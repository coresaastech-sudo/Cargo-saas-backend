<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Enums\ApAccountTypeEnum;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\ApContractService;
use Modules\Ap\Http\Services\ApLoanService;
use Modules\Ap\Http\Services\ApQpayService;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\ResponseCodeEnum;
use Illuminate\Support\Str;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Http\Services\PolarisApiRequestService;
use Modules\Gp\Entities\GPInstFeeTypeCur;
use Modules\Gp\Entities\Views\VwGPInstFeeList;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\AuthenticateDashboard;
use Modules\Ap\Entities\ApNegdi;
use Illuminate\Support\Facades\Auth;
use Modules\Ap\Entities\ApCustBankToken;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Entities\ApQpay;
use Modules\Ap\Entities\Views\VwApCustBankToken;
use Modules\Ap\Http\Services\ApAuthService;
use Modules\Ap\Http\Services\ApNegdiService;
use Modules\Gp\Entities\GpppList;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Http\Controllers\GPController;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ap\Http\Services\ApBonumService;

class ApServiceController extends Controller
{


    /**
     * oi000340 Зээл олгох /Шугам/
     *
     * @param  mixed $instid Зээл авах гэж байгаа байгууллагын дугаар
     * @param  mixed $txnAcntCode Зээлийн данс
     * @param  mixed $contId Өөрийн бүртгэлтэй дансны дугаар
     * @param  mixed $amount Хүсэж буй зээлийн дүн
     * @param  mixed $sign_image_id Гарын үсэг зам
     * @param  mixed $request
     * @return void
     */
    public function oi000340(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
            'txnAcntCode' => 'required',
            'contId' => 'required',
            'amount' => 'required|numeric',
            'sign_image_id' => 'required'
        ], [
            'instid.required' => ResponseCodeEnum::required,
            'txnAcntCode.required' => ResponseCodeEnum::required,
            'amount.required' => ResponseCodeEnum::required,
            'contId.required' => ResponseCodeEnum::required,
            'sign_image_id.required' => ResponseCodeEnum::required,
        ]);

        $contAcnt = ApCustBankAccount::where('id', $validated['contId'])
            ->where('statusid', '!=', -1)
            ->where('cust_user_id', auth()->user()->id)->first();
        if (empty($contAcnt)) {
            $this->error('RC000022');
        }

        $cust = ApCustomer::where('instid', $validated['instid'])
            ->where('regno', auth()->user()->regno)
            ->where('statusid', 1)->first();

        if (empty($cust)) {
            $this->error('RC000176');
        }

        $service = new ApLoanService();
        $bankCode = $service->getCallCgwBankCode($validated);

        if ($bankCode != $contAcnt->bank_code) {
            // Нэг удаад олгох зээлийн дээд дүн шалгав.
            $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();

            if (isset($provider)) {
                $providerConfig = json_decode($provider->config, true);

                if (isset($providerConfig['cgw']['limit']['otherBankTxnLimit'])) {
                    if ($validated['amount'] > $providerConfig['cgw']['limit']['otherBankTxnLimit']) {
                        $bank_name = $bankCode;

                        $bank = GPInstConst::where('parent_code', 'bank')->where('value', $bankCode)->first();
                        if ($bank) {
                            $bank_name = $bank->name;
                        }

                        throw new MeException("RC000214", ["amount" => number_format($providerConfig['cgw']['limit']['otherBankTxnLimit'], 2, '.', ','), "bank_name" => $bank_name]);
                    }
                }
            }
        }

        $validated['contAcntCode'] = $contAcnt->acnt_code;
        $validated['contAcntName'] = $cust->lname . " " . $cust->fname;
        $validated['contBankCode'] = $contAcnt->bank_code;
        $service = new ApLoanService();
        return $service->giveLoan($validated);
    }

    /**
     * oi000350 Хадгаламж барьцаалсан зээл авах
     *
     * @param  mixed $instid Зээл авах гэж байгаа байгууллагын дугаар
     * @param  mixed $txnAcntCode Барьцаалж буй хадгаламжийн данс
     * @param  mixed $contId Өөрийн бүртгэлтэй дансны дугаар
     * @param  mixed $amount Хүсэж буй зээлийн дүн
     * @param  mixed $signphoto Гарын үсэг зам
     * @param  mixed $request
     * @return void
     */
    public function oi000350(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
            'txnAcntCode' => 'required',
            'contId' => 'required',
            'amount' => 'required|numeric',
            'sign_image_id' => 'required'
        ], [
            'instid.required' => ResponseCodeEnum::required,
            'txnAcntCode.required' => ResponseCodeEnum::required,
            'amount.required' => ResponseCodeEnum::required,
            'contId.required' => ResponseCodeEnum::required,
            'sign_image_id.required' => ResponseCodeEnum::required,
        ]);
        // if (!checkInstPerm('lo0120', $validated['instid'])) {
        //     return response()->json('[lo0120] эрх олгогдоогүй байна.', 500);
        // }
        $contAcnt = ApCustBankAccount::where('id', $validated['contId'])
            ->where('statusid', 1)->where('cust_user_id', auth()->user()->id)->first();
        if (empty($contAcnt)) {
            $this->error('RC000022');
        }

        $cust = ApCustomer::where('instid', $validated['instid'])
            ->where('regno', auth()->user()->regno)
            ->where('statusid', '1')->first();

        if (empty($cust)) {
            $this->error('RC000176');
        }

        $service = new ApLoanService();
        $bankCode = $service->getCallCgwBankCode($validated);

        if ($bankCode != $contAcnt->bank_code) {
            // Нэг удаад олгох зээлийн дээд дүн шалгав.
            $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();

            if (isset($provider)) {
                $providerConfig = json_decode($provider->config, true);

                if (isset($providerConfig['cgw']['limit']['otherBankTxnLimit'])) {
                    if ($validated['amount'] > $providerConfig['cgw']['limit']['otherBankTxnLimit']) {
                        $bank_name = $bankCode;

                        $bank = GPInstConst::where('parent_code', 'bank')->where('value', $bankCode)->first();
                        if ($bank) {
                            $bank_name = $bank->name;
                        }
                        throw new MeException("RC000214", ["amount" => number_format($providerConfig['cgw']['limit']['otherBankTxnLimit'], 2, '.', ','), "bank_name" => $bank_name]);
                    }
                }
            }
        }


        // Зээлийн орлого хүлээж авах өөрийн дансны мэдээлэл
        $validated['contAcntCode'] = $contAcnt->acnt_code;
        $validated['contAcntName'] = $cust->lname . " " . $cust->fname;
        $validated['contBankCode'] = $contAcnt->bank_code;
        $service = new ApLoanService();
        return $service->getLoanSaving($validated);
    }


    /**
     * oi000360 - Хадгаламж барьцаалсан зээлийн мэдээлэл авах
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000360(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
            'txnAcntCode' => 'required'
        ]);

        $service = new ApLoanService();
        return $service->getLoanInfoTdAcnt($validated);
    }

    /**
     * oi000370 Зээлийн шимтгэлийн нөхцөл авах
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000370(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
            'amount' => 'required|numeric',
            'type_id' => 'required',
            'acnt_code' => 'required_if:type_id,LINE',
        ]);

        $polaris = new PolarisApiRequestService($validated['instid']);

        switch ($validated['type_id']) {
            case 'TD':
                $prod_code = ApAcntDp::where('acnt_code', $validated['acnt_code'])
                    ->where('instid', $validated['instid'])
                    ->where('statusid', 1)->first();
                $operation = 13610265;
                break;
            case 'LINE':
                $acnt = ApAcntLn::where('acnt_code', $validated['acnt_code'])->where('instid', $validated['instid'])->first();
                if (empty($acnt)) {
                    return response()->json("Зээлийн данс олдсонгүй!", 500);
                }
                $prod_code = $acnt->prod_code;
                $operation = 13610262;
                break;
            default:
                # code...
                break;
        }

        $fee = VwGPInstFeeList::where('feecode', $polaris->fee->fee_td)
            ->where('instid', $validated['instid'])
            ->where('statusid', 1)->first();

        if (empty($fee)) {
            throw new MeException('RC000188');
        }

        $feeconf = GPInstFeeTypeCur::where('feecode', $fee->feecode)
            ->where('instid', $validated['instid'])
            ->where('statusid', 1)->first();

        if (empty($feeconf)) {
            throw new MeException('RC000188');
        }

        $fee_config = json_decode($feeconf->formula, true);

        $service = new ApLoanService();
        $calc_amounts_resp = $service->getCalcAmounts($fee_config, $validated['amount']);

        $calc_fee_amount = $calc_amounts_resp['calc_fee_amount'];
        if ($calc_fee_amount > $validated['amount']) {
            throw new MeException('RC000189');
        }
        return $calc_amounts_resp;
    }


    /**
     * oi000380 Хадгаламжын данс үүсгэх
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000380(Request $request)
    {
        $validated = $this->validate($request, [
            'prodCode' => 'required',
            'instid' => 'required|numeric',
            'sign_image_id' => 'required',
            'amount' => 'nullable',
            'prodid' => 'required',
            'goal' => 'nullable'
        ], [
            'sign_image_id.required' => 'Гарын үсэг оруулна уу.'
        ]);

        $service = new ApAcntService();
        return $service->createTd($request, $validated['instid']);
    }

    /**
     * oi000390 - Хадгаламж барьцаалсан зээлийн мэдээлэл авах
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000390(Request $request)
    {
        $validated = $this->validate($request, [
            'prodCode' => 'required',
            'instid' => 'required|numeric',
            'amount' => 'required',
            'prodid' => 'required'
        ]);
        $service = new ApAcntService();
        return $service->initTd($validated, $validated['instid']);
    }

    /**
     * oi000400 - Зээл төлөх, Хадгаламж орлого хийх
     * нэхэмжлэх үүсгэх
     * typeid - [
     *  0 - Зээл төлөлт
     *  1 - Зээл хаах
     *  2 - Хугацаат хадгаламжийн дансанд орлого хийх.
     *  3 - CASA - Харилцах, хугацаагүй хадгаламжийн дансанд орлого хийх
     * ]
     * @param  mixed $request
     * @return void
     */
    public function oi000400(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
            'contAcntCode' => 'required',
            'amount' => 'required|numeric',
            'typeid' => 'in:0,1,2,3'
        ]);
        $user = auth()->user();

        if ($validated['amount'] < 20 && round($validated['amount'], 2) != 0) {
            return response()->json('QPAY төлбөрийн хэрэгслийн шаардлагын дагуу хамгийн багадаа 20 төгрөгөөр гүйлгээ хийгдэнэ.', 500);
        }

        // Гүйлгээний лимит шалгав.
        $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();

        if (isset($provider)) {
            $providerConfig = json_decode($provider->config, true);

            if (isset($providerConfig['cgw']['limit']['payLimit'])) {
                if ($validated['amount'] > $providerConfig['cgw']['limit']['payLimit']) {
                    throw new MeException("RC000215", ["amount" => number_format($providerConfig['cgw']['limit']['payLimit'], 2, '.', ',')]);
                }
            }
        }

        try {
            $qpayType = GPInstConst::where('parent_code', 'qpay_type')->where('instid', $validated['instid'])->where('statusid', '<>', -1)->first();
            $qpay = ($qpayType?->code == 'qpay_type_Negdi')
                ? new ApNegdiService($validated['instid'])
                : new ApQpayService($validated['instid']);
            $acnttabl = null;
            switch (@$validated['typeid']) {
                case 0:
                    $acnttabl = ApAcntLn::class;
                    break;
                case 1:
                    if ($validated['amount'] == 0) {

                        $validated['to_account'] = $validated['contAcntCode'];
                        $validated['cur_code'] = "MNT";
                        $validated['sender_invoice_no'] = random_number();
                        $dqpay = $qpay->store($validated);
                        $loanService = new ApLoanService();
                        $polaris = new PolarisApiRequestService($validated['instid']);
                        $cust = ApCustomer::where('instid', $validated['instid'])
                            ->where('regno', $user->regno)->where('statusid', '1')->first();
                        if (empty($cust)) {
                            throw new MeException('Харилцагчийн мэдээлэл системд бүртгэлгүй байна.');
                        }
                        if ($polaris->is_use_cust_susp_acnt == 1 || $polaris->is_use_cust_susp_acnt == '1') {
                            $casaAcnt = ApAcntDp::where('prod_code', $polaris->susp_acnt_prod_code)
                                ->whereIn('status', ['O', '4'])->where('instid', $validated['instid'])
                                ->where('cust_code', $cust->cif)
                                ->orderBy('acnt_code', 'desc')->first();
                            if (empty($casaAcnt)) {
                                throw new MeException('Харилцагч дээр түр дансны бүртгэл хийгдээгүй байна.');
                            }
                            $tran_acnt = $casaAcnt->acnt_code;
                        } else {
                            $tran_acnt = $polaris->repay_susp_accountno;
                        }

                        $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $validated['instid']]));
                        try {
                            $resp = $loanService->paymentLoan(
                                $validated['instid'],
                                $dqpay,
                                $tran_acnt,
                                '',
                                '',
                                [
                                    'txn_date' => $sysDate,
                                ]
                            );

                            return "Зээл хаах хүсэлт амжилттай боллоо.";
                        } catch (Exception $ex) {
                            return $ex->getMessage();
                        }
                    }
                    $acnttabl = ApAcntLn::class;
                    break;
                case 2:
                    $acnttabl = ApAcntDp::class;
                    break;
                case 3:
                    $acnttabl = ApAcntDp::class;
                    break;
                default:
                    # code...
                    break;
            }
        } catch (Exception $e) {
            throw $e;
        }

        $type = 'TD'; // TD - Хадгаламж орлого, LINE = Шугам, LN - Зээл төлөлт
        
        if ($validated['typeid'] != 2) {

            $lnAcnt = $acnttabl::where('acnt_code', $validated['contAcntCode'])->where('instid', $validated['instid'])->first();

            $type = $lnAcnt->acnt_type;
            
            if (!$lnAcnt) {
                throw new MeException("RC000022");
            }
            if ($validated['typeid'] == 1) {
                $validated['amount'] = $lnAcnt->total_bal;
            }
        }
        $validated['cur_code'] = 'MNT';
        $validated['amount'] = round($validated['amount'], 2);
        $resp = $qpay->createInvoice($validated, $type);
        return $resp['data'];
    }

    /**
     * oi000410 Qpay төлбөр төлөгдсөн эсэхийг мобайл аппаас шалгах
     *
     * @param  mixed $request
     */
    public function oi000410(Request $request)
    {
        sleep(5);
        $validated = $this->validate($request, [
            'invoiceid' => 'required',
        ], [
            'invoiceid.required' => ResponseCodeEnum::required,
        ]);

        $qpay = ApQpay::where('invoice_id', $validated['invoiceid'])->first();
        if ($qpay) {
            if (config('app.env') == 'production') {
                return ['status' => $qpay->statusid];
            }

            if ($qpay->statusid != 1 && $qpay->statusid != 3) {
                $qpays = new ApQpayService($qpay->instid);
                return $qpays->callBackUrl($qpay->sender_invoice_no, true);
            } else {
                return ['status' => $qpay->statusid];
            }
        } else {
            throw new MeException("RC000210");
        }
    }

    /**
     * oi000420 Гэрээний нөхцөл авах
     *
     * @param  mixed $request
     * @return string
     */
    public function oi000420(Request $request)
    {
        $data = $this->validate($request, [
            'instid' => 'required|numeric',
            'type_id' => 'required',
            'acnt_code' => 'required',
            'amount' => 'required',
            'contId' => 'required',
            'int_rate' => 'required',
        ], [
            'instid.required' => ResponseCodeEnum::required,
            'type_id.required' => ResponseCodeEnum::required,
            'acnt_code.required' => ResponseCodeEnum::required,
            'amount.required' => ResponseCodeEnum::required,
            'contId.required' => ResponseCodeEnum::required,
            'int_rate.required' => ResponseCodeEnum::required,
        ]);

        $contAcnt = ApCustBankAccount::where('id', $data['contId'])
            ->where('cust_user_id', auth()->user()->id)
            ->where('statusid', 1)
            ->first();

        if (!$contAcnt) {
            $this->error('RC000022');
        }

        $data['rcv_account'] = $contAcnt->acnt_code;
        $data['bank_code'] = $contAcnt->bank_code;
        $service = new ApContractService();
        $resp = $service->getFillContract($data, auth()->user(), null);
        return $resp;
    }

    /**
     * oi000430 Гэрээний нөхцөл авах хадгаламж
     *
     * @param  mixed $request
     * @return string
     */
    public function oi000430(Request $request)
    {
        $data = $this->validate($request, [
            'instid' => 'required|numeric',
            'type_id' => 'required',
            'amount' => 'required',
            'productno' => 'required',
        ]);

        $service = new ApContractService();
        $resp = $service->getContractNewAccount($data, auth()->user());
        return $resp;
    }

    /**
     * oi000440 Хадгаламж зээлийн харьцаа
     *
     * @param  mixed $request
     * @return string
     */
    public function oi000440(Request $request)
    {
        $user = auth()->user();
        $custs = ApCustomer::where('regno', $user->regno)->where('statusid', "<>", -1)->get();
        if (!$custs) {
            $this->error('RC000015');
        }

        $casa = new ApAcntService();
        $response = [];
        foreach ($custs as $cust) {
            $inst = GPInstList::where('id', $cust->instid)->where('statusid', '<>', -1)->first();
            $ownaccounts = $casa->getAccounts($user, $cust->instid, true);

            $lnAmount = 0;
            $dpAmount = 0;
            foreach ($ownaccounts as $account) {
                $type = ApAccountTypeEnum::fromString(Str::lower($account['acnt_type']));


                if ($type == ApAccountTypeEnum::loan) {
                    $lnAmount = $lnAmount + $account['avail_com_bal'];
                }

                if ($type == ApAccountTypeEnum::line) {
                    $lnAmount = $lnAmount + $account['avail_com_bal'];
                }

                if ($type == ApAccountTypeEnum::casa_acnt) {
                    $dpAmount = $dpAmount + $account['avail_bal'];
                }

                if ($type == ApAccountTypeEnum::td_acnt) {
                    $dpAmount = $dpAmount + $account['avail_bal'];
                }
            }
            $response[] = ["instname" => $inst->name, 'instid' => $inst->id, "dpamount" => $dpAmount, "lnamount" => $lnAmount];
        }
        return $response;
    }


    /**
     * oi000460 Апп хувилбар шалгах
     *
     * @param  mixed $request
     *
     */
    public function oi000460(Request $request)
    {
        $validated = $this->validate($request, [
            'version' => 'required',
        ]);
        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $showregister = 0;
        if ($app != null) {
            if ($app->app_data) {
                $appData = json_decode($app->app_data, true);
                $max = $appData['max_version'];
                $min = $appData['min_version'];
                $showregister = $appData['enable_register'];
            }
        } else {
            $max = GPInstConst::where('code', "APP_VERSION_2")->where('statusid', '<>', -1)->first();
            $min = GPInstConst::where('code', "APP_VERSION_1")->where('statusid', '<>', -1)->first();
            $max = $max->value;
            $min = $min->value;
        }

        if (!isset($max)) {
            throw new MeException("RC000186");
        }

        if (!isset($min)) {
            throw new MeException("RC000187");
        }

        if ((intval($validated['version']) >= intval($min)) && (intval($validated['version']) <= intval($max))) {
            return ['success' => 1, 'showregister' => $showregister];
        } else {
            return ['success' => 0, 'showregister' => $showregister];
        }
    }

    /**
     * generateQpay - QPAY гүйлгээний callback дуудалт
     *
     * @param  mixed $request
     */
    public function paymentCheckLoanQpay($instid, $invoiceno)
    {
        $r = new GPLogRequestList();
        $request = request();
        $method = $request->getMethod();
        $r->url = $request->fullUrl();
        $r->AC = '000000';
        $r->method = $method;
        $r->responsecode = 200;
        $r->save();
        $qpay = new ApQpayService($instid);
        $data = $qpay->callBackUrl($invoiceno);
        return $data;
    }
    /**
     * WebSocket - эрх шалгах
     */
    public function ap030000(Request $request)
    {
        $GPcontroller = new GPController();
        if (!$GPcontroller->checkActionCode('ap030000')) {
            $this->error("SR0023", ['AC' => 'ap030000']);
        }
        $authcontroller = new AuthenticateDashboard();
        $token = $authcontroller->__invoke($request);
        return $token;
    }

    /**
     * Negdi - төлөлт үүсгэх
     */
    public function oi000520(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
            'contAcntCode' => 'required',
            'amount' => 'required|numeric',
            'cur_code' => 'nullable',
            'txn_type' => 'required|in:0,1,2,3',
            'type' => 'nullable|in:0,1'
        ]);
        $user = auth()->user();
        if (!isset($validated['cur_code'])) {
            $validated['cur_code']  = "MNT";
        }

        if (!isset($validated['type'])) {
            $validated['type']  = 1;
        }

        $typeid = '';
        try {
            $validated['to_account'] = $validated['contAcntCode'];
            $negdiService = new ApNegdiService($validated['instid']);
            $acnttabl = null;
            switch (@$validated['txn_type']) {
                case 0:
                    $acnttabl = ApAcntLn::class;
                    $typeid = AccountTypeEnum::ln;
                    break;
                case 1:
                    $typeid = AccountTypeEnum::ln;
                    if ($validated['amount'] == 0) {

                        $validated['sender_invoice_no'] = random_number();
                        $dqpay = $negdiService->store([
                            'amount' => $validated['amount'],
                            'checkid' => 0,
                            'instid' => $validated['instid'],
                            'to_account' => $validated['contAcntCode'],
                            'typeid' => $typeid,
                            'cur_code' => $validated['cur_code'],
                        ]);

                        $dqpay->typeid = 1;
                        $loanService = new ApLoanService();
                        $polaris = new PolarisApiRequestService($validated['instid']);
                        $cust = ApCustomer::where('instid', $validated['instid'])
                            ->where('regno', $user->regno)->where('statusid', '1')->first();
                        if (empty($cust)) {
                            throw new MeException('Харилцагчийн мэдээлэл системд бүртгэлгүй байна.');
                        }
                        if ($polaris->is_use_cust_susp_acnt == 1 || $polaris->is_use_cust_susp_acnt == '1') {
                            $casaAcnt = ApAcntDp::where('prod_code', $polaris->susp_acnt_prod_code)
                                ->whereIn('status', ['O', '4'])->where('instid', $validated['instid'])
                                ->where('cust_code', $cust->cif)
                                ->orderBy('acnt_code', 'desc')->first();
                            if (empty($casaAcnt)) {
                                throw new MeException('Харилцагч дээр түр дансны бүртгэл хийгдээгүй байна.');
                            }
                            $tran_acnt = $casaAcnt->acnt_code;
                        } else {
                            $tran_acnt = $polaris->repay_susp_accountno;
                        }

                        $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $validated['instid']]));
                        try {

                            // $tmpuser = auth()->user();
                            // $onlineteller = CoreService::getInstGp($validated['instid'], 'ONLINETELLERNUMBER');

                            // $user = GPInstUser::where('instid', $validated['instid'])->find(
                            //     $onlineteller
                            // );
                            // Auth::setUser($user);

                            $resp = $loanService->paymentLoan(
                                $validated['instid'],
                                $dqpay,
                                $tran_acnt,
                                '',
                                '',
                                [
                                    'txn_date' => $sysDate,
                                ]
                            );
                            // Auth::setUser(ApCustUser::find($tmpuser->id));

                            return "Зээл хаах хүсэлт амжилттай боллоо.";
                        } catch (Exception $ex) {
                            return $ex->getMessage();
                        }
                    }
                    $acnttabl = ApAcntLn::class;
                    break;
                case 2:
                    $typeid = AccountTypeEnum::dp;
                    $acnttabl = ApAcntDp::class;
                    break;
                case 3:
                    $typeid = AccountTypeEnum::dp;
                    $acnttabl = ApAcntDp::class;
                    break;
                default:
                    # code...
                    break;
            }
        } catch (Exception $e) {
            throw $e;
        }

        if ($validated['txn_type'] != 2) {
            $lnAcnt = $acnttabl::where('acnt_code', $validated['contAcntCode'])->where('instid', $validated['instid'])->first();
            if (!$lnAcnt) {
                throw new MeException("RC000022");
            }

            if ($validated['txn_type'] == 1) {
                $validated['amount'] = $lnAcnt->total_bal;
            }
        }

        $negdipay = $negdiService->store([
            'amount' => $validated['amount'],
            'checkid' => 0,
            'instid' => $validated['instid'],
            'typeid' => $typeid,
        ]);

        $validated['id'] = $negdipay->id;


        if (@$validated['txn_type'] == '0') {
            $txndesc = 'Зээл төлөлт';
        } else if (@$validated['txn_type'] == '1') {
            $txndesc = 'Зээл хаах';
        } else if ($validated['txn_type'] == '2') {
            $txndesc = 'Хадгаламж данс цэнэглэх';
        } else if ($validated['txn_type'] == '3') {
            $txndesc = 'Харилцах данс цэнэглэх';
        }

        $validated['description'] = $txndesc;

        try {
            $resp = $negdiService->createOrder($validated, $negdipay);
        } catch (Exception $e) {
            throw $e;
        }

        $tmpdata = [];
        if (
            gettype($resp) === 'array'
            && isset($resp['order'])
        ) {
            if ($resp['order']['status'] == 'Preparing') {
                $tmpdata['tranid'] = $resp['order']['tranid'];
                $tmpdata['checkid'] = $resp['order']['checkid'];
                $tmpdata['status'] = $resp['order']['status'];
                $tmpdata['negdiurl'] = @$resp['order']['negdiurl'];
                $tmpdata['detail'] = @$resp['order']['detail'];
            } else if ($resp['order']['status'] == 'System error') {
                $negdipay->update(['status' => $resp['order']['status']]);
                throw new MeException($resp['order']['detail']);
            } else if ($resp['order']['status'] == 'Approved') {
                $tmpdata['description'] = 'ME: ' . $txndesc;
                $tmpdata['ordertype'] = $negdiService->ordertype;
                $tmpdata['terminalid'] = $negdiService->terminalid;
                $tmpdata['username'] = $negdiService->username;
                $tmpdata['password'] = $negdiService->password;
                $tmpdata['returnurl'] = $negdiService->returnurl;
                $tmpdata['cur_code'] = $negdiService->cur_code;
                $tmpdata['customerid'] = auth()->user()->id;
                $tmpdata['customername'] = auth()->user()->lastname . ' ' . auth()->user()->firstname;
            }
        }

        if (count($tmpdata) > 0) {
            $negdipay->update(array_merge($tmpdata, $validated));
        }

        return $resp;
    }

    public function oi000530(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required|numeric',
        ]);

        $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();
        $paymentMethods = [];
        if (isset($provider)) {
            $providerConfig = json_decode($provider->config, true);

            if (isset($providerConfig['paymentMethods'])) {
                $paymentMethods = $providerConfig['paymentMethods'];
            } else {
                $paymentMethods = ['qpay'];
            }
        }
        return $paymentMethods;
    }

    public function oi000540(Request $request)
    {
        $user = auth()->user();

        $app = GpppList::where('id', $user->app_id)->where('statusid', 1)->first();

        if (!$app) {
            $this->error('RC000010', [
                'id' => $user->app_id
            ]);
        }

        $cardSystem = CoreService::getInstGp($app['instid'], 'ConnectCardSystem');

        // if ($cardSystem == 'BONUM') {
        //     $service =  new ApBonumService($app['instid'], $user->id);
        //     $cardList = $service->getCustCardInfo(mb_strtoupper($user->regno));
        //     $list = [];
        //     foreach ($cardList as $card) {
        //         $list[] = [
        //             "maskedpan" => $card['cardNumber'],
        //             "cardId" => $card['cardId'],
        //             "bankName" => $card['embossName'],
        //             "creditLimit" => $card['creditLimit'],
        //             "availableLimit" => $card['availableLimit'],
        //             "currency" => $card["currency"],
        //             "expdate" => $card["expiry"],
        //             "status" => $card["status"],
        //             "productId" => $card["productId"],
        //             "cardSystem" => $cardSystem,
        //         ];
        //     }
        //     return $list;
        //     // return VwApCustBankToken::select('id', 'maskedpan', 'expdate', 'brand', 'bankname', 'bank_name', 'bank_name2', 'dicvalue1', 'dicvalue2')->where('cust_user_id', $user->id)->where('statusid', '>', 0)->get();
        // } else {
        $tokens = VwApCustBankToken::select('id', 'maskedpan', 'expdate', 'brand', 'bankname', 'bank_name', 'bank_name2', 'dicvalue1', 'dicvalue2')->where('cust_user_id', $user->id)->where('statusid', '>', 0)->get();
        // cardSystem талбарыг нэмэх
        foreach ($tokens as $token) {
            $token->cardSystem = $cardSystem;
        }
        return $tokens;
        // }
    }

    public function oi000550(Request $request)
    {
        $validated = $this->validate(
            $request,
            [
                'id' => 'required'
            ],
            [
                'id.required' => ResponseCodeEnum::required,
            ]
        );

        $custToken = ApCustBankToken::where('id', $validated['id'])
            ->where('cust_user_id', auth()->user()->id)
            ->where('statusid', '>', 0)
            ->first();

        if (!$custToken) {
            $this->error('RC000022');
        }

        $count =  ApCustBankToken::where('tokenid', $custToken->tokenid)
            ->where('cust_user_id', auth()->user()->id)
            ->where('statusid', '<', 0)
            ->count();

        $custToken->statusid = $count ? ($count + 1) * -1 : -1;
        $custToken->updated_by = auth()->user()->id;
        $custToken->save();
    }

    public function oi000560(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required',
            'id' => 'required',
            'tranid' => 'required',
        ]);


        $token = ApCustBankToken::where('id', $validated['id'])->where('statusid', 1)->where('cust_user_id', auth()->user()->id)->first();
        if (!$token) {
            $this->error('RC000010', ['id', $validated['id']]);
        }

        $negdipay = ApNegdi::where('tranid', $validated['tranid'])->first();

        if (!$negdipay) {
            $this->error('RC000010', ['id', $validated['tranid']]);
        }

        $data = [
            'tokenid' => $token->tokenid,
            'customerid' => $token->cust_user_id,
            'tranid' => $negdipay->tranid,
            'checkid' => $negdipay->checkid,
            'amount' => $negdipay->amount
        ];

        try {
            $negdiService = new ApNegdiService($validated['instid']);
            $resp = $negdiService->processOrder($data);
        } catch (Exception $e) {
            throw $e;
        }

        if (
            gettype($resp) === 'array'
            && isset($resp['order'])
            && $negdipay->statusid != 1
        ) {
            if ($resp['order']['status'] == 'Preparing') {
                $tmpdata['tranid'] = $resp['order']['tranid'];
                $tmpdata['checkid'] = $resp['order']['checkid'];
                $tmpdata['status'] = $resp['order']['status'];
                $tmpdata['negdiurl'] = @$resp['order']['negdiurl'];
                $tmpdata['detail'] = @$resp['order']['detail'];
                throw new MeException('RC000223');
            } else if ($resp['order']['status'] == 'Approved') {
                try {
                    $res =  $negdiService->callBackUrl(@$negdipay->id);
                    return $res;
                } catch (Exception $e) {
                    throw new MeException('RC000223');
                }
            } else {
                $negdipay->update(['status' => $resp['order']['status']]);
                throw new MeException('RC000223');
            }
        }
    }

    public function oi000570(Request $request)
    {
        $user = auth()->user();

        $instids = ApInstCustUserLink::where('cust_userid', $user->id)->where('statusid', 1)->pluck('instid');

        $insts = GPInstList::select('name', 'name2', 'phone', 'email', 'w3w', 'logo')->whereIn('id', $instids)->where('statusid', 1)->get();

        return $insts;
    }

    public function oi000810(Request $request)
    {
        $validate = $this->validateMe($request, [
            'acntno' => 'required',
            'amount' => 'required|numeric|min:1',
            'receive_acntno' => 'required',
        ], [
            'acntno.required' => ResponseCodeEnum::required,
            'amount.required' => ResponseCodeEnum::required,
            'receive_acntno.required' => ResponseCodeEnum::required,
        ]);

        $service = new ApLoanService();
        return $service->withdrawDpAcnt($validate);
    }
            

    public function negdiCallBackUrl($instid, $transactionId)
    {
        $service = new ApNegdiService($instid);
        $res = $service->callBackUrl($transactionId);
        $redirect = $service->redirect1 ?? '';
        $redirect = str_replace("{id}", $transactionId, $redirect);
        $redirect = str_replace("{res}", $res["statusid"] == 1 ? "APPROVED" : "DECLINED", $redirect);

        return redirect($redirect);
    }

    public function negdiQPayCallBackUrl($instid)
    {
        $tranid = request('tranid');
        $transactionId = ApNegdi::where('tranid', $tranid)->where('instid', $instid)->first()->id;

        $r = new GPLogRequestList();
        $request = request();
        $method = $request->getMethod();
        $r->url = $request->fullUrl();
        $r->AC = '000000';
        $r->method = $method;
        $r->responsecode = 200;
        $r->save();
        $qpay = new ApNegdiService($instid);
        $data = $qpay->callBackUrl($transactionId);

        return $data;
    }
}
