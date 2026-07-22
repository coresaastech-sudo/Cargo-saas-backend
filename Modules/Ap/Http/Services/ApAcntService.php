<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApAcntCd;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApAcntSchedule;
use Modules\Ap\Entities\ApAcntStatement;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApTxnJournal;
use Modules\Ap\Enums\ApAccountTypeEnum;
use Modules\Ap\Transformers\ApAccountStatementCollection;
use Modules\Ap\Transformers\ApAcntScheduleCollection;
use Modules\Ap\Transformers\ApCasaCollection;
use Modules\Ap\Transformers\ApCcaCollection;
use Modules\Ap\Transformers\ApLoanCollection;
use Modules\Gp\Entities\GPInstConst;
use Illuminate\Support\Str;
use Modules\Ap\Entities\ApAcntInt;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Http\Controllers\ApInstController;
use Modules\Ap\Transformers\ApAccountIntCollection;
use Modules\Cr\Entities\Views\VwCrCustAllAcntWithBalance;
use Modules\Dp\Entities\DpAccountType;
use Modules\Dp\Entities\Views\VwDpAccountDetail;
use Modules\Dp\Entities\Views\VwDpAccountInquiry;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ln\Entities\Views\VwLnAccountDetail;
use Modules\Ln\Entities\Views\VwLnAccountInquiry;
use Modules\Ln\Entities\Views\VwLnMorDetail;
use Modules\Gp\Entities\GPPhoto;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Modules\Gp\Entities\GPInstPerm;
use Modules\Re\Entities\ReInstReportTemp;
use Modules\Re\Entities\ReInstReportTempContent;
use Illuminate\Support\Facades\Storage;

class ApAcntService
{
    public function calcDiffDays($date1, $date2)
    {
        $diff = abs(strtotime($date2) - strtotime($date1));
        return floor($diff / (24 * 60 * 60));
    }

    public function getAcntTypes()
    {
        return GPInstConst::where('statusid', 1)->where('parent_code', 'NES_ACNT_TYPE')->get();
    }

    public function getAcntTypeTable($acntType, $acntTypes)
    {
        foreach ($acntTypes as $key => $value) {
            if ($value->value == $acntType) {
                return $value->maintype;
            }
        }
    }

    public function checkDates($date1, $date2, $day = 93)
    {
        $diff = $this->calcDiffDays($date1, $date2);
        if ($diff > $day) {
            throw new MeException("RC000185", ['day' => $day, 'diff' => $diff]);
        }
        return [
            'data' => "Success"
        ];
    }

    public function getRepaymentSchedule($acnt, $instid)
    {
        $polaris = new PolarisApiRequestService($instid);
        try {
            $respdata = $polaris->sendRequest(13610203, [$acnt], $instid);
            $this->createRepaymentSchedule($respdata, $instid, $acnt);
        } catch (Exception $ex) {
            $respdata = $this->getRepaymentSchedules($acnt, $instid);
        }

        return $respdata;
    }

    public function createRepaymentSchedule($datas, $instid, $acnt_code)
    {
        ApAcntSchedule::where('instid', $instid)->where('acnt_code', $acnt_code)->delete();
        foreach ($datas as $data) {
            $schdl = new ApAcntSchedule();
            $schdl->instid = $instid;
            $schdl->acnt_code = $acnt_code;
            $schdl->schd_date = formatDate($data['schdDate'] ?? null);
            $schdl->amount = $data['amount'] ?? null;
            $schdl->int_amount = $data['intAmount'] ?? null;
            $schdl->total_amount = $data['totalAmount'] ?? null;
            $schdl->theor_bal = $data['theorBal'] ?? null;
            $schdl->statusid = 1;
            $schdl->created_by = auth()->user()->id;
            $schdl->save();
        }
    }

    public function getRepaymentSchedules($acnt_code, $instid)
    {
        return new ApAcntScheduleCollection(ApAcntSchedule::where('instid', $instid)->where('acnt_code', $acnt_code)->get());
    }

    public function getAccountStatement($data, $instid)
    {
        $polaris = new PolarisApiRequestService($instid);
        $respdata = $polaris->sendRequest(13610302, [$data], $instid);

        $this->createAcntStatement($respdata['txns'] ?? [], $instid, $data['acntCode']);

        return $this->getStatements($data, $instid);
        // return $respdata;
    }

    public function createAcntStatement($datas, $instid, $acnt_code)
    {
        foreach ($datas as $data) {
            if (!empty($data['jrno'])) {
                ApAcntStatement::where('jrno', $data['jrno'])->where('instid', $instid)->where('acnt_code', $acnt_code)->delete();
                $stmnt = ApAcntStatement::where('jrno', $data['jrno'])->where('instid', $instid)->where('acnt_code', $acnt_code)->first();
                if (empty($stmnt)) {
                    $stmnt = new ApAcntStatement();
                    $stmnt->instid = $instid;
                    $stmnt->acnt_code = $acnt_code;
                }
                $stmnt->cont_cur_rate = $data['contCurRate'] ?? null;
                $stmnt->income = $data['income'] ?? null;
                $stmnt->jrno = $data['jrno'] ?? null;
                $stmnt->begin_bal = $data['beginBal'] ?? null;
                $stmnt->end_bal = $data['endBal'] ?? null;
                $stmnt->txn_date = str_replace('.', '-', $data['txnDate']);
                $stmnt->txn_code = $data['txnCode'] ?? null;
                $stmnt->bal_type_code = $data['balTypeCode'] ?? null;
                $stmnt->outcome = $data['outcome'] ?? null;
                $stmnt->balance = $data['balance'] ?? null;
                $stmnt->txn_desc = $data['txnDesc'] ?? null;
                $stmnt->cont_acnt_code = $data['contAcntCode'] ?? null;
                $stmnt->cont_bank_acnt_code = $data['contBankAcntCode'] ?? null;
                $stmnt->cont_bank_acnt_name = $data['contBankAcntName'] ?? null;
                $stmnt->cont_bank_code = $data['contBankCode'] ?? null;
                $stmnt->cont_bank_name = $data['contBankName'] ?? null;
                $stmnt->post_date = formatDate($data['postDate'] ?? null);
                $stmnt->statusid = 1;
                $stmnt->created_by = auth()->user()->id;
                $stmnt->save();
            }
        }
    }

    public function getStatements($data, $instid)
    {
        $from = date($data['startDate']);
        $to = date($data['endDate']);
        return new ApAccountStatementCollection(
            ApAcntStatement::where('acnt_code', $data['acntCode'])
                ->where('instid', $instid)
                ->orderBy('txn_date', 'desc')
                ->orderBy('jrno', 'desc')
                ->whereBetween('txn_date', [$from, $to])
                ->skip($data['startPosition'])->take($data['count'])->get()
        );
    }

    /**
     * getAccounts - inst дээр бүртгэлтэй данс авах
     *
     * @param  mixed $user
     * @param  mixed $instid
     * @return array
     */
    public function getAccountsNotCollection($user, $instid, $isall = false)
    {
        if (empty($user)) {
            $user = auth()->user();
        }
        $acnts = [];
        $casasql = ApAcntDp::where('instid', $instid)
            ->where('userid', $user->id)->where('statusid', 1);
        $lnsql = ApAcntLn::where('instid', $instid)
            ->where('userid', $user->id)->where('statusid', 1);
        $ccasql = ApAcntCd::where('instid', $instid)
            ->where('userid', $user->id)->where('statusid', 1);
        if (!$isall) {
            // $tdsql = $tdsql->whereIn('status', ['O', 'N']);
            $casasql = $casasql->whereIn('status', ['O', 'N']);
            $lnsql = $lnsql->whereIn('status', ['O', 'N']);
            $ccasql = $ccasql->whereIn('status_sys', ['O', 'N']);
        }
        $casa = $casasql->get();
        // $td = $tdsql->get();
        $ln = $lnsql->get();
        $cca = $ccasql->get();
        foreach ($casa as $item) {
            $acnts[] = [
                'acntCode' => $item->acnt_code,
                'status' => $item->status,
                'sysNo' => $item->sys_no,
                'acntType' => $item->acnt_type
            ];
        }
        // foreach ($td as $item) {
        //     $acnts[] = [
        //         'acntCode' => $item->acnt_code,
        //         'status' => $item->status,
        //         'sysNo' => $item->sys_no,
        //         'acntType' => $item->acnt_type
        //     ];
        // }
        foreach ($ln as $item) {
            $acnts[] = [
                'acntCode' => $item->acnt_code,
                'status' => $item->status,
                'sysNo' => $item->sys_no,
                'acntType' => $item->acnt_type
            ];
        }
        foreach ($cca as $item) {
            $acnts[] = [
                'acntCode' => $item->acnt_code,
                'status' => $item->status_sys,
                'sysNo' => $item->sys_no,
                'acntType' => $item->acnt_type
            ];
        }
        return $acnts;
    }

    /**
     * getAccounts - inst дээр бүртгэлтэй данс авах
     *
     * @param  mixed $user
     * @param  mixed $instid
     * @return array
     */
    public function getAccounts($user, $instid, $isall = false)
    {
        if (empty($user)) {
            $user = auth()->user();
        }
        $acnts = [];
        // with('inst:id,instname,instnameeng,logo,color')
        $casasql = ApAcntDp::where('instid', $instid)
            ->where('userid', $user->id)->where('statusid', 1);
        $lnsql = ApAcntLn::where('instid', $instid)
            ->where('userid', $user->id)->where('statusid', 1);
        $ccasql = ApAcntCd::where('instid', $instid)
            ->where('userid', $user->id)->where('statusid', 1);
        if (!$isall) {
            // $tdsql = $tdsql->whereIn('status', ['O', 'N']);
            $casasql = $casasql->whereIn('status', ['O', 'N', 1, 4]);
            $lnsql = $lnsql->whereIn('status', ['O', 'N', 1, 4]);
            $ccasql = $ccasql->whereIn('status_sys', ['O', 'N', 1, 4]);
        }
        $casa = new ApCasaCollection($casasql->get());
        $ln = new ApLoanCollection($lnsql->get());
        $cca = new ApCcaCollection($ccasql->get());
        $inst = GPInstList::select([
            'name',
            'name2',
            'logo',
        ])->find($instid);
        // dd($casa);
        foreach ($casa as $item) {
            $item->inst = $inst;
            $acnts[] = $item;
        }
        foreach ($ln as $item) {
            $item->inst = $inst;
            $acnts[] = $item;
        }
        foreach ($cca as $item) {
            $item->inst = $inst;
            $acnts[] = $item;
        }
        return $acnts;
    }

    /**
     * getAllAccounts - хэрэглэгчийн inst үл хамааран бүх данс авах
     *
     * @param  mixed $user
     * @return void
     */

    public function getAllAccounts($user, $isall = false)
    {
        if (empty($user)) {
            $user = auth()->user();
        }
        $acnts = [];
        $casasql = ApAcntDp::where('userid', $user->id)->where('statusid', 1);
        $lnsql = ApAcntLn::where('userid', $user->id)->where('statusid', 1);
        $ccasql = ApAcntCd::where('userid', $user->id)->where('statusid', 1);
        if (!$isall) {
            // $tdsql = $tdsql->whereIn('status', ['O', 'N']);
            $casasql = $casasql->whereIn('status', ['O', 'N']);
            $lnsql = $lnsql->whereIn('status', ['O', 'N']);
            $ccasql = $ccasql->whereIn('status_sys', ['O', 'N']);
        }
        $casa = new ApCasaCollection($casasql->get());
        $ln = new ApLoanCollection($lnsql->get());
        $cca = new ApCcaCollection($ccasql->get());
        foreach ($casa as $item) {
            $acnts[] = $item;
        }
        // foreach ($td as $item) {
        //     $acnts[] = $item;
        // }
        foreach ($ln as $item) {
            $acnts[] = $item;
        }
        foreach ($cca as $item) {
            $acnts[] = $item;
        }
        return $acnts;
    }

    public function createCasaAcntList($datas = [], $user = [], $instid = "")
    {
        if (empty($user)) {
            $user = auth()->user();
        }
        // CasaAcnt::where('userid', $user->id)->where('instid', $instid)->delete();
        // TdAcnt::where('userid', $user->id)->where('instid', $instid)->delete();
        // LoanAcnt::where('userid', $user->id)->where('instid', $instid)->delete();
        // CcaAcnt::where('userid', $user->id)->where('instid', $instid)->delete();
        $acnts = [];
        foreach ($datas as $data) {
            if (@$data['sysNo'] != 1312) {
                $type = ApAccountTypeEnum::fromString(Str::lower($data['acntType']));
                if ($type == ApAccountTypeEnum::casa_acnt || $type == ApAccountTypeEnum::td) {
                    $acnts[] = $data;
                    $casaService = new ApCasaAcntService();
                    $casaService->createAccountNes($data, $instid, $user);
                } else if (
                    $type == ApAccountTypeEnum::loan || $type == ApAccountTypeEnum::line
                ) {
                    $acnts[] = $data;
                    $lnService = new ApLoanAcntService();
                    $lnService->createAccountNes($data, $instid, $user);
                } else if ($type == ApAccountTypeEnum::cca) {
                    $acnts[] = $data;
                    $ccaService = new ApCcaAcntService();
                    $ccaService->createAccountNes($data, $instid, $user);
                }
            }
        }

        return $acnts;
    }

    public function getCasaAccountDetail($acnt, $instid)
    {
        // $type CA, SA аль нэг нь байхад болно.
        return $this->getAccountDetail($acnt, 'CA', $instid);
    }

    public function getLoanAccountDetail($acnt, $instid)
    {
        return $this->getAccountDetail($acnt, 'LOAN', $instid);
    }

    public function getTdAccountDetail($acnt, $instid)
    {
        return $this->getAccountDetail($acnt, 'TD', $instid);
    }

    public function getCreditAccountDetail($acnt, $instid)
    {
        return $this->getAccountDetail($acnt, 'CCA', $instid);
    }

    /**
     * getAccountDetail - Дансны дэлгэрэнгүй
     *
     * @param  mixed $acnt - Дансны дугаар
     * @param  mixed $type - Дансны төрөл
     * @param  mixed $instid - Байгууллагын дугаар
     * @param  mixed $isBackOff - Backoffice системээс хандаж байгаа эсэх
     *
     */
    public function getAccountDetail($acnt, $type, $instid, $isBackOff = false)
    {
        $user = auth()->user();
        if ($instid == 0) {
            $instid = $user->instid;
        }
        $operation = 0;
        if (!$isBackOff) {
            $connInst = ApInstCustUserLink::where('instid', $instid)
                ->where('cust_userid', $user->id)->where('statusid', 1)->first();
            if (!$connInst) {
                throw new MeException('Тухайн байгууллагад бүртгэлгүй байна.');
            }
        }
        $acntType = ApAccountTypeEnum::fromString(Str::lower($type));
        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            $this->getAccountDetailCore($acnt, $instid, $acntType);
        } else {
            switch ($acntType) {
                case ApAccountTypeEnum::ln:
                    $account = ApAcntLn::where('acnt_code', $acnt)->where('instid', $instid);
                    if (!$isBackOff) {
                        $account = $account->where('userid', $user->id);
                    }
                    $account = $account->first();
                    if (empty($account)) {
                        throw new MeException('RC000034', ['mainacntno' => $acnt]);
                    }
                    $operation = 13610200;
                    break;
                case ApAccountTypeEnum::casa_acnt:
                    $account = ApAcntDp::where('acnt_code', $acnt)->where('instid', $instid);
                    if (!$isBackOff) {
                        $account = $account->where('userid', $user->id);
                    }
                    $account = $account->first();
                    if (empty($account)) {
                        throw new MeException('RC000034', ['mainacntno' => $acnt]);
                    }
                    $operation = 13610000;
                    break;
                case ApAccountTypeEnum::td:
                    $account = ApAcntDp::where('acnt_code', $acnt)->where('instid', $instid);
                    if (!$isBackOff) {
                        $account = $account->where('userid', $user->id);
                    }
                    $account = $account->first();
                    if (empty($account)) {
                        throw new MeException('RC000034', ['mainacntno' => $acnt]);
                    }
                    $operation = 13610100;
                    break;
                case ApAccountTypeEnum::cca:
                    $account = ApAcntCd::where('acnt_code', $acnt)->where('instid', $instid);
                    if (!$isBackOff) {
                        $account = $account->where('userid', $user->id);
                    }
                    $account = $account->first();
                    if (empty($account)) {
                        throw new MeException('RC000034', ['mainacntno' => $acnt]);
                    }
                    $operation = 13610400;
                    break;

                default:
                    # code...
                    break;
            }
            $this->getAccountDetailPolaris($operation, $acnt, $instid, $type, $isBackOff);
        }

        return $this->getInterAccountDetail($acnt, $instid, $type, $isBackOff);
    }

    /**
     * getAccountDetailCore
     *
     * @return void
     */
    public function getAccountIntCore($acnt, $instid, $acntType)
    {
        switch ($acntType) {
            case ApAccountTypeEnum::ln:
                $loanAcnt = new ApAcntService();
                $data = VwLnAccountDetail::where('acntno', $acnt)->where('instid', $instid)->first();
                if (empty($data)) {
                    throw new MeException('RC000034', ['mainacntno' => $acnt]);
                }

                return $loanAcnt->updateAcntIntCoreInfo($data, $acnt, $instid, $acntType);
                break;

            case ApAccountTypeEnum::casa_acnt:
            case ApAccountTypeEnum::td:
                $loanAcnt = new ApAcntService();
                $data = VwDpAccountDetail::where('acntno', $acnt)->where('instid', $instid)->first();
                if (empty($data)) {
                    throw new MeException('RC000034', ['mainacntno' => $acnt]);
                }

                return $loanAcnt->updateAcntIntCoreInfo($data, $acnt, $instid, $acntType);
                break;
            default:
                # code...
                break;
        }
    }
    /**
     * getAccountDetailCore
     *
     * @return void
     */
    public function getAccountDetailCore($acnt, $instid, $acntType)
    {
        switch ($acntType) {
            case 'LOAN_ACNT':
                $loanAcnt = new ApLoanAcntService();
                $data = VwLnAccountDetail::where('acntno', $acnt)->where('instid', $instid)->first();
                if (empty($data)) {
                    throw new MeException('RC000034', ['mainacntno' => $acnt]);
                }
                $inquiry = VwLnAccountInquiry::where('acntno', $acnt)->where('instid', $data->instid)->first();
                if ($inquiry) {
                    $data['purp_name'] = $inquiry->purp_name;
                    $data['subpurpcode_name'] = $inquiry->subpurpcode_name;
                    $data['nextpayamount'] = $inquiry->payamount;
                    $data['nextpaysumint'] = $inquiry->nextpaysumint;
                    $data['debttopay'] = $inquiry->debttopay;
                    $data['nowclosebalance'] = $inquiry->nowclosebalance;
                    $data['total_bill'] =  $data->capbint + $data->capcint +  $data->capfint + $data->baseint2cap + $data->comint2cap + $data->fineint2cap + $data->adjbint2cap + $data->adjcint2cap + $data->adjfint2cap;
                }

                $loanAcnt->updateAcntCoreData($data, $instid);
                break;
            case 'CASA_ACNT':
                $casaAcnt = new ApCasaAcntService();
                $data = VwDpAccountDetail::where('acntno', $acnt)->where('instid', $instid)->first();
                if (empty($data)) {
                    throw new MeException('RC000034', ['mainacntno' => $acnt]);
                }

                $casaAcnt->updateAcntCoreData($data, $instid);
                break;
            case 'TD_ACNT':
                $tdAcnt = new ApCasaAcntService();
                $data = VwDpAccountDetail::where('acntno', $acnt)
                    ->where('procflag', 'T')
                    ->where('instid', $instid)->first();
                if (empty($data)) {
                    throw new MeException('RC000034', ['mainacntno' => $acnt]);
                }
                $inquiry = VwDpAccountInquiry::where('acntno', $data->acntno)->where('instid', $data->instid)->first();
                if ($inquiry) {
                    $data['availBal'] = $inquiry->currentbal;
                }

                $tdAcnt->updateAcntCoreData($data, $instid);
                break;
            case 'CCA_ACNT':
                throw new MeException('RC000034', ['mainacntno' => $acnt]);
                // $ccaAcnt = new ApCcaAcntService();
                // $ccaAcnt->updateAcntCoreData($data, $instid);
                break;

            default:
                # code...
                break;
        }
    }

    /**
     * getAccountDetailPolaris
     *
     * @return void
     */
    public function getAccountDetailPolaris($operation, $acnt, $instid, $type, $isBackOff = false)
    {
        $polaris = new PolarisApiRequestService($instid);
        /**
         *  $acnt - [Дансны дугаар, Нууцлалтай авах эсэх]
         * */
        $respdata = $polaris->sendRequest($operation, [$acnt, 0], $instid);
        $this->updateAcntInfo($respdata, $instid, $type);
        return $respdata;
    }

    public function getInterAccountDetail($acnt_code, $instid, $type, $isBackOff)
    {

        $acntType = ApAccountTypeEnum::fromString(Str::lower($type));
        $dtData = [];
        switch ($acntType) {
            case 'LOAN_ACNT':
                $loanAcnt = new ApLoanAcntService();
                $dtData = $loanAcnt->detailAcntData($acnt_code, $instid, $isBackOff);
                break;
            case 'CASA_ACNT':
                $casaAcnt = new ApCasaAcntService();
                $dtData = $casaAcnt->detailAcntData($acnt_code, $instid, $isBackOff);
                break;
            case 'TD_ACNT':
                $tdAcnt = new ApCasaAcntService();
                $dtData = $tdAcnt->detailAcntData($acnt_code, $instid, $isBackOff);
                break;
            case 'CCA_ACNT':
                $ccaAcnt = new ApCcaAcntService();
                $dtData = $ccaAcnt->detailAcntData($acnt_code, $instid, $isBackOff);
                break;

            default:
                # code...
                break;
        }
        return $dtData;
    }

    /**
     * updateAcntInfo - Дансны мэдээлэл засварлах
     *
     * @param  mixed $data - Дансны мэдээлэл
     * @param  mixed $instid - Байгууллагын дугаар
     * @param  mixed $type - Дансны төрөл
     * @return void
     */
    public function updateAcntInfo($data, $instid, $type)
    {
        $acntType = ApAccountTypeEnum::fromString(Str::lower($type));
        switch ($acntType) {
            case 'LOAN_ACNT':
                $loanAcnt = new ApLoanAcntService();
                $loanAcnt->updateAcntNesData($data, $instid);
                break;
            case 'CASA_ACNT':
                $casaAcnt = new ApCasaAcntService();
                $casaAcnt->updateAcntNesData($data, $instid);
                break;
            case 'TD_ACNT':
                $tdAcnt = new ApCasaAcntService();
                $tdAcnt->updateAcntNesData($data, $instid);
                break;
            case 'CCA_ACNT':
                $ccaAcnt = new ApCcaAcntService();
                $ccaAcnt->updateAcntNesData($data, $instid);
                break;

            default:
                # code...
                break;
        }
    }

    /**
     * Системийн дансны хүүний мэдээллийг шинэчлэх
     *
     * @param  mixed $acntCode
     * @param  mixed $instid
     * @return void
     */
    public function updateAcntIntNesInfo($data, $acntCode, $instid)
    {
        ApAcntInt::where('instid', $instid)->where('acnt_code', $acntCode)->delete();
        if (empty($data)) {
            return;
        }
        $user = auth()->user();
        for ($i = 0; $i < count($data); $i++) {
            $elem = $data[$i];
            $acnt = new ApAcntInt();
            $acnt->acnt_code = $acntCode;
            $acnt->instid = $instid;
            $acnt->userid = $user->id;
            $acnt->statusid = 1;
            $acnt->created_at = Carbon::now();
            $acnt->created_by = $user->id;
            $acnt->other_info = $elem['otherInfo'] ?? null;
            $acnt->pay_cust_name = $elem['payCustName'] ?? null;
            $acnt->int_rate = $elem['intRate'] ?? null;
            $acnt->source_bal_type = $elem['sourceBalType'] ?? null;
            $acnt->last_acr_info = $elem['lastAcrInfo'] ?? null;
            $acnt->type = $elem['type'] ?? null;
            $acnt->accr_int_amt = $elem['accrIntAmt'] ?? null;
            $acnt->int_type_name = $elem['intTypeName'] ?? null;
            $acnt->int_rate_option = $elem['intRateOption'] ?? null;
            $acnt->daily_int_amt = $elem['dailyIntAmt'] ?? null;
            $acnt->last_acr_txn_seq = $elem['lastAcrTxnSeq'] ?? null;
            $acnt->bal_type_code = $elem['balTypeCode'] ?? null;
            $acnt->int_type_code = $elem['intTypeCode'] ?? null;
            $acnt->last_acr_amt = $elem['lastAcrAmt'] ?? null;
            $acnt->last_accrual_date = formatDate($elem['lastAccrualDate'] ?? null);
            $acnt->save();
        }
    }

    /**
     * ME core Системийн дансны хүүний мэдээллийг шинэчлэх
     *
     * @param  mixed $acntCode
     * @param  mixed $instid
     * @return void
     */
    public function updateAcntIntCoreInfo($data, $acntCode, $instid, $acntType)
    {

        switch ($acntType) {
            // Шугамын зээл авахад ашиглана
            case 'LOAN_ACNT':

                ApAcntInt::where('instid', $instid)->where('acnt_code', $acntCode)->delete();
                if (empty($data)) {
                    return;
                }
                $user = auth()->user();
                // Зээлийн үндсэн хүү
                $acnt = new ApAcntInt(
                    [
                        'acnt_code' => $acntCode,
                        'instid' => $instid,
                        'statusid' => 1,
                        'created_at' => Carbon::now(),
                        'int_rate' => $data['intrate'],
                        'accr_int_amt' => $data['baseint2cap'] ?? null,
                        'int_type_name' => "Зээлийн үндсэн хүү",
                        'int_rate_option' => "FIXED",
                        'int_type_code' => 'SIMPLE_INT',
                        'daily_int_amt' => $data['baseintdaily'] ?? null,
                        'last_acr_amt' => $data['capbint'] ?? null,
                        'last_accrual_date' => formatDate($data['arreardateint'] ?? null),
                        'userid' => $user->id,
                        'created_by' => $user->id,
                    ],
                );
                $acnt->save();

                // Нэмэгдүүлсэн хүү
                $acnt = new ApAcntInt(
                    [
                        'acnt_code' => $acntCode,
                        'instid' => $instid,
                        'statusid' => 1,
                        'created_at' => Carbon::now(),
                        'int_rate' => $data['intratefine'],
                        'accr_int_amt' => $data['fineint2cap'] ?? null,
                        'int_type_name' => "Нэмэгдүүлсэн хүү",
                        'int_rate_option' => "FIXED",
                        'int_type_code' => 'FINE_INT',
                        'daily_int_amt' => $data['fineintdaily'] ?? null,
                        'last_acr_amt' => $data['capfint'] ?? null,
                        'last_accrual_date' => formatDate($data['finelastacrueddate'] ?? null),
                        'userid' => $user->id,
                        'created_by' => $user->id,
                    ],
                );
                $acnt->save();

                // Коммитмент хүү
                $acnt = new ApAcntInt(
                    [
                        'acnt_code' => $acntCode,
                        'instid' => $instid,
                        'statusid' => 1,
                        'created_at' => Carbon::now(),
                        'int_rate' => $data['intratecom'],
                        'accr_int_amt' => $data['comint2cap'] ?? null,
                        'int_type_name' => "Коммитмент хүү",
                        'int_rate_option' => "FIXED",
                        'int_type_code' => 'INT_COM',
                        'daily_int_amt' => $data['comintdaily'] ?? null,
                        'last_acr_amt' => $data['capcint'] ?? null,
                        'last_accrual_date' => formatDate($data['arreardatecom'] ?? null),
                        'userid' => $user->id,
                        'created_by' => $user->id,
                    ],
                );
                $acnt->save();
                break;
            // Хадгаламж барьцаалсан зээл дээр ашиглана
            case 'TD_ACNT':
                ApAcntInt::where('instid', $instid)->where('acnt_code', $acntCode)->delete();
                if (empty($data)) {
                    return;
                }
                $user = auth()->user();
                // Зээлийн үндсэн хүү
                $acnt = new ApAcntInt(
                    [
                        'acnt_code' => $acntCode,
                        'instid' => $instid,
                        'statusid' => 1,
                        'created_at' => Carbon::now(),
                        'int_rate' => $data['crintrate'],
                        'accr_int_amt' => $data['baseint2cap'] ?? null,
                        'int_type_name' => "Хадгаламжийн үндсэн хүү",
                        'int_rate_option' => "FIXED",
                        'int_type_code' => 'SIMPLE_INT',
                        'daily_int_amt' => $data['baseintdaily'] ?? null,
                        'last_acr_amt' => $data['capbint'] ?? null,
                        'last_accrual_date' => formatDate($data['arreardateint'] ?? null),
                        'userid' => $user->id,
                        'created_by' => $user->id,
                    ],
                );
                $acnt->save();

                break;
        }
    }

    /**
     * Системийн дансны зээлийн мэдээллийг авах
     *
     * @param  mixed $acnt
     * @param  mixed $instid
     * @return void
     */
    public function getInterAccountIntDetail($acntCode, $instid)
    {
        $intlist = ApAcntInt::where('instid', $instid)->where('acnt_code', $acntCode)->get();
        return new ApAccountIntCollection($intlist);
    }

    /**
     *
     * @param  mixed $acnt
     * @param  mixed $instid
     * @param  mixed $acntType TD_ACNT - Хадгаламж барьцаалсан зээл, LN_ACNT зээлийн шугам үед ашиглана
     * @return array
     */
    public function getAccountInt($acnt, $instid, $acntType)
    {
        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            $this->getAccountIntCore($acnt, $instid, $acntType);
        } else {
            $polaris = new PolarisApiRequestService($instid);
            $respdata = $polaris->sendRequest(13619995, [$acnt]);
            $this->updateAcntIntNesInfo($respdata, $acnt, $instid);
        }
        $respdata = $this->getInterAccountIntDetail($acnt, $instid);
        return $respdata;
    }

    /**
     * Барьцаа хөрөнгийн дансны дэлгэрэнгүй (Холбосон дансаар)
     *
     * @param  mixed $acnt Хадгаламжын дансны дугаар
     * @param  mixed $instid
     */
    public function getTdCollInfo($acnt, $instid)
    {
        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');
        // Log::debug($providertype);
        if ($providertype == 'MECORE') {
            $tdAcnt = ApAcntDp::where('acnt_code', $acnt)->where('userid', auth()->user()->id)->where('statusid', '>=', 0)->first();
            if ($tdAcnt) {
                $respdata = VwLnMorDetail::where('collacntno', $tdAcnt->acnt_code)->where('statusid', '>=', 0)->where('instid', $instid)->first();
                if ($respdata) {
                    $respdata['utilized'] = $respdata['obamount'];
                    return $respdata;
                } else {
                    return null;
                }
            } else {
                throw new MeException('Хадгаламжийн данс олдсонгүй.', 404);
            }
        } else {
            $tdAcnt = ApAcntDp::where('acnt_code', $acnt)->where('userid', auth()->user()->id)->first();
            if ($tdAcnt) {
                $polaris = new PolarisApiRequestService($instid);
                /**
                 *  $acnt - [Дансны дугаар, Нууцлалтай авах эсэх]
                 * */
                $respdata = $polaris->sendRequest(13610907, [1306, $acnt], $instid);

                $respdata = json_decode(json_encode($respdata['data']));
                return $respdata;
            } else {
                throw new MeException('Хадгаламжийн данс олдсонгүй.', 404);
            }
        }
    }

    public function createTd($data, $instid)
    {
        $stpservice = new ApStopService();
        $resp = $stpservice->checkStopSrevice([
            'instid' => $instid,
            'serviceCode' => '20000001',
            'prodCode' => $data['prodCode'],
        ]);

        if ($resp['status'] != 1) {
            return $resp;
        }
        $user = auth()->user();
        $cust = ApCustomer::where('instid', $instid)->where('regno', $user->regno)->first();
        if (!$cust) {
            throw new MeException('RC000197');
        }
        $polaris = new PolarisApiRequestService($instid);
        $sysDate = formatDate(Carbon::now());

        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');

        $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $instid]));

        $dicmain = GPInstConst::where('id', $data['prodid'])->where('instid', $instid)->first();

        if (empty($dicmain)) {
            throw new MeException('RC000198');
        }
        $termln = GPInstConst::where('parent_code', $dicmain->code)->where('value_add1', 'LENGTH')->where('instid', $instid)->first();

        if (!$termln) {
            throw new MeException('RC000199');
        }
        $data['termLen'] = $termln->value;

        $prodint = GPInstConst::where('parent_code', $dicmain->code)->where('value_add1', 'INT')->where('instid', $instid)->first();

        if (!$prodint) {
            throw new MeException('RC000200');
        }
        $data['prodInt'] = $prodint->value;
        $data['maturityDate']  = Carbon::createFromFormat('Y-m-d', $sysDate)->addMonths($data['termLen']);
        $acntname = ($cust->fname ?? '') . ' ' . ($cust->lname ?? '');
        $acntname2 = empty($cust->shortname2) ? $cust->fname2 : $cust->shortname2;

        if ($providertype == 'MECORE') {
            $dpprod = DpAccountType::where('prodcode', $data['prodCode'])
                ->where('instid', $instid)->first();
            switch ($dpprod->termbasis) {
                case 'D':
                    $rawTerm = $data['termLen'] * 30.44; // 30.44 бол 4 жилийн дундаж сарын өдрийн тоо
                    $data['termLen'] = floor($rawTerm) + ((fmod($rawTerm, 1) > 0.5) ? 1 : 0);
                    break;
                case 'M':
                    $data['termLen'] = $data['termLen'];
                    break;
                case 'Y':
                    $data['termLen'] = $data['termLen'] / 12;
                    break;

                default:
                    # code...
                    break;
            }
            // Зээлийн данс олголт хийх
            $requestData = [
                "brchno" => $polaris->brchCode ?? '',
                "termcyclecount" => 0,
                "openeddate" => $sysDate,
                "crcapmethod" => 1,
                "custno" => $cust->cif,
                "segcode" => $cust->segment,
                "prodcode" => $data['prodCode'] ?? '',
                "crintrateacnt" => 0,
                "curcode" => $dpprod->curcode,
                "crintrate" =>  $data['prodInt'] * 12,
                "termbasis" => $dpprod->termbasis,
                "name" => $acntname,
                "name2" =>  $acntname2 ?? '',
                "autocont" => 0,
                "termnextprodcode" => $data['prodCode'] ?? '',
                "termnextcrcapmethod" => 1,
                "termnextcrintrate" =>  $data['prodInt'] * 12,
                "termnextcrintrateacnt" => 0,
                "termnextlen" => 1,
                "nexttermbasis" => $dpprod->termbasis,
                "nextcurcode" => $dpprod->curcode,
                "termlen" => $data['termLen'] ?? '',
                "termstartdate" => CoreService::getTxnDate($instid),
                "termexpdate" =>  $data['maturityDate'],
                "useratetier" => 0,
                "ratetierdatas" => [],
                "sourcecode" => 2,
                "catcode" => $data['goal']
            ];

            $process = GpctionCode::where('ACTION_CODE', 'dp010200')->first();
            $route = $process->controller . '@' . $process->function;
            request()->merge($requestData);
            $tmpuser = auth()->user();
            $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');
            $user = GPInstUser::where('instid', $data['instid'])->find(
                $onlineteller
            );
            if (empty($user)) {
                throw new MeException('RC000201');
            }
            Auth::setUser($user);
            $respdata = App::call($route);
            $acntno = $respdata;

            $promo = GPInstConst::where('parent_code', $dicmain->code)
                ->where('value_add1', 'PROMOTIONAL')
                ->where('instid', $instid)
                ->first();

            if ($promo && ($promo->value ?? null) === 'SAVING' && !empty($acntno)) {
                try {
                    $process = GpctionCode::where('ACTION_CODE', 'dp010301')->first();

                    if (empty($process)) {
                        Log::warning('Process code dp010301 not found', [
                            'instid' => $instid,
                            'acntno' => $acntno
                        ]);
                    } else {
                        $route = $process->controller . '@' . $process->function;

                        $addData = [
                            $promo->value_add2 => $data['amount'] ?? 0,
                            'acntno' => $acntno
                        ];
                        request()->merge($addData);

                        $resadddata = App::call($route);
                    }
                } catch (Exception $ex) {
                    Log::error('Failed to update promotional saving account', [
                        'acntno' => $acntno,
                        'amount' => $data['amount'] ?? 0,
                        'error' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString()
                    ]);
                }
            }


            if ($acntno) {
                $coredata = VwCrCustAllAcntWithBalance::where('instid', $instid)
                    ->where('acntno', $acntno)->first();

                if ($coredata) {
                    $respdata = [
                        'sysNo' => $coredata->sys_no,
                        'acntName' => $coredata->name,
                        'acntCode' => $coredata->acntno,
                        'isSecure' => $coredata->is_secure,
                        'custCode' => $coredata->custno,
                        'custName' => $coredata->custname,
                        'custName2' => $coredata->custname2,
                        'prodCode' => $coredata->prodcode,
                        'availBalance' => $coredata->balance,
                        'balance' => $coredata->balance,
                        'isAllowPartialLiq' => 1,
                        'acntType' => $coredata->acntmode,
                        // 'acntType' => ApAccountTypeEnum::fromString(Str::lower($coredata->acntmode)),
                        'prodName' => $coredata->prod_name,
                        'curCode' => $coredata->curcode,
                        'status' => $coredata->statusid,
                        'instid' => $coredata->instid
                    ];

                    $casaService = new ApCasaAcntService();
                    $casaService->createAccountNes($respdata, $instid, $user);
                }

                Auth::setUser(ApCustUser::find($tmpuser->id));
                $txnjrno = '000000';

                $cservice = new ApContractService();
                $cservice->storeCustContract([
                    'account_no' => $acntno,
                    'acnt_code' => $acntno,
                    'prod_code' => $data['prodCode'],
                    'operation' => 'dp010200',
                    'txn_jrno' => $txnjrno,
                    'cust_cif' => $cust->cif,
                    'cust_name' => $cust->fname,
                    'instid' => $instid,
                    'amount' => $data['amount'] ?? 0,
                    'type_id' => ApAccountTypeEnum::td,
                    'sign_image_id' => $data['sign_image_id'],
                ], 20000001, null);
            }
            return ['acntCode' => $acntno];
        } else {
            /**
             *  $acnt - []
             * */
            $acnt = $polaris->sendRequest(13610120, [[
                'prodCode' => $data['prodCode'] ?? '',
                'slevel' => 1,
                'capMethod' => 0,
                'startDate' => $sysDate,
                'maturityOption' => 'R',
                'rcvAcntCode' => $data['rcvAcntCode'] ?? '',
                'brchCode' => $polaris->brchCode ?? '',
                'curCode' => 'MNT',
                'name' => $acntname,
                'name2' => $acntname2 ?? '',
                'termLen' => $data['termLen'] ?? '',
                'maturityDate' => formatDate($data['maturityDate'] ?? '', false),
                'custCode' => $cust->cif,
                'segCode' => $cust->segment,
                'jointOrSingle' => 'S',
                'statusCustom' => '',
                'statusDate' => '',
                'casaAcntCode' => '',
                'closedBy' => '',
                'closedDate' => '',
                'lastCtDate' => '',
                'lastDtDate' => '',
            ]], $instid);


            $polaris = new PolarisApiRequestService($instid);
            $respdata = $polaris->sendRequest(13610100, [$acnt, 0], $instid);

            $txnjrno = '000000';

            $txnjrno = $polaris->sendRequest(13610122, [$acnt, "Дансны төлөв нээх хүсэлт"], $instid);
            $this->getAccountDetailPolaris(13610100, $acnt, $instid, 'TD');

            $cservice = new ApContractService();
            $cservice->storeCustContract([
                'account_no' => $acnt,
                'acnt_code' => $acnt,
                'prod_code' => $data['prodCode'],
                'operation' => '13610120',
                'txn_jrno' => $txnjrno,
                'cust_cif' => $cust->cif,
                'cust_name' => $cust->fname,
                'instid' => $instid,
                'amount' => $data['amount'] ?? 0,
                'type_id' => ApAccountTypeEnum::td,
                'sign_image_id' => $data['sign_image_id'],
            ], 20000001, null);

            return $respdata;
        }
    }

    public function initTd($data, $instid)
    {
        $user = auth()->user();
        $cust = ApCustomer::where('instid', $instid)->where('regno', $user->regno)->first();
        if (!$cust) {
            throw new MeException('RC000197');
        }

        $dicmain = GPInstConst::where('id', $data['prodid'])->where('instid', $instid)->first();

        if (empty($dicmain)) {
            throw new MeException('RC000198');
        }
        $termln = GPInstConst::where('parent_code', $dicmain->code)->where('value_add1', 'LENGTH')->where('instid', $instid)->first();

        if (!$termln) {
            throw new MeException('RC000199');
        }

        $prodint = GPInstConst::where('parent_code', $dicmain->code)->where('value_add1', 'INT')->where('instid', $instid)->first();

        if (!$prodint) {
            throw new MeException('RC000200');
        }

        $data['termLen'] = $termln->value * 1;
        $data['prodInt'] = $prodint->value * 1;

        $polaris = new PolarisApiRequestService($instid);
        $sysDate = formatDate(Carbon::now());

        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');

        if ($providertype == 'MECORE') {
            $sysDate = CoreService::getTxnDate($instid);
        } else {
            $sysDate = $polaris->getDate($instid);
        }

        $maturityDate  = Carbon::createFromFormat('Y-m-d', $sysDate)->addMonths($data['termLen']);
        $yearint = 12 * $data['prodInt'];
        // Өдрийн хүү
        $data['dayInt'] = round($data['amount'] * $yearint / 100 / $polaris->savingTd->dailyBasisCode, 2);
        // Нийт хүүний дүн
        $data['days'] = $maturityDate->diffInDays($sysDate) - 1;
        $data['allAmountInt'] = round($data['days'] * $data['dayInt'], 2);
        $data['maturityDate']  = $maturityDate->format('Y-m-d');
        return $data;
    }

    public function tdQpayTransaction($data)
    {
        $GPInstGp =  GPInstGp::where('instid', $data['instid'])->where('itemname', 'SYSTEMTELLERNUMBER')->first();

        if (isset($GPInstGp)) {
            $userid = $GPInstGp->itemvalue;
        } else {
            $userid = 1;
        }

        $user = GPInstUser::find($userid);
        Auth::setUser($user);

        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }

        $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');
        $cust = $data['cust'];

        // Шимтгэлийн гүйлгээний мэдээлэл үүсгэх
        $lnService = new ApTxnJournal();
        $lnService->tcust_name = $cust->fname;
        $lnService->tcust_addr = $cust->address ?? "";
        $lnService->tcust_register = $cust->regno;
        $lnService->tcust_register_mask = $cust->register_mask_code;
        $lnService->tcust_contact = $cust->phone ?? '';
        $lnService->txn_acnt_code = $data['txnAcntCode'];
        $lnService->cur_code = 'MNT';
        $lnService->identity_type = "MANUAL";
        $lnService->rate = 1;
        $lnService->cont_amount = $data['txnAmount'];
        $lnService->txn_amount = $data['txnAmount'];
        $lnService->cont_cur_code = 'MNT';
        $lnService->cont_rate = 1;
        $lnService->txn_desc = $data['txnDesc'];
        $lnService->source_type = "OI";
        $lnService->is_tmw = 1;
        $lnService->is_preview = 0;
        $lnService->is_preview_fee = 0;
        $lnService->cont_acnt_code = $data['contAcntCode'];
        // $lnService->cont_bank_code = $data['contBankCode'];
        $lnService->created_at = Carbon::now();
        $lnService->userid = $data['userid'] ?? 1;
        $lnService->created_by = $onlineteller ?? 1;
        $lnService->statusid = 0;
        $lnService->txn_type = 1;
        $lnService->txn_date = $data['txn_date'] ?? '';
        $lnService->prodcode = $data['prodcode'];

        $providertype = CoreService::getInstGp($data['instid'], 'MEAPPPROVIDER');

        $lnService->oper_code = $providertype == "MECORE" ? "ia903021" : "13610022";
        $lnService->instid = $data['instid'];
        $lnService->save();

        if ($providertype == "MECORE") {
            $req_data = [
                "acntno" => $data['txnAcntCode'],
                "curcode" => $data['curCode'],
                "rate" => $data['rate'],
                "rtypecode" => "1",
                "contacntno" => $data['contAcntCode'],
                "txnamount" => $data['txnAmount'],
                "txndesc" => $data['txnDesc'],
                "ispreview" => 0
            ];

            try {
                $process = GpctionCode::where('ACTION_CODE', 'ia903021')->first();
                $route = $process->controller . '@' . $process->function;
                request()->merge($req_data);

                $respdata = App::call($route);

                $lnService->statusid = 1;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->is_supervisor = $respdata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $respdata['jrItemNoAndIncr'] ?? 0;
                $lnService->err_desc = "";
                $lnService->save();
                return $respdata;
            } catch (Exception $ex) {
                Log::error($ex);
                throw $ex;
            }
        } else {
            $req_data = [
                [
                    "txnAcntCode" => $data['txnAcntCode'],
                    "txnAmount" => $data['txnAmount'],
                    "rate" => $data['rate'],
                    "contAcntCode" => $data['contAcntCode'],
                    "contAmount" => $data['contAmount'],
                    "contRate" => $data['contRate'],
                    "txnDesc" => $data['txnDesc'],
                    "txnDefCode" => null,
                    "sourceType" => "OI",
                    "isPreview" => 0,
                    "isPreviewFee" => null,
                    "isTmw" => 1,
                    "operCode" => 13610022
                ]
            ];

            try {
                $polaris = new PolarisApiRequestService($data['instid']);
                $respdata = $polaris->sendRequest($lnService->oper_code, $req_data, $data['instid']);

                $lnService->statusid = 1;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->is_supervisor = $respdata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $respdata['jrItemNoAndIncr'] ?? 0;
                $lnService->err_desc = "";
                $lnService->save();

                return $respdata;
            } catch (Exception $ex) {
                Log::error($ex);
                throw $ex;
            }
        }
    }

    public function CasaQpayTransaction($data)
    {
        // $apuser = auth()->user();
        $GPInstGp =  GPInstGp::where('instid', $data['instid'])->where('itemname', 'SYSTEMTELLERNUMBER')->first();

        if (isset($GPInstGp)) {
            $userid = $GPInstGp->itemvalue;
        } else {
            $userid = 1;
        }

        $user = GPInstUser::find($userid);
        Auth::setUser($user);

        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }

        $cust = $data['cust'];

        $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');
        // Шимтгэлийн гүйлгээний мэдээлэл үүсгэх
        $lnService = new ApTxnJournal();
        $lnService->tcust_name = $cust->fname;
        $lnService->tcust_addr = $cust->address ?? "";
        $lnService->tcust_register = $cust->regno;
        $lnService->tcust_register_mask = $cust->register_mask_code;
        $lnService->tcust_contact = $cust->phone ?? '';
        $lnService->txn_acnt_code = $data['txnAcntCode'];
        $lnService->cur_code = 'MNT';
        $lnService->identity_type = "MANUAL";
        $lnService->rate = 1;
        $lnService->cont_amount = $data['txnAmount'];
        $lnService->txn_amount = $data['txnAmount'];
        $lnService->cont_cur_code = 'MNT';
        $lnService->cont_rate = 1;
        $lnService->txn_desc = $data['txnDesc'];
        $lnService->source_type = "OI";
        $lnService->is_tmw = 1;
        $lnService->is_preview = 0;
        $lnService->is_preview_fee = 0;
        $lnService->cont_acnt_code = $data['contAcntCode'];
        // $lnService->cont_bank_code = $data['contBankCode'];
        $lnService->created_at = Carbon::now();
        $lnService->userid = $data['userid'] ?? 1;
        $lnService->created_by = $onlineteller ?? 1;
        $lnService->statusid = 0;
        $lnService->txn_type = 1;
        $lnService->txn_date = $data['txn_date'] ?? '';
        $lnService->prodcode = $data['prodcode'];

        $providertype = CoreService::getInstGp($data['instid'], 'MEAPPPROVIDER');

        $lnService->oper_code = $providertype == "MECORE" ? "ia903021" : "13610651";

        $lnService->instid = $data['instid'];
        $lnService->save();

        if ($providertype == "MECORE") {
            $req_data = [
                "acntno" => $data['txnAcntCode'],
                "curcode" => $data['curCode'],
                "rate" => $data['rate'],
                "rtypecode" => "1",
                "contacntno" => $data['contAcntCode'],
                "txnamount" => $data['txnAmount'],
                "txndesc" => $data['txnDesc'],
                "ispreview" => 0
            ];

            $process = GpctionCode::where('ACTION_CODE', 'ia903021')->first();
            $route = $process->controller . '@' . $process->function;
            request()->merge($req_data);

            try {
                $respdata = App::call($route);

                $lnService->statusid = 1;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->err_desc = "";
                $lnService->save();
            } catch (Exception $ex) {
                Log::error($ex);
                throw $ex;
            }

            return $respdata;
        } else {
            $req_data = [
                [
                    "txnAcntCode" => $data['txnAcntCode'],
                    "txnAmount" => $data['txnAmount'],
                    "rate" => $data['rate'],
                    "contAcntCode" => $data['contAcntCode'],
                    "contAmount" => $data['contAmount'],
                    "contRate" => $data['contRate'],
                    "txnDesc" => $data['txnDesc'],
                    "txnDefCode" => null,
                    "sourceType" => "OI",
                    "isPreview" => 0,
                    "isPreviewFee" => null,
                    "isTmw" => 1
                ]
            ];
            try {
                $polaris = new PolarisApiRequestService($data['instid']);
                $respdata = $polaris->sendRequest($lnService->oper_code, $req_data, $data['instid']);

                $lnService->statusid = 1;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->is_supervisor = $respdata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $respdata['jrItemNoAndIncr'] ?? 0;
                $lnService->err_desc = "";
                $lnService->save();
            } catch (Exception $ex) {
                Log::error($ex);
                throw $ex;
            }

            return $respdata;
        }
    }

    public function internalTocasaTran($data)
    {
        $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');

        $user = GPInstUser::where('instid', $data['instid'])->find(
            $onlineteller
        );
        Auth::setUser($user);

        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }

        // Шимтгэлийн гүйлгээний мэдээлэл үүсгэх
        $lnService = new ApTxnJournal();
        $lnService->txn_acnt_code = $data['txnAcntCode'];
        $lnService->cur_code = 'MNT';
        $lnService->txn_amount = $data['txnAmount'];
        $lnService->identity_type = "MANUAL";
        $lnService->rate = 1;
        $lnService->cont_amount = $data['txnAmount'];
        $lnService->cont_cur_code = 'MNT';
        $lnService->cont_rate = 1;
        $lnService->txn_desc = $data['txnDesc'];
        $lnService->source_type = "OI";
        $lnService->is_tmw = 1;
        $lnService->is_preview = 0;
        $lnService->is_preview_fee = 0;
        $lnService->cont_acnt_code = $data['contAcntCode'];
        // $lnService->cont_bank_code = $data['contBankCode'];
        $lnService->created_at = Carbon::now();
        $lnService->userid = $data['userid'] ?? 1;
        $lnService->created_by = $onlineteller ?? 1;
        $lnService->statusid = 0;
        $lnService->txn_type = 1;
        $lnService->txn_date = $data['txn_date'] ?? '';
        $lnService->instid = $data['instid'];
        $lnService->prodcode = $data['prodcode'];

        $providertype = CoreService::getInstGp($data['instid'], 'MEAPPPROVIDER');

        $lnService->oper_code = $providertype == "MECORE" ? "ia903021" : "13610651";
        $lnService->save();

        if ($providertype == "MECORE") {
            $req_data = [
                "acntno" => $data['txnAcntCode'],
                "curcode" => $data['curCode'],
                "rate" => $data['rate'],
                "rtypecode" => "1",
                "contacntno" => $data['contAcntCode'],
                "txnamount" => $data['txnAmount'],
                "txndesc" => $data['txnDesc'],
                "ispreview" => 0
            ];

            $process = GpctionCode::where('ACTION_CODE', 'ia903021')->first();
            $route = $process->controller . '@' . $process->function;
            request()->merge($req_data);

            try {
                $respdata = App::call($route);

                $lnService->statusid = 1;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->err_desc = "";
                $lnService->save();
            } catch (Exception $ex) {
                Log::error($ex);
                throw $ex;
            }

            Auth::setUser(ApCustUser::find($data['userid']));
            return $respdata;
        } else {
            $req_data = [
                [
                    "txnAcntCode" => $data['txnAcntCode'],
                    "txnAmount" => $data['txnAmount'],
                    "rate" => $data['rate'],
                    "contAcntCode" => $data['contAcntCode'],
                    "contAmount" => $data['contAmount'],
                    "contRate" => $data['contRate'],
                    "txnDesc" => $data['txnDesc'],
                    "txnDefCode" => null,
                    "sourceType" => "OI",
                    "isPreview" => 0,
                    "isPreviewFee" => null,
                    "isTmw" => 1
                ]
            ];

            try {
                $polaris = new PolarisApiRequestService($data['instid']);
                $respdata = $polaris->sendRequest($lnService->oper_code, $req_data, $data['instid']);

                $lnService->statusid = 2;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->is_supervisor = $respdata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $respdata['jrItemNoAndIncr'] ?? 0;
                $lnService->err_desc = "";
                $lnService->save();
            } catch (Exception $ex) {
                Log::error($ex);
                throw $ex;
            }
            Auth::setUser(ApCustUser::find($data['userid']));
            return $respdata;
        }
    }

    /**
     * Бүтээгдэхүүнээр хувь нийлүүлсэн эсэхийг шалгах
     *
     * @param  mixed $productno -  дугаар
     * @return boolean
     */
    public function isJs($productno)
    {
        $jses = GPInstConst::where('parent_code', 'JS_PRODCODE')
            ->where('statusid', '<>', '-1')->get();
        foreach ($jses as $js) {
            if ($js->value == $productno) {
                return true;
            }
        }
        return false;
    }
    public function generateDepositCertPdf($accountDetail, $user, $instid, $acntCode, $isDownload = false): string
    {
        ini_set('memory_limit', '256M');

        $cust = ApCustomer::where('instid', $instid)
            ->where('regno', $user->regno)
            ->where('statusid', 1)
            ->first();

        if (!$cust) {
            throw new MeException('RC000176');
        }

        $configcontract = GPInstConst::where('parent_code', 'USER_ACNT_CERT')
            ->where('instid', $instid)
            ->where('statusid', 1)
            ->first();

        if (empty($configcontract) || empty($configcontract->value_add1)) {
            throw new MeException('RC000180');
        }

        $template = ReInstReportTemp::where('statusid', 1)
            ->whereIn('ACTION_CODE', function ($query) use ($instid) {
                $query->select('AC')
                    ->from(with(new GPInstPerm())->getTable())
                    ->where('instid', $instid)
                    ->where('statusid', '<>', -1);
            })
            ->where('ACTION_CODE', $configcontract->value_add1)
            ->first();

        if (!$template) {
            throw new MeException('RC000180');
        }

        $templateContent = ReInstReportTempContent::where('templateid', $template->id)
            ->where('statusid', 1)
            ->first();

        if (!$templateContent) {
            throw new MeException('RC000180');
        }

        $inst = GPInstList::select('id', 'name', 'name2', 'logo')->find($instid);
        $instLogo = '';
        if ($inst && $inst->logo) {
            $row = GPPhoto::where('id', $inst->logo)->first();
            if ($row && $row->photo) {
                $raw = is_resource($row->photo) ? stream_get_contents($row->photo) : $row->photo;
                $instLogo = "data:image/png;base64," . self::resizeImageBase64(base64_decode($raw));
            }
        }

        $now       = Carbon::now();
        $printDate = $now->format('Y-m-d');

        $qrEncodeStr = json_encode([
            'Данс'        => $acntCode,
            'Байгууллага' => $inst->name ?? '',
            'Огноо'       => $printDate,
        ], JSON_UNESCAPED_UNICODE);

        $writer   = new PngWriter();
        $qrCode   = QrCode::create($qrEncodeStr);
        $result   = $writer->write($qrCode);
        $qrBase64 = 'data:image/png;base64,' . base64_encode($result->getString());
        unset($result, $qrCode, $writer);

        $balance      = $accountDetail['avail_bal'] ?? $accountDetail['current_bal'] ?? 0;
        $maturityDate = $accountDetail['maturity_date'] ?? '';
        $intRate      = $accountDetail['int_rate'] ?? 0;
        $prodName     = $accountDetail['prod_name'] ?? '';
        $textBalance  = numbertotext($balance, 2);

        $searchVal = [
            '${year}',
            '${month}',
            '${day}',
            '${print_date}',
            '${account}',
            '${register}',
            '${last_name}',
            '${first_name}',
            '${prod_name}',
            '${balance}',
            '${text_balance}',
            '${int_rate}',
            '${maturity_date}',
            '${inst_name}',
            '${inst_logo}',
            '${qr_code}',
        ];

        $replaceVal = [
            $now->format('Y'),
            $now->format('m'),
            $now->format('d'),
            $printDate,
            $acntCode,
            $cust->regno,
            $cust->lname,
            $cust->fname,
            $prodName,
            number_format($balance, 2),
            $textBalance,
            number_format($intRate, 2),
            substr($maturityDate, 0, 10),
            $inst->name ?? '',
            $instLogo,
            $qrBase64,
        ];

        $html = str_replace($searchVal, $replaceVal, $templateContent->source);

        unset($qrBase64, $instLogo);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', storage_path());
        $options->set('chroot', [public_path(), storage_path()]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        unset($html);

        $fileName    = 'customer_account_cert_' . $acntCode . '_' . now()->format('Ymd_His') . '.pdf';
        $storagePath = 'customer_account_certs/' . $fileName;

        Storage::disk('public')->put($storagePath, $dompdf->output());
        unset($dompdf);
        return url('api/pdf/account/' . $fileName);
    }

    private static function resizeImageBase64(string $rawImage, int $maxDim = 200): string
    {
        $img = @imagecreatefromstring($rawImage);
        if ($img === false) return base64_encode($rawImage);

        $origW = imagesx($img);
        $origH = imagesy($img);

        if ($origW > $maxDim || $origH > $maxDim) {
            $ratio   = min($maxDim / $origW, $maxDim / $origH);
            $resized = imagescale($img, (int)($origW * $ratio), (int)($origH * $ratio));
            ob_start();
            imagepng($resized, null, 6);
            $rawImage = ob_get_clean();
            imagedestroy($resized);
        }

        imagedestroy($img);
        return base64_encode($rawImage);
    }
}
