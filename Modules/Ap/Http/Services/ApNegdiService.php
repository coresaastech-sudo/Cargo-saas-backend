<?php

namespace Modules\Ap\Http\Services;

use App\Events\ApTxnMonitoringEvent;
use App\Exceptions\MeException;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\Views\VwAdNotificationUsers;
use Modules\Ad\Http\Services\AdCreditInfoBueroService;
use Modules\Ad\Http\Services\AdNotificationService;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApCustBankToken;
use Modules\Ap\Entities\ApCustInquiry;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApNegdi;
use Modules\Ap\Entities\ApQpay;
use Modules\Ap\Entities\ApTxnJournal;
use Modules\Ap\Http\Controllers\ApInstController;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\ApLoanService;
use Modules\Ap\Http\Services\PolarisApiRequestService;
use Modules\Gp\Entities\GPConnConf;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\GPProviderConf;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Http\Services\IaTxnService;

class ApNegdiService
{
    private $instid;
    public $createorder;
    public $createorderwithtoken;
    public $traninquiry;
    public $processorder;
    public $terminalid;
    public $ordertype;
    public $ordertype1;
    public $ordertype_qpay;
    public $check_qpay;
    public $username;
    public $password;
    public $cur_code;
    public $returnurl;
    public $host_merchant_v2 = '';
    private $internalAccount = '';
    private $token;
    public $gen_token;
    public $redirect1;
    public $publickey;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct($instid)
    {
        $this->instid = $instid;
        $pp = GPProviderConf::where("code", 'RTP')->where('statusid', 1)->where('instid', $instid)->first();
        $negdi = json_decode($pp->config);
        if (!$negdi) {
            throw new MeException('SR1133', ['field' => "Negdi"]);
        }
        $this->ordertype = $negdi->card_ecommerce->ordertype ?? '';
        $this->ordertype1 = $negdi->card_ecommerce->ordertype1 ?? '';
        $this->ordertype_qpay = $negdi->card_ecommerce->ordertype_qpay ?? '';
        $this->check_qpay = $negdi->card_ecommerce->checkqpay ?? '';
        $this->terminalid = $negdi->card_ecommerce->terminalid ?? '';
        $this->username = $negdi->card_ecommerce->username ?? '';
        $this->password = $negdi->card_ecommerce->password ?? '';
        $this->returnurl = $negdi->card_ecommerce->returnurl ?? '';
        $this->processorder = $negdi->card_ecommerce->processorder ?? '';
        $this->cur_code = $negdi->card_ecommerce->curcode ?? '';
        $this->internalAccount = $negdi->card_ecommerce->internalAccount ?? '';
        $this->redirect1 = $negdi->card_ecommerce->redirect1 ?? '';
        $this->publickey = $negdi->card_ecommerce->publickey ?? '';

        //url
        $this->createorder = $negdi->card_ecommerce->createorder ?? '';
        $this->createorderwithtoken = $negdi->card_ecommerce->createorderwithtoken ?? '';
        $this->traninquiry = $negdi->card_ecommerce->traninquiry ?? '';
        $connConf = GPConnConf::where("id", $pp->connid)->where('instid', $this->instid)->first();
        if (!$connConf) {
            throw new MeException('SR1134', ['field' => "Negdi"]);
        }
        if (!$conn = json_decode($connConf->config)) {
            throw new MeException('SR1135', ['field' => "Negdi"]);
        }
        $this->host_merchant_v2 = $conn->urlPayment;
    }

    public function postApi($url, $params)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $r = GPLogRequestList::create([
            'userid' => 1,
            'url' => $this->host_merchant_v2 . $url,
            // 'responsecode',
            // 'responsetime',
            'request' => json_encode($params),
            'method' => 'POST',
            'instid' => 1,
        ]);
        $response = Http::withHeaders(
            [
                // 'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->post($this->host_merchant_v2 . $url, $params);

        $r->update([
            'responsetime' => (Carbon::now()->getTimestampMs() - $startTime) / 1000,
            'response' => $response->body(),
            'responsecode' => $response->status(),
        ]);

        $repsonse = json_decode((string) $response->getBody(), true);
        if (@$response['ordersign']) {
            $ordersign = base64_decode($response['ordersign']);

            $cleardata = json_encode($repsonse['order']);
            // Verify the signature with the public key
            $verify_result = openssl_verify($cleardata, $ordersign, $this->publickey, OPENSSL_ALGO_SHA256);

            if (!$verify_result) {
                throw new MeException('Дата шалгалт амжилтгүй боллоо.');
            }
        } else {
            throw new MeException('Гүйлгээ хийх системтэй холбогдож чадсангүй.');
        }
        return $repsonse;
    }


    public function createOrder($data, $negdipay)
    {

        $data['ordertype'] = ($data['type'] == 0) ? $this->ordertype1 : $this->ordertype;
        $data['terminalid'] = $this->terminalid;
        $data['username'] = $this->username;
        $data['password'] = $this->password;
        $data['returnurl'] = $this->returnurl . $data['instid'] . '/' . $data['id'];
        $data['ordernum'] = $data['to_account'];
        $data['currency'] = $this->cur_code;
        $data['customerid'] = auth()->user()->id;
        $data['customername'] = auth()->user()->lastname . ' ' . auth()->user()->firstname;

        $negdipay->update([
            'ordertype' => $data['ordertype'],
            'terminalid' =>  $data['terminalid'],
            'username' =>  $data['username'],
            'password' =>  $data['password'],
            'returnurl' =>  $data['returnurl'],
            'ordernum' =>  $data['ordernum'],
            'customerid' =>  $data['customerid'],
            'customername' =>  $data['customername']
        ]);

        return $this->postApi(($data['type'] == 0) ? $this->createorderwithtoken : $this->createorder, $data);
    }

    public function checkPayment($data ,$url = null)
    {
        $response = $this->postApi($url ?? $this->traninquiry, $data);
        return $response;
    }

    public function processOrder($data)
    {
        $response = $this->postApi($this->processorder, $data);
        return $response;
    }


    public function store($data)
    {
        $user = auth()->user();
        $negdipay = new ApNegdi();
        foreach ($negdipay->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $negdipay->$field = $data[$field];
            }
        }

        $negdipay->statusid = 0;
        $negdipay->created_at = Carbon::now();
        $negdipay->created_by = $user->id;
        $negdipay->save();
        return $negdipay;
    }

    public function callBackUrl($transactionId)
    {
        $negdipay = ApNegdi::where('id', $transactionId)->where('instid', $this->instid)->first();
        $websocketid = 0;

        if ($negdipay) {
            $checkRequest = array(
                'tranid' => $negdipay->tranid,
                'checkid' => $negdipay->checkid
            );
            $negdipay->callbacked_at = Carbon::now();
            if ($negdipay->ordertype == $this->ordertype_qpay) {
                $responseData = $this->checkPayment($checkRequest, $this->check_qpay);
            } else {
                $responseData = $this->checkPayment($checkRequest);
            }

            if (!empty($negdipay->created_by)) {
                $user = ApCustUser::where('id', $negdipay->created_by)->first();
            } else {
                return;
            }

            $websocketid = rand(100, 999999);
            $inst = GPInstList::where('id', $this->instid)->first();
            $desciption = $negdipay->description;

            switch ($negdipay->txn_type) {
                case '2':
                    $title = 'Хадгаламж данс орлого';
                    break;
                case '3':
                    $title = 'Харилцах данс орлого';
                    break;
                default:
                    $title = 'Зээл төлөлт';
                    break;
            }

            auth()->setUser($user);
            // Negdi гүйлгээ амжилттай болох
            try {
                if (
                    gettype($responseData) === 'array'
                    && isset($responseData['order'])
                    && @$responseData['order']['status'] == 'Approved' && $negdipay->statusid != 1
                ) {

                    $desciption = 'TRN=' . (@$responseData['order']['tranid'] ?? null) . '-' . (@$responseData['order']['ordernum'] ?? null)  . '-' . (@$inst->name ?? @$inst->name2);
                    $contaccountno = '';
                    $cust = ApCustomer::where('instid', $this->instid)->where('regno', $user->regno)->where('statusid', 1)->first();
                    if (empty($cust)) {
                        throw new MeException('RC000197');
                    }
                    $polaris = new PolarisApiRequestService($this->instid);

                    $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $this->instid]));
                    $negdipay->status = @$responseData['order']['status'] ?? null;
                    $negdipay->approvalCode = @$responseData['order']['approvalCode'] ?? null;
                    $negdipay->description = @$desciption ?? @$responseData['order']['description'];
                    $negdipay->ordernum = @$responseData['order']['ordernum'] ?? null;

                    $tokens = @$responseData['order']['token'] ?? null;
                    $customerregisterid =  @$responseData['order']['customer']['customerregisterid'] ?? null;

                    if ($tokens) {
                        foreach ($tokens as $token) {
                            $exist = ApCustBankToken::where('cust_user_id', $user->id)->where('tokenid', $token['tokenid'])->where('statusid', 1)->first();
                            if (!$exist) {
                                ApCustBankToken::create(
                                    [
                                        'cust_user_id' => $user->id,
                                        'tokenid' => $token['tokenid'],
                                        'customerregisterid' => $customerregisterid,
                                        'maskedpan' => $token['maskedpan'],
                                        'expdate' => $token['expdate'],
                                        'brand' => $token['brand'],
                                        'bankname' => $token['bankname'],
                                        'statusid' => 1,
                                        'created_by' => $user->id,
                                    ]
                                );
                            }
                        }
                    }
                    if ($negdipay->txn_type == '2') {
                        try {
                            $acntService = new ApAcntService();

                            $prodcode = null;

                            $account = ApAcntDp::where('acnt_code', $negdipay->to_account)->where('instid', $this->instid)->first();

                            if (isset($account)) {
                                $prodcode = $account->prod_code;
                            }
                            $respdata = $acntService->tdQpayTransaction(
                                [
                                    "txnAcntCode" => $this->internalAccount,
                                    "txnAmount" => $negdipay->amount,
                                    "rate" => 1,
                                    "contAcntCode" => $negdipay->to_account,
                                    "contAmount" => $negdipay->amount,
                                    "contRate" => 1,
                                    "txnDesc" => $negdipay->description ?? 'Орлогын гүйлгээ',
                                    "instid" => $this->instid,
                                    "userid" => $negdipay->created_by,
                                    "curCode" => $negdipay->cur_code,
                                    "txn_date" => $sysDate,
                                    "cust" => $cust,
                                    "prodcode" => $prodcode,
                                ]
                            );

                            $negdipay->jrno = $respdata['txnJrno'] ?? 0;
                            $negdipay->statusid = 1;
                            $msg = 'Хадгаламжийн ' . $negdipay->to_account . ' дансруу амжилттай орлого хийгдлээ.';
                        } catch (Exception $ex) {
                            $negdipay->statusid = 2;
                            $msg = 'Хадгаламжийн дансруу орлого хийх үйлдэл амжилтгүй. Суурь системд алдаа гарлаа.';
                            Log::error($ex);
                        }
                    } else if ($negdipay->txn_type == '3') {
                        try {
                            $prodcode = null;

                            $account = ApAcntDp::where('acnt_code', $negdipay->to_account)->where('instid', $this->instid)->first();

                            if (isset($account)) {
                                $prodcode = $account->prod_code;
                            }

                            $acntService = new ApAcntService();
                            $respdata = $acntService->CasaQpayTransaction(
                                [
                                    "txnAcntCode" => $this->internalAccount,
                                    "txnAmount" => $negdipay->amount,
                                    "rate" => 1,
                                    "contAcntCode" => $negdipay->to_account,
                                    "contAmount" => $negdipay->amount,
                                    "contRate" => 1,
                                    "txnDesc" => $negdipay->description ?? 'Орлогын гүйлгээ',
                                    "instid" => $this->instid,
                                    "userid" => $negdipay->created_by,
                                    "txn_date" => $sysDate,
                                    "curCode" => $negdipay->cur_code,
                                    "cust" => $cust,
                                    "prodcode" => $prodcode,
                                ]
                            );

                            $negdipay->jrno = $respdata['txnJrno'] ?? 0;
                            $negdipay->statusid = 1;
                            $msg = 'Харилцах ' . $negdipay->to_account . ' дансруу амжилттай орлого хийгдлээ.';
                        } catch (Exception $ex) {
                            $negdipay->statusid = 2;
                            $msg = 'Харилцах ' . $negdipay->to_account . ' дансруу орлого хийх үйлдэл амжилтгүй. Суурь системд алдаа гарлаа.';
                            Log::error($ex);
                        }
                    } else if ($negdipay->txn_type == '4') {
                        try {
                            // $userid = auth()->user()->id;
                            $instconst = GPInstConst::where('code', 'MAIN_APP_INST_ID')
                                ->where('statusid', '<>', -1)
                                ->first();

                            if ($instconst) {
                                $onlineteller = CoreService::getInstGp($negdipay['instid'], 'ONLINETELLERNUMBER');

                                $service = new AdCreditInfoBueroService($instconst->value, $onlineteller);

                                $inquiry = ApCustInquiry::where('id', $negdipay->inquiry_id)
                                    ->whereIn('statusid', [0, 2])
                                    ->first();

                                if ($inquiry) {
                                    $req = [
                                        "productno" => $inquiry->productno,
                                        "purptypeid" => $inquiry->purptypeid,
                                        "purposedesc" => $inquiry->purposedesc,
                                        "custtypeid" => $inquiry->custtypeid,
                                        "custregno" => $inquiry->regno,
                                        "custemail" => "",
                                        "custphone" => ""
                                    ];
                                    try {
                                        $response = $service->post($req);
                                    } catch (Exception $ex) {
                                        Log::debug($ex);
                                        throw new MeException('Лавлагаа авах үед алдаа гарлаа.');
                                    }

                                    if ($response['response_code'] == 'SR0000' && $response['response']['responsecode'] == 'SR0000') {
                                        $inquiry->regno = $response['response']['cust']['custregno'];
                                        $inquiry->pdf_url = $response['response']['pdf'];
                                        $inquiry->servicecode = $response['response']['service']['servicecode'];
                                        $inquiry->service_detail_date = $response['response']['service']['servicedetaildate'];
                                        $inquiry->inquiry = json_encode($response['response']);
                                        $inquiry->statusid = 1;
                                        $inquiry->save();

                                        $negdipay->statusid = 1;
                                        $msg = 'Лавлагаа амжилттай авлаа';
                                    } else {
                                        $msg = 'Лавлагаа авах амжилтгүй боллоо. Суурь системд алдаа гарлаа.';
                                        throw new MeException($response['response']['responsemsg']);
                                    }
                                } else {
                                    $msg = 'Лавлагаа авах амжилтгүй боллоо. Суурь системд алдаа гарл��а.';
                                    throw new MeException('RC000212', ['id' => $negdipay->inquiry_id]);
                                }
                            } else {
                                $msg = 'Лавлагаа авах амжилтгүй боллоо. Суурь системд алдаа гарлаа.';
                                throw new MeException('RC000211');
                            }
                        } catch (Exception $ex) {
                            Log::error($ex);
                            ApCustInquiry::where('id', $negdipay->inquiry_id)
                                ->update(['statusid' => 2]);
                        }
                    } else {
                        if ($polaris->is_use_cust_susp_acnt == 1 || $polaris->is_use_cust_susp_acnt == '1') {
                            $casaAcnt = ApAcntDp::where('prod_code', $polaris->susp_acnt_prod_code)
                                ->whereIn('status', ['O', '4'])->where('instid', $this->instid)
                                ->where('cust_code', $cust->cif)
                                ->orderBy('acnt_code', 'desc')->first();
                            if (empty($casaAcnt)) {
                                throw new MeException('RC000209');
                            }
                            $contaccountno = $casaAcnt->acnt_code;
                        } else {
                            $contaccountno = $polaris->repay_susp_accountno;
                        }

                        $prodcode = null;

                        $account = ApAcntLn::where('acnt_code', $negdipay->to_account)->where('instid', $this->instid)->first();

                        if (isset($account)) {
                            $prodcode = $account->prod_code;
                        }

                        if (empty($contaccountno)) {
                            throw new MeException('RC000209');
                        }
                        $acntService = new ApAcntService();
                        try {
                            $tran1_data = $acntService->internalTocasaTran([
                                "txnAcntCode" => $this->internalAccount,
                                "txnAmount" => $negdipay->amount,
                                "rate" => 1,
                                "contAcntCode" => $contaccountno,
                                "curCode" => $negdipay->cur_code,
                                "contAmount" => $negdipay->amount,
                                "contRate" => 1,
                                "txnDesc" => $negdipay->description ?? 'Шимтгэл',
                                "instid" => $this->instid,
                                "userid" => $negdipay->created_by,
                                "txn_date" => $sysDate,
                                "prodcode" => $prodcode,
                            ]);

                            $lnService = new ApLoanService();

                            $tran1 = ApTxnJournal::where('core_jrno', $tran1_data['txnJrno'])->where('instid', $this->instid)->first();

                            try {
                                // Зээл хаах болон төлөлт хийх
                                $respdata = $lnService->paymentLoan(
                                    $this->instid,
                                    $negdipay,
                                    $contaccountno,
                                    null,
                                    null,
                                    [
                                        'txn_date' => $sysDate,
                                    ]
                                );

                                $negdipay->jrno = $respdata['txnJrno'] ?? 0;
                                $negdipay->statusid = 1;
                                $tran1->statusid = 1;
                                $tran1->err_desc = '';
                                $tran1->parent_jrno = $respdata['txnJrno'];
                                $tran1->save();
                                $msg = $negdipay->to_account . ' данс амжилттай зээл төлөлт хийгдлээ.';
                            } catch (Exception $ex) {
                                // Эсрэг гүйлгээ хийх
                                // Log::info("Эсрэг гүйлгээ хийх хэсэгрүү орлоо.");
                                Log::error($ex);
                                $negdipay->statusid = 2;
                                $msg = 'Зээл төлөлт амжилтгүй. Суурь системд алдаа гарлаа.';

                                $req_data = [
                                    'orgJrno' => $tran1_data['txnJrno'],
                                    'txnDesc' => 'Буцаалт - Зээл төлөлт амжилтгүй болов.'
                                ];

                                try {
                                    $respdata = $lnService->oppositeTran($req_data, $polaris, $this->instid);
                                    $tran1->statusid = 3;
                                    $tran1->core_corr_jrno = $respdata['txnJrno'];
                                    $tran1->err_desc = 'Шимтгэлийн гүйлгээ амжилтгүй болсон учир буцаалт хийв.';
                                    $tran1->save();
                                } catch (\Throwable $th) {
                                    Log::debug($th);
                                    $tran1->err_desc = 'Core системийн гүйлгээний
                                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $tran1_data['txnJrno'];
                                    $tran1->save();
                                }
                            }
                        } catch (Exception $ex) {
                            $negdipay->statusid = 2;
                            $msg = 'Зээл төлөлт амжилтгүй. Суурь системд алдаа гарлаа.';
                            Log::error($ex);
                            throw $ex;
                        }
                    }
                } else if (
                    gettype($responseData) === 'array'
                    && isset($responseData['order'])
                    && @$responseData['order']['status'] != 'Approved' && $negdipay->statusid != 1
                ) {
                    $negdipay->status = $responseData['order']['status'];
                    $negdipay->save();
                }
            } catch (\Throwable $th) {
                if ($websocketid) {
                    try {
                        $tmpdata = [
                            'time' => Carbon::now(),
                            'id' => $websocketid,
                            'status' => 0,
                            'processName' => $th->getMessage(),
                            'responseCode' => ResponseCodeEnum::sys_error,
                            'stage' => 2,
                        ];
                        event(new ApTxnMonitoringEvent($tmpdata, $this->instid));
                        event(new ApTxnMonitoringEvent($tmpdata, 1));
                    } catch (Exception $ex) {
                        Log::debug($ex);
                    }
                }
                throw new MeException('RC000223');
            }

            if ($websocketid) {
                try {
                    $tmpdata = [
                        'time' => Carbon::now(),
                        'id' => $websocketid,
                        'status' => 0,
                        'processName' => $msg,
                        'responseCode' => ResponseCodeEnum::success,
                        'stage' => 2,
                    ];
                    event(new ApTxnMonitoringEvent($tmpdata, $this->instid));
                    event(new ApTxnMonitoringEvent($tmpdata, 1));
                } catch (Exception $ex) {
                    Log::debug($ex);
                }
            }

            try {
                $service = new AdNotificationService($this->instid);
                $notif = $service->createMainNotif([
                    'title' => $title ?? '',
                    'description' => $msg ?? '',
                    'instid' => $this->instid,
                    'created_by' => $negdipay->created_by,
                    'statusid' => 1,
                    'usetemp' => 0,
                    'notiftype' => "PUSH",
                    'execfreq' => 1,
                    'reportActionCode' => 0,
                ]);

                $custuser = VwAdNotificationUsers::where('type', "MEAPP")->where('instid', $this->instid)
                    ->where('custid', $user->id)->where('statusid', '<>', -1)->first();

                if ($custuser) {
                    $service->sendNotification($notif, $custuser, []);
                }
            } catch (Exception $ex) {
                Log::error($ex);
                throw new MeException("RC000003");
            }

            $negdipay->save();
            return array(
                'statusid' => $negdipay->statusid,
                'txn_type' => $negdipay->txn_type,
            );
        } else {
            throw new MeException('RC000010', ['id' => $transactionId]);
        }
    }

    public function correctionTransaction()
    {
        $data = [];
        $data['username'] = $this->username;
        $data['password'] = $this->password;
        $data['amount'] = 500;
        $data['tranid'] = 578;

        return $this->postApi('/api/pay/ec1099', $data);
    }


    /**
     * createInvoice - QPAY Нэхэмжлэх үүсгэх
     * @param  mixed $data
     */
    public function createInvoice($data)
    {
        $txndesc = '';
        if (@$data['typeid'] == 2 || @$data['typeid'] == 3) {
            $txndesc = 'Орлогын гүйлгээ';
        } else if ($data['typeid'] == 4) {
            $txndesc = 'Лавлагаа авах';
        }
        
        $data['ordertype'] = $this->ordertype_qpay;
        $data['terminalid'] = $this->terminalid;
        $data['username'] = $this->username;
        $data['password'] = $this->password;
        $data['returnurl'] = $this->returnurl . 'qpay/' . $data['instid'];
        // $data['returnurl'] = 'http://localhost:4001/api/v1/negdi/payment/qpay/' . $data['instid'];
        $data['amount'] = $data['amount'];
        $data['currency'] = $this->cur_code;
        $data['ordernum'] = $data['contAcntCode'];
        $data['to_account'] = $data['contAcntCode'];
        $data['description'] = $txndesc;
        $data['txn_type'] = $data['typeid'];

        $response = $this->postApi('/api/pay/ec1010', $data);

        if (!empty($response['order']['tranid'])) {
            $data['tranid'] = $response['order']['tranid'];
            $data['checkid'] = $response['order']['checkid'];
            $data['negdiurl'] = $response['order']['negdiurl'];
            $data['status'] = $response['order']['status'];
            
            $this->store($data);
        }

        return [ 'data' => $response ];
    }

}
