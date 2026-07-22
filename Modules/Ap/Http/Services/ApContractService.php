<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApContractSignImage;
use Modules\Ap\Entities\ApCustContract;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Enums\ApAccountTypeEnum;
use Modules\Ap\Http\Controllers\ApInstController;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\Views\VwCrCustAddress;
use Modules\Dp\Entities\DpAccountType;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstPerm;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\NotificationSendMailJob;
use Modules\Re\Entities\ReInstReportTemp;
use Modules\Re\Entities\ReInstReportTempContent;
use Modules\Gp\Entities\GPInstRolePerms;
use Modules\Gp\Entities\GPInstUserRole;
use Modules\Ln\Entities\LnAccountType;

class ApContractService
{

    /**
     * Зээлийн гэрээ
     *
     * @param  mixed $data = ['instid','type_id','acnt_code']
     * @return void
     */
    public function getFillContract($data, $user, $apcust)
    {
        $prod_code = '';
        $prod = null;
        $operation = '';

        if (!empty($apcust->regno)) {
            $cust = ApCustomer::where('instid', $data['instid'])
                ->where('regno', $apcust->regno)
                ->where('statusid', 1)->first();
        } else {
            $cust = ApCustomer::where('instid', $data['instid'])
                ->where('regno', $user->regno)
                ->where('statusid', 1)->first();
        }

        if (empty($cust)) {
            throw new MeException('RC000176');
        }
        $polaris = new PolarisApiRequestService($data['instid']);
        $end_date = '';
        if (empty($data['int_rate'])) {
            $data['int_rate'] = 0;
        }
        $coll_contract_no = '...';
        $parent_code = '';
        switch ($data['type_id']) {
                // Хадгаламж барьцаалсан зээл
            case ApAccountTypeEnum::td:
                $parent_code = 'PRODUCTS_PRODCODE';
                $prod_code = $polaris->savingLoan->loanAcnt->prodCode;
                $acntService = new ApAcntService();
                $acntService->getTdAccountDetail($data['acnt_code'], $data['instid']);
                $acnt = ApAcntDp::where('acnt_code', $data['acnt_code'])
                    ->where('instid', $data['instid'])->first();

                if (empty($acnt)) {
                    throw new MeException('RC000034', [
                        'mainacntno' => $data['acnt_code']
                    ]);
                }
                if (empty($acnt->maturity_date)) {
                    throw new MeException('Хадгаламжийн данс дээрх дуусах хугацаа бүртгэлгүй байна.');
                }
                $tddata = null;
                $end_date = $acnt->maturity_date;
                try {
                    $tddata = $acntService->getTdCollInfo($data['acnt_code'], $data['instid']);
                } catch (Exception $ex) {
                    Log::error($ex);
                }

                if (isset($tddata)) {
                    $coll_contract_no = $tddata->acntCode;
                }
                break;
            // Line зээл
            case ApAccountTypeEnum::line:

                $parent_code = 'PRODUCTS_LN_PRODCODE';
                $acnt = ApAcntLn::where('acnt_code', $data['acnt_code'])
                    ->where('instid', $data['instid'])->first();
                if (empty($acnt)) {
                    throw new MeException('RC000034', [
                        'mainacntno' => $data['acnt_code']
                    ]);
                }

                $end_date = $acnt->end_date;

                $prod_code = $acnt->prod_code;
                break;
            default:
                # code...
                break;
        }

        $prod = LnAccountType::where('prodcode', $prod_code)->where('instid', $data['instid'])->where('statusid', '<>', -1)->first();

        $dicbank = GPInstConst::where('parent_code', 'bank')
            ->where('value', $data['bank_code'])
            ->where('statusid', 1)->first();
        $now = strtotime("now");
        try {
            $txndate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $data['instid']]));
            $time = strtotime($txndate);
        } catch (\Throwable $th) {
            Log::debug($th);
            $time = $now;
            //throw $th;
        }
        $diff_day = 0;
        if (isset($end_date)) {
            $diff_day = round((strtotime($end_date) - $time) / (60 * 60 * 24));
        }
        
        $configcontract = GPInstConst::where('parent_code', $parent_code)
            ->where('value', $prod_code)
            ->where('instid', $data['instid'])
            ->where('statusid', 1)->first();
        /**
         * $configcontract->value_add1 - report template process code hadgalagdana
         */
        if (empty($configcontract) || empty($configcontract->value_add1)) {
            throw new MeException('RC000180');
        }

        $contract = ReInstReportTemp::where('statusid', 1)
            ->whereIn('ACTION_CODE', function ($query) use ($data) {
                $query->select('AC')
                    ->from(with(new GPInstPerm())->getTable())
                    ->where('instid', $data['instid'])
                    ->where('statusid', '<>', -1);
            })->where('ACTION_CODE', $configcontract->value_add1)->first();

        if (!$contract) {
            // if ($data['type_id'] == ApAccountTypeEnum::line) {
            //     return null;
            // }
            throw new MeException('RC000180');
        } else {
            $contract = ReInstReportTempContent::where('templateid', $contract->id)
                ->where('statusid', 1)->first();
            if (!$contract) {
                // if ($data['type_id'] == ApAccountTypeEnum::line) {
                //     return null;
                // }
                throw new MeException('RC000180');
            }
        }

        $addr = $this->getAddressCust($cust->cif, $data['instid']);
        $searchVal = array(
            '${year}',
            '${month}',
            '${day}',
            '${account}',
            '${contract_no}',
            '${coll_contract_no}',
            '${sign_photo}'
        );

        $base64Image = "";
        if (isset($data['sign_image_id'])) {
            $image = ApContractSignImage::where('id', $data['sign_image_id'])
                ->where('instid', $data['instid'])
                ->where('statusid', 1)
                ->first();
            $base64Image = stream_get_contents($image->image);
        }

        $replaceVal = array(
            date("Y", $time),
            date("m", $time),
            date("d", $time),
            $data['acnt_code'],
            $data['contract_no'] ?? '...',
            $coll_contract_no ?? '...',
            'data:image/png;base64,' . $base64Image,
        );
        $body = str_replace($searchVal, $replaceVal, $contract->source);
        $searchVal = array('${address}', '${register}', '${last_name}', '${first_name}');
        $replaceVal = array($addr, $cust->regno, $cust->lname, $cust->fname);
        $body = str_replace($searchVal, $replaceVal, $body);
        $intamount = ($data['amount'] * $data['int_rate'] / 100 / 365) * $diff_day;
        $textLoanAmount = numbertotext($data['amount'], 2);
        $searchVal = array(
            '${loan_amount}',
            '${text_loan_amount}',
            '${rcv_account}',
            '${rcv_bank_name}',
            '${maturity_date}',
            '${diff_day}',
            '${int_rate_month}',
            '${int_rate_year}',
            '${int_amount}',
            '${sum_amount}',
            '${account_balance}',
            '${prod_name}',
        );
        $replaceVal = array(
            number_format($data['amount'], 2),
            $textLoanAmount,
            $data['rcv_account'],
            $dicbank->name ?? '',
            substr($end_date, 0, 10),
            $diff_day,
            number_format($data['int_rate'] / 12, 2),
            number_format($data['int_rate'], 2),
            number_format($intamount, 2),
            number_format($intamount + $data['amount'], 2),
            number_format($acnt->current_bal, 2),
            @$prod->name ?? '',
        );
        $body = str_replace($searchVal, $replaceVal, $body);
        return $body;
    }

    public function getContractNewAccount($data, $user)
    {
        $prod_code = $data['productno'];
        $cust = ApCustomer::where('instid', $data['instid'])
            ->where('regno', $user->regno)
            ->where('statusid', '1')->first();
        if (empty($cust)) {
            throw new MeException('RC000176');
        }
        $time = strtotime("now");
        try {
            $txndate = (new ApInstController())->oi000180((new Request())
                ->merge(['instid' => $data['instid']]));
            $time = strtotime($txndate);
        } catch (\Throwable $th) {
            Log::debug($th);
            //throw $th;
        }
        $dicmain = GPInstConst::where('parent_code', 'PRODUCTS_TD_PRODCODE')
            ->where('value', $prod_code)
            ->where('instid', $data['instid'])
            ->where('statusid', 1)->first();
        if (empty($dicmain)) {
            throw new MeException('RC000037', ['prodcode' => $prod_code]);
        }
        $termln = GPInstConst::where('parent_code', $dicmain->code)
            ->where('instid', $data['instid'])
            ->where('value_add1', 'LENGTH')->first();

        if (!$termln) {
            throw new MeException('RC000199');
        }

        $prodint = GPInstConst::where('parent_code', $dicmain->code)
            ->where('instid', $data['instid'])
            ->where('value_add1', 'INT')->first();

        if (!$prodint) {
            throw new MeException('RC000200');
        }

        $data['prodInt'] = $prodint->value * 1;
        $yearint = 12 * $data['prodInt'];
        $data['termLen'] = $termln->value;
        $data['maturityDate']  = (new Carbon())->addMonths($data['termLen']);
        $end_date = new Carbon($data['maturityDate']);

        /**
         * $dicmain->value_add1 - report template process code hadgalagdana
         */
        if (empty($dicmain) || empty($dicmain->value_add1)) {
            throw new MeException('RC000180');
        }

        $contract = ReInstReportTemp::where('statusid', 1)
            ->whereIn('ACTION_CODE', function ($query) use ($data) {
                $query->select('AC')
                    ->from(with(new GPInstPerm())->getTable())
                    ->where('instid', $data['instid'])
                    ->where('statusid', '<>', -1);
            })->where('ACTION_CODE', $dicmain->value_add1)->first();

        // $contract = Contract::where('statusid', '<>', -1)->where('instid', $data['instid'])
        //     ->where('prod_code', $prod_code)->where('operation', $operation)->first();
        if (!$contract) {
            // if ($data['type_id'] == ApAccountTypeEnum::line) {
            //     return null;
            // }
            throw new MeException('RC000180');
        } else {
            $contract = ReInstReportTempContent::where('templateid', $contract->id)
                ->where('statusid', 1)->first();
            if (!$contract) {
                // if ($data['type_id'] == ApAccountTypeEnum::line) {
                //     return null;
                // }
                throw new MeException('RC000180');
            }
        }

        $prod = DpAccountType::where('prodcode', $prod_code)->where('instid', $data['instid'])->where('statusid', '<>', -1)->first();

        $addr = $this->getAddressCust($cust->cif, $data['instid']);

        $searchVal = array('${year}', '${month}', '${day}', '${contract_no}');
        $replaceVal = array(date("Y", $time), date("m", $time), date("d", $time), $data['contract_no'] ?? '...');
        $body = str_replace($searchVal, $replaceVal, $contract->source);

        $searchVal = array('${custno}', '${register}', '${last_name}', '${first_name}', '${sign_photo}', '${address}');
        $base64Image = "";
        if (isset($data['sign_image_id'])) {
            $image = ApContractSignImage::where('id', $data['sign_image_id'])
                ->where('instid', $data['instid'])
                ->where('statusid', 1)
                ->first();
            $base64Image = stream_get_contents($image->image);
        }

        $replaceVal = array($cust->cif, $cust->regno, $cust->lname, $cust->fname, 'data:image/png;base64,' . $base64Image, $addr);
        $body = str_replace($searchVal, $replaceVal, $body);

        $textLoanAmount = numbertotext($data['amount'], 2);

        $searchVal = array('${amount}', '${td_month}', '${td_year_int}', '${td_month_int}', '${maturity_year}', '${maturity_month}', '${maturity_day}', '${prod_name}', '${text_amount}');
        $replaceVal = array($data['amount'], $data['termLen'], $yearint, $data['prodInt'], $end_date->year, $end_date->month, $end_date->day, @$prod->name ?? '', $textLoanAmount);
        $body = str_replace($searchVal, $replaceVal, $body);

        return $body;
    }

    public function getAddressCust($cif, $instid)
    {
        $addr = '';
        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            $cust = CrCustInd::where('custno', $cif)
                ->where('instid', $instid)->first();
            $addrResp = VwCrCustAddress::where('custid', $cust->id)
                ->where('addrtypecode', 1)
                ->where('instid', $instid)->first();
            if ($addrResp) {
                $addr = $addrResp->state_name . ' ' . $addrResp->region_name . ' ' . $addrResp->subregion_name;
            }
        } else {
            try {
                $polaris = new PolarisApiRequestService($instid);
                $addrResp = $polaris->sendRequest(13619991, [['custCode' => $cif]], $instid);
                foreach ($addrResp as $key => $value) {
                    if (($value['isMain'] ?? 0) == 1) {
                        $addr = ($value['addrName'] ?? '') . ' ' . ($value['addrDetail'] ?? '');
                    }
                }
            } catch (\Throwable $th) {
                Log::debug($th);
                //throw $th;
            }
        }
        return $addr;
    }

    public function storeCustContract($data, $serviceCode, $cust)
    {
        $user = auth()->user();
        $conf = new ApCustContract();
        foreach ($conf->getFillable() as $field) {
            if (array_key_exists($field, $data)) {
                $conf->$field = $data[$field];
            }
        }

        try {
            $subject = '';
            if ($serviceCode == '20000001') {
                $subject = 'Хадгаламжийн данс үүсгэх хүсэлт';
                $respcont = $this->getContractNewAccount([
                    'instid' => $data['instid'],
                    'type_id' => $data['type_id'],
                    'productno' => $data['prod_code'],
                    'acnt_code' => $data['acnt_code'],
                    'amount' => $data['amount'],
                    'sign_image_id' => $data['sign_image_id'],
                    'contract_no' => $data['account_no'] ?? '...',
                ], $user);
            } else {
                $subject = 'Зээлийн гэрээ';
                $respcont = $this->getFillContract([
                    'instid' => $data['instid'],
                    'type_id' => $data['type_id'],
                    'acnt_code' => $data['acnt_code'],
                    'amount' => $data['amount'],
                    'int_rate' => $data['int_rate'] ?? '',
                    'rcv_account' => $data['bank_acnt_code'] ?? '',
                    'bank_code' => $data['bank_code'] ?? '',
                    'sign_image_id' => $data['sign_image_id'],
                    'contract_no' => $data['account_no'] ?? '...',
                ], $user, $cust);
            }
            $conf->contract = $respcont;
            // $email = [
            //     "to" => $user->email,
            //     "subject" => $subject,
            //     "data" => [
            //         ''
            //     ],
            //     "template" => "mail.contract"
            // ];

            // NotificationSendMailJob::dispatch($email)->onQueue('sendMail');

        } catch (Exception $ex) {
            Log::error($ex);
            $conf->contract = 'Гэрээ үүсээгүй байна.';
        }
        $conf->statusid = 1;
        $conf->created_at = Carbon::now();
        $conf->created_by = $user->id;
        $conf->save();

        return $conf;
    }

    public function previewContract($data, $serviceCode, $cust)
    {
        $user = auth()->user();

        return  $this->getFillContract([
            'instid'        => $data['instid'],
            'type_id'       => $data['type_id'],
            'acnt_code'     => $data['acnt_code'],
            'amount'        => $data['amount'],
            'int_rate'      => $data['int_rate'] ?? 0,
            'rcv_account'   => $data['bank_acnt_code'] ?? '',
            'bank_code'     => $data['bank_code'] ?? '',
            'sign_image_id' => $data['sign_image_id'],
            'contract_no'   => $data['account_no'] ?? '...',
        ], $user, $cust);
    }
}
