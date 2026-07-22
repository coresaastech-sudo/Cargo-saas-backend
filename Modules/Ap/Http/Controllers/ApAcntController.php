<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\PolarisApiRequestService;
use Modules\Cr\Entities\Views\VwCrCustAllAcntWithBalance;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Ap\Entities\ApAcntSchedule;
use Modules\Ap\Http\Services\ApAuthService;
use Modules\Ap\Transformers\ApAccountStatementCollection;
use Modules\Ap\Transformers\ApAcntScheduleCollection;
use Modules\Cr\Entities\Views\VwCrCustAllAcntList;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ln\Entities\Views\VwLnNrs;
use Modules\Tr\Entities\DpTxn;
use Modules\Tr\Entities\LnTxn;
use Illuminate\Support\Str;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;

class ApAcntController extends Controller
{
    /**
     * oi000250 Дансны жагсаалт авах (Inst)
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000250(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required',
            'isAll' => 'nullable|boolean',
        ]);
        if (empty($validated['isAll'])) {
            $validated['isAll'] = false;
        }
        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $validated['instid'])
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }
        $cust = ApCustomer::select('cif')->where('instid', $validated['instid'])
            ->where('regno', $user->regno)->first();
        if (!$cust) {
            $this->error('RC000015');
        }

        $providertype = CoreService::getInstGp($validated['instid'], 'MEAPPPROVIDER');
        $custCode = $cust->cif;
        $instid = $validated['instid'];
        $isAll = $validated['isAll'];

        if ($providertype == 'MECORE') {
            $coredatas = VwCrCustAllAcntWithBalance::where('instid', $instid)
                ->where('custno', $custCode)->get();
            $respdata = [];
            foreach ($coredatas as $key => $coredata) {
                $respdata[] = [
                    'sysNo' => $coredata->sys_no,
                    'acntName' => $coredata->name,
                    'acntName2' => $coredata->name2,
                    'acntCode' => $coredata->acntno,
                    'isSecure' => $coredata->is_secure,
                    'custCode' => $coredata->custno,
                    'prodCode' => $coredata->prodcode,
                    'availBalance' => $coredata->balance,
                    'balance' => $coredata->balance,
                    'isAllowPartialLiq' => 1,
                    'acntType' => $coredata->acntmode,
                    // 'acntType' => ApAccountTypeEnum::fromString(Str::lower($coredata->acntmode)),
                    'prodName' => $coredata->prod_name,
                    'prodName2' => $coredata->prod_name2,
                    'curCode' => $coredata->curcode,
                    'status' => $coredata->statusid,
                    'instid' => $coredata->instid
                ];
            }
            // $respdata = json_decode(json_encode($respdata));
        } else {
            $respdata = $this->getNesCustAccounts($custCode, $instid);
        }

        $casa = new ApAcntService();
        $ownaccounts = $casa->getAccountsNotCollection([], $instid, $isAll);
        $tmpacnts = [];
        foreach ($ownaccounts as $ownaccount) {
            $isinclude = false;
            foreach ($respdata as $nesdata) {
                if (
                    $ownaccount['acntCode'] == @$nesdata['acntCode']
                ) {
                    $isinclude = true;
                }
            }
            if (!$isinclude) {
                $ownaccount['status'] = 'C';
                $tmpacnts[] = $ownaccount;
            }
        }

        $respdata = array_merge($respdata, $tmpacnts);
        $respdata = $casa->createCasaAcntList($respdata, [], $instid);
        return $casa->getAccounts([], $instid, $isAll);
        // $data = $this->service->getCustAccounts($cust->cif, $validated['instid'],  $validated['isAll']);
        // return response()->json($data['data']);
    }

    public function getNesCustAccounts($custCode, $instid)
    {
        $polaris = new PolarisApiRequestService($instid);
        return $polaris->sendRequest(13610312, [$custCode, 0, -1], $instid);
    }

    /**
     * oi000260 Дансны бүх жагсаалт авах
     *
     * @param  mixed $request
     */
    public function oi000260(Request $request)
    {

        $user = auth()->user();

        $connInsts = ApInstCustUserLink::where('cust_userid', $user->id)->where('statusid', 1)->get();

        $allAccount = collect();

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        if ($app->app_identifier != 'MeApp') {
            $request->merge([
                'instid' => $app->instid,
                'isAll' => false,
            ]);

            try {
                $accounts = $this->oi000250($request);

                if ($accounts) {
                    $allAccount = $allAccount->merge($accounts);
                }
                return $allAccount;
            } catch (Exception $ex) {
                Log::debug($ex);
            }
        } else {
            foreach ($connInsts as $inst) {
                $request->merge([
                    'instid' => $inst->instid,
                    'isAll' => false,
                ]);
                try {
                    $accounts = $this->oi000250($request);

                    if ($accounts) {
                        $allAccount = $allAccount->merge($accounts);
                    }
                } catch (Exception $ex) {
                    Log::debug($ex);
                }
            }

            return $allAccount;
        }
    }

    /**
     * oi000470 Дансны хуулга
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000470(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'instid' => 'required|numeric',
            'startPosition' => 'required|numeric',
            'count' => 'required|numeric',
        ]);

        $acntService = new ApAcntService();

        $acntService->checkDates($validated['startDate'], $validated['endDate']);

        $user = auth()->user();
        $connInst = ApInstCustUserLink::where('instid', $validated['instid'])->where('cust_userid', $user->id)->where('statusid', 1)->first();
        if (!$connInst) {
            throw new MeException("RC000184");
        }

        $sendData = [
            'acntCode' => $validated['acntCode'],
            'startDate' => $validated['startDate'],
            'endDate' => $validated['endDate'],
            'orderBy' => 'desc',
            'seeNotFinancial' => 0,
            'seeCorr' => 0,
            'seeReverse' => 0,
            'startPosition' => $validated['startPosition'],
            'count' => $validated['count']
        ];
        $providertype = CoreService::getInstGp($validated['instid'], 'MEAPPPROVIDER');
        $acntCode = $validated['acntCode'];
        $instid = $validated['instid'];

        if ($providertype == 'MECORE') {
            $acnt = VwCrCustAllAcntList::select('acntno', 'acntmode')->where('acntno', $acntCode)->where('instid', $instid)->first();

            $respdata = [];
            if (isset($acnt)) {
                if (Str::upper($acnt->acntmode) == "LN") {
                    $coredatas = LnTxn::where('instid', $instid)
                        ->where('acntno', $acntCode)->orderBy('txndate', 'desc')
                        ->orderBy('jrno', 'desc')
                        ->whereBetween('txndate', [$validated['startDate'], $validated['endDate']])
                        ->skip($validated['startPosition'])->take($validated['count'])->get();

                    foreach ($coredatas as $key => $coredata) {
                        $respdata[] = [
                            'contCurRate' => $coredata->contcurrate,
                            'income' => $coredata->txntype == 1 ? $coredata->txnamount : 0,
                            'jrno' => $coredata->jrno,
                            'beginBal' => $coredata->begin_bal,  // ?
                            'endBal' => $coredata->acntbal,
                            'txnDate' => $coredata->txndate,
                            'txnCode' => $coredata->txncode,
                            'balTypeCode' => $coredata->bal_type_code, // ?
                            'outcome' => $coredata->txntype == 0 ? $coredata->txnamount : 0,
                            'balance' => $coredata->acntbal,
                            'txnDesc' => $coredata->txndesc,
                            'contAcntCode' => $coredata->contacntno,
                            'contBankAcntCode' => $coredata->cont_bank_acnt_code, // ?
                            'contBankAcntName' => $coredata->cont_bank_acnt_name, // ?
                            'contBankCode' => $coredata->cont_bank_code, // ?
                            'contBankName' => $coredata->cont_bank_name, // ?
                            'postDate' => formatDate($coredata->postdate),
                        ];
                    }
                } else if (Str::upper($acnt->acntmode) == "DP") {
                    $coredatas = DpTxn::where('instid', $instid)
                        ->where('acntno', $acntCode)->get();

                    foreach ($coredatas as $key => $coredata) {
                        $respdata[] = [
                            'contCurRate' => $coredata->contcurrate,
                            'income' => $coredata->txntype == 1 ? $coredata->txnamount : 0,
                            'jrno' => $coredata->jrno,
                            'beginBal' => $coredata->begin_bal,  // ?
                            'endBal' => $coredata->acntbal,
                            'txnDate' => $coredata->txndate,
                            'txnCode' => $coredata->txncode,
                            'balTypeCode' => $coredata->bal_type_code,
                            'outcome' => $coredata->txntype == 0 ? $coredata->txnamount : 0,
                            'balance' => $coredata->acntbal,
                            'txnDesc' => $coredata->txndesc,
                            'contAcntCode' => $coredata->contacntno,
                            'contBankAcntCode' => $coredata->cont_bank_acnt_code,
                            'contBankAcntName' => $coredata->cont_bank_acnt_name,
                            'contBankCode' => $coredata->cont_bank_code,
                            'contBankName' => $coredata->cont_bank_name,
                            'postDate' => formatDate($coredata->postdate),
                        ];
                    }
                }

                $acntService->createAcntStatement($respdata, $instid, $acntCode);
                $respdata['data'] = $acntService->getStatements($validated, $instid);
            } else {
                throw new MeException('RC000034', ['mainacntno' => $acntCode]);
            }
        } else {
            $respdata['data'] = $acntService->getAccountStatement($sendData, $validated['instid']);
        }
        return $respdata['data'];
    }

    /**
     * oi000270 Эргэн төлөлтийн хуваарь
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000270(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric',
        ]);

        $user = auth()->user();

        $acntCode = $validated['acntCode'];
        $instid = $validated['instid'];

        $providertype = CoreService::getInstGp($validated['instid'], 'MEAPPPROVIDER');

        if ($providertype == 'MECORE') {
            $coredatas = VwLnNrs::where('instid', $instid)
                ->where('acntno', $acntCode)->get();

            $respdata = [];
            foreach ($coredatas as $key => $coredata) {
                $respdata[] = [
                    'schdDate' => $coredata->payday,
                    'amount' => $coredata->baseamount,
                    'intAmount' => $coredata->intamount,
                    'totalAmount' => $coredata->payamount,
                    'theorBal' => $coredata->theorbal,
                ];
            }
            // $respdata = json_decode(json_encode($respdata));
        } else {
            $respdata = $this->getNesRepaymentSchedule($acntCode, $instid);
        }

        return $respdata;
    }

    public function getNesRepaymentSchedule($acnt, $instid)
    {
        $polaris = new PolarisApiRequestService($instid);
        try {
            $respdata = $polaris->sendRequest(13610203, [$acnt], $instid);
            $this->createRepaymentSchedule($respdata, $instid, $acnt);
        } catch (Exception $ex) {
            Log::error($ex);
            $respdata['data'] = $this->getRepaymentSchedules($acnt, $instid);
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
            $schdl->created_by = auth()->user()->id;
            $schdl->save();
        }
    }

    public function getRepaymentSchedules($acnt_code, $instid)
    {
        return new ApAcntScheduleCollection(ApAcntSchedule::where('instid', $instid)->where('acnt_code', $acnt_code)->get());
    }

    /**
     * Холбосон данс харах
     *
     * @return array
     */
    public function oi000320()
    {
        $user = auth()->user();
        return ApCustBankAccount::where('cust_user_id', $user->id)
            ->where('statusid', '>', 0)->get();
    }

    /**
     * "Данс холбох"
     *
     * @return array
     */
    public function oi000321(Request $request)
    {
        $data = $this->validate($request, [
            'acnt_code' => 'required',
            'acnt_name' => 'required',
            'bank_code' => 'required',
        ], [
            'acnt_code.required' => ResponseCodeEnum::required,
            'acnt_name.required' => ResponseCodeEnum::required,
            'bank_code.required' => ResponseCodeEnum::required,
        ]);
        $data['token'] = rand(100000, 999999);

        $user = auth()->user();
        $acnt = ApCustBankAccount::where('cust_user_id', $user->id)
            ->where('acnt_code', $data['acnt_code'])
            ->where('statusid', '>', 0)->first();

        if ($acnt) {
            $this->error('RC000028');
        }
        $custAccount = new ApCustBankAccount();

        $custAccount->acnt_code = $data['acnt_code'];
        $custAccount->acnt_name = $data['acnt_name'];
        $custAccount->bank_code = $data['bank_code'];
        $custAccount->token = $data['token'] ?? null;
        $custAccount->cust_user_id = $user->id;
        $custAccount->confirmed_at = Carbon::now();
        $custAccount->statusid = 1;
        $custAccount->created_by = $user->id;
        $custAccount->save();
    }

    /**
     * "Данс салгах"
     *
     * @return array
     */
    public function oi000322(Request $request)
    {
        $data = $this->validate(
            $request,
            [
                'acnt_code' => 'required'
            ],
            [
                'acnt_code.required' => ResponseCodeEnum::required,
            ]
        );

        $custAccount = ApCustBankAccount::where('acnt_code', $data['acnt_code'])
            ->where('cust_user_id', auth()->user()->id)
            ->where('statusid', '>', 0)
            ->first();
        if (!$custAccount) {
            $this->error('RC000022');
        }

        $count =  ApCustBankAccount::where('acnt_code', $data['acnt_code'])
            ->where('cust_user_id', auth()->user()->id)
            ->where('statusid', '<', 0)
            ->count();

        $custAccount->statusid = $count ? ($count + 1) * -1 : -1;
        $custAccount->updated_by = auth()->user()->id;
        $custAccount->save();
    }

    /**
     * oi000280 Харилцах дансны дэлгэрэнгүй мэдээлэл
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000280(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric',
        ], [
            'acntCode.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ]);

        $service = new ApAcntService();
        $accountDetail = $service->getCasaAccountDetail($validated['acntCode'], $validated['instid']);
        $accountDetail['allowTransaction'] = 1;

        $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();
        $sharedCapitalProdCodes = [];
        if (isset($provider)) {
            $providerConfig = json_decode($provider->config, true);

            if (isset($providerConfig['savingLoan']['sharedCapitalProdCodes'])) {
                $sharedCapitalProdCodes = $providerConfig['savingLoan']['sharedCapitalProdCodes'];
            } else {
                $sharedCapitalProdCodes = [];
            }
        }

        if (in_array($accountDetail['prod_code'], $sharedCapitalProdCodes)) {
            $accountDetail['allowTransaction'] = 0;
        }

        return $accountDetail;
    }

    /**
     * oi000300 Зээлийн дансны дэлгэрэнгүй мэдээлэл
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000300(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric',
        ], [
            'acntCode.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ]);

        $service = new ApAcntService();
        $accountDetail =  $service->getLoanAccountDetail($validated['acntCode'], $validated['instid']);
        return $accountDetail;
    }

    /**
     * oi000290 Хадгаламжийн дансны дэлгэрэнгүй мэдээлэл
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000290(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric',
        ], [
            'acntCode.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ]);

        $service = new ApAcntService();
        $accountDetail = $service->getTdAccountDetail($validated['acntCode'], $validated['instid']);
        $accountDetail['allowTransaction'] = 1;

        $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();
        $sharedCapitalProdCodes = [];
        if (isset($provider)) {
            $providerConfig = json_decode($provider->config, true);

            if (isset($providerConfig['savingLoan']['sharedCapitalProdCodes'])) {
                $sharedCapitalProdCodes = $providerConfig['savingLoan']['sharedCapitalProdCodes'];
            } else {
                $sharedCapitalProdCodes = [];
            }
        }

        if (in_array($accountDetail['prod_code'], $sharedCapitalProdCodes)) {
            $accountDetail['allowTransaction'] = 0;
        }

        return $accountDetail;
    }

    /**
     * oi000310 Кредит дансны дэлгэрэнгүй мэдээлэл
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000310(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric',
        ], [
            'acntCode.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ]);

        $service = new ApAcntService();
        return $service->getCreditAccountDetail($validated['acntCode'], $validated['instid']);
    }

    /**
     * oi000480 Дансны хүүний дэлгэрэнгүй
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000480(Request $request)
    {
        $validated = $this->validate($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric',
            'acntType' => 'required|max:20',
        ]);

        $service = new ApAcntService();
        $data = $service->getAccountInt($validated['acntCode'], $validated['instid'], $validated['acntType']);
        return $data;
    }

    /**
     * oi000760 Бүтээгдэхүүний жагсаалт авах
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000760(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
        ]);

        $parents = GPInstConst::select('id', 'code', 'value', 'name', 'name2', 'value_add1', 'value_add2')
            ->where('instid', $validated['instid'])
            ->where('parent_code', 'PRODUCTS_TD_PRODCODE')
            ->where('statusid', 1)
            ->where('is_show_front', 1)
            ->get()
            ->toArray();

        $parentCodes = array_column($parents, 'code');

        $children = GPInstConst::select('id', 'code', 'parent_code', 'value', 'value_add1')
            ->where('instid', $validated['instid'])
            ->whereIn('parent_code', $parentCodes)
            ->where('statusid', 1)
            ->where('is_show_front', 1)
            ->get()
            ->toArray();

        $childrenMap = [];
        foreach ($children as $child) {
            if ($child['value_add1']) {
                // Үндсэндээ key->value боломжтой бүхэнийг хадгална
                $childrenMap[$child['parent_code']][$child['value_add1']] = $child['value'];
            }
        }

        foreach ($parents as &$parent) {
            $code = $parent['code'];
            if (isset($childrenMap[$code])) {
                foreach ($childrenMap[$code] as $k => $v) {
                    $parent[strtolower($k)] = $v;
                }
            }
        }

        // Эцэст нь массивийг буцаах
        return $parents;
    }
    /**
     * oi000770 Хэрэглэгчийн дансны тодорхойлолт авах
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000770(Request $request)
    {
        $validate = $this->validateMe($request, [
            'acntCode' => 'required|max:20',
            'instid' => 'required|numeric'
        ], [
            'acntCode.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ]);

        $user = auth()->user();
        $instid = $validate['instid'];
        $acntCode = $validate['acntCode'];
        $acnt = ApAcntDp::where('acnt_code', $acntCode)
            ->where('instid', $instid)
            ->where('userid', $user->id)
            ->first();

        if (!$acnt) {
            $acnt = ApAcntLn::where('acnt_code', $acntCode)
                ->where('instid', $instid)
                ->where('userid', $user->id)
                ->first();

            if (!$acnt) {
                throw new MeException('RC000034', ['mainacntno' => $acntCode]);
            }

            $acntType = 'ln';
        } else {

            $acntType = match (strtoupper($acnt->acnt_type ?? 'TD_ACNT')) {
                'TD_ACNT'   => 'td',
                'CASA_ACNT' => 'casa_acnt',
                'CCA'       => 'cca',
                default     => 'td',
            };
        }

        $connInst = ApInstCustUserLink::where('instid', $instid)
            ->where('cust_userid', $user->id)
            ->where('statusid' , 1)
            ->first();
        if (!$connInst) {
            throw new MeException('RC000184');
        }

        $acntService = new ApAcntService();
        $get_acnt_dtl = $acntService->getAccountDetail($acntCode, $acntType,  $instid, true);
        $accountDetail = json_decode(json_encode($get_acnt_dtl), true);

        $pdf = $acntService->generateDepositCertPdf($accountDetail, $user, $instid, $acntCode);

        return ['pdf' => $pdf];
    }
}
