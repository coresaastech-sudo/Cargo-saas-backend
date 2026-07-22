<?php

namespace Modules\Ap\Http\Services;

use App\Events\ApTxnMonitoringEvent;
use App\Exceptions\MeException;
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
use Modules\Ap\Entities\ApCustInquiry;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApCustUser;
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

class ApQpayService
{

    private $host_merchant_v2 = 'https://merchant.qpay.mn';
    private $username = '';
    private $password = '';
    private $invoice_code = '';
    private $invoice_code_line = null;
    private $invoice_code_deposit_income = null;
    private $txndesc = '';
    public $callbackUrl;
    public $token;
    private $instid;
    private $is_service_suspended = 0;
    private $suspend_until = null;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct($instid)
    {
        // $pp = ProviderParam::where("name", 21)->first();
        $this->instid = $instid;
        $pp = GPProviderConf::where("code", "QPAY")->where('instid', $instid)->where('statusid', 1)->first();
        if (empty($pp)) {
            throw new MeException('RC000202', ['type' => "QPAY"]);
        }
        $qpay = json_decode($pp->config);
        if (!$qpay) {
            throw new MeException('RC000202', ['type' => "QPAY"]);
        }

        $this->invoice_code = @$qpay->invoice_code;
        $this->invoice_code_line = @$qpay->invoice_code_line;
        $this->invoice_code_deposit_income = @$qpay->invoice_code_deposit_income;
        $this->txndesc = $qpay->txndesc;
        $this->callbackUrl = $qpay->callbackUrl;
        $this->username = $qpay->username;
        $this->password = safeDecrypt($pp->sec1);
        $this->is_service_suspended = @$qpay->is_service_suspended ?? 0;
        $this->suspend_until = @$qpay->suspend_until ?? null;
        $connConf = GPConnConf::where("id", $pp->connid)->where('instid', $instid)->first();
        if (!$connConf) {
            throw new MeException('RC000203', ['type' => "QPAY"]);
        }
        if (!$conn = json_decode($connConf->config)) {
            throw new MeException('RC000203', ['type' => "QPAY"]);
        }
        $this->host_merchant_v2 = $conn->url;
        $this->token = $this->getToken();
    }

    public function postApi($url, $params)
    {
        $url = $this->host_merchant_v2 . $url;
        $startTime = Carbon::now()->getTimestampMs();
        $r = GPLogRequestList::create([
            'userid' => 1,
            'request' => json_encode($params),
            'url' => $url,
            // 'responsecode',
            // 'responsetime',
            'method' => 'POST',
            'instid' => 1,
        ]);
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->post($url, $params);
        $r->update([
            'responsetime' => (Carbon::now()->getTimestampMs() - $startTime) / 1000,
            'response' => $response->body(),
            'responsecode' => $response->status(),
        ]);
        if ($response->status() == 200) {
            $data = json_decode((string) $response->body(), true);
            return [
                'data' => $data,
                'status' => $response->status(),
            ];
        } else {
            $data = $response->body();
            return [
                'data' => $data,
                'status' => 500,
            ];
        }
    }

    public function getToken()
    {
        $url = $this->host_merchant_v2 . '/v2/auth/token';
        $startTime = Carbon::now()->getTimestampMs();
        $r = GPLogRequestList::create([
            'userid' => 1,
            'url' => $url,
            // 'responsecode',
            // 'responsetime',
            'method' => 'POST',
            'instid' => 1,
        ]);
        $response = Http::withHeaders(
            [
                'Authorization' => 'Basic ' . base64_encode("$this->username:$this->password"),
            ]
        )->post($url);
        $r->update([
            'responsetime' => (Carbon::now()->getTimestampMs() - $startTime) / 1000,
            'response' => $response->body(),
            'responsecode' => $response->status(),
        ]);
        $token = json_decode((string) $response->getBody(), true);
        return $token['access_token'];
    }

    /**
     * createInvoice - QPAY Нэхэмжлэх үүсгэх
     *
     * @param  mixed $data
     */
    public function createInvoice($data, $type = null)
    {
        if ($this->is_service_suspended == 1) {
            if ($this->suspend_until) {
                try {
                    $suspendUntil = Carbon::createFromFormat('Y/m/d H:i:s', $this->suspend_until);
                    if (Carbon::now()->lt($suspendUntil)) {
                        throw new MeException('RC000268');
                    }
                } catch (\Exception $e) {
                    if ($e instanceof MeException) {
                        throw $e;
                    }
                    throw new MeException('RC000268');
                }
            } else {
                throw new MeException('RC000268');
            }
        }

        $txndesc = $this->txndesc;
        if (@$data['typeid'] == 2 || @$data['typeid'] == 3) {
            $txndesc = 'Орлогын гүйлгээ';
        } else if ($data['typeid'] == 4) {
            $txndesc = 'Лавлагаа авах';
        } else if ($data['typeid'] == 5) {
            $txndesc = 'Карт захиалга';
        }

        if ($type === 'LINE') {
            $data['invoice_code'] = $this->invoice_code_line ?? $this->invoice_code;
        } else if ($type === 'TD') {
            $data['invoice_code'] = $this->invoice_code_deposit_income ?? $this->invoice_code;
        } else {
            $data['invoice_code'] = $this->invoice_code;
        }

        $data['sender_invoice_no'] = random_number();
        $data['callback_url'] = $this->callbackUrl . $data['instid'] . '/' . $data['sender_invoice_no'];
        $data['invoice_receiver_code'] = 'terminal';
        $data['invoice_description'] = 'ME: ' . $txndesc;
        $data['to_account'] = $data['contAcntCode'];

        $response = $this->postApi('/v2/invoice', $data);

        if ($response['status'] == 200) {
            $data['invoice_id'] = $response['data']['invoice_id'];
            $data['qr_text'] = $response['data']['qr_text'];
            $data['qpay_shorturl'] = $response['data']['qPay_shortUrl'];
            $this->store($data);
        }
        return $response;
    }

    public function getInvoice($invoice_id)
    {
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->get($this->host_merchant_v2 . '/v2/invoice/' . $invoice_id);

        return json_decode((string) $response->getBody(), true);
    }

    public function getPayment($invoice_id)
    {
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->get($this->host_merchant_v2 . '/v2/payment/' . $invoice_id);

        return json_decode((string) $response->getBody(), true);
    }

    public function checkPayment($data)
    {
        $response = $this->postApi('/v2/payment/check', $data);
        return $response;
    }

    public function cancelPayment($payment_id, $data)
    {
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->delete($this->host_merchant_v2 . '/v2/payment/cancel/' . $payment_id, $data);

        return json_decode((string) $response->getBody(), true);
    }

    public function refundPayment($payment_id, $data)
    {
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->delete($this->host_merchant_v2 . '/v2/payment/refund/' . $payment_id, $data);

        return json_decode((string) $response->getBody(), true);
    }

    public function createEbarimt($data)
    {
        return $this->postApi('/v2/ebarimt/create', $data);
    }

    public function store($data)
    {
        $user = auth()->user();
        $qpay = new ApQpay();
        foreach ($qpay->getFillable() as $field) {
            if (array_key_exists($field, $data)) {
                $qpay->$field = $data[$field];
            }
        }
        $qpay->statusid = 0;
        $qpay->created_at = Carbon::now();
        $qpay->created_by = $user->id;
        $qpay->save();
        return $qpay;
    }

    public function callBackUrl($invoiceno, $callmobile = false)
    {

        $key = 'QPAY_' . $invoiceno;
        $lockFile = storage_path('app/' . $key . '.lock');
        $lock = fopen($lockFile, 'w+');
        if (flock($lock, LOCK_EX)) {
            if (config("app.env") == 'local') {
                $qpay = ApQpay::where('sender_invoice_no', $invoiceno)->first();
                $qpay->statusid = 0;
                $inquiry = ApCustInquiry::orderBy('id', 'DESC')->first();
                $qpay->inquiry_id = $inquiry->id;
                $qpay->save();
            } else {
                $qpay = ApQpay::where('sender_invoice_no', $invoiceno)->first();
            }
            if ($qpay) {
                if ($qpay->statusid == 1 || $qpay->statusid == 3) {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    return ['status' => $qpay->statusid];
                }
                $qpay->statusid = 3;
                $qpay->save();
            }
            flock($lock, LOCK_UN);
        }
        fclose($lock);
        $websocketid = 0;
        if ($qpay) {
            $checkRequest = array(
                'object_type' => 'INVOICE',
                'object_id' => $qpay->invoice_id
            );
            $qpay->callbacked_at = Carbon::now();
            if (config('app.env') == 'local') {
                $responseData = [
                    'status' => 200,
                    'data' => [
                        "count" => 1,
                        "paid_amount" => $qpay->amount,
                        "rows" => [
                            [
                                "payment_id" => "493622150113497",
                                "payment_status" => "PAID",
                                "payment_amount" => $qpay->amount,
                                "trx_fee" => "0.00",
                                "payment_currency" => "MNT",
                                "payment_wallet" => "Хаан банк апп",
                                "payment_type" => "P2P",
                                "next_payment_date" => null,
                                "next_payment_datetime" => null,
                                "card_transactions" => [],
                                "p2p_transactions" => [
                                    [
                                        "transaction_bank_code" => "050000",
                                        "account_bank_code" => "050000",
                                        "account_bank_name" => "Хаан банк",
                                        "account_number" => "50*******",
                                        "status" => "SUCCESS",
                                        "amount" => $qpay->amount,
                                        "currency" => "MNT",
                                        "settlement_status" => "SETTLED"
                                    ]
                                ]
                            ]
                        ]

                    ]
                ];
            } else {
                $responseData = $this->checkPayment($checkRequest);
            }

            if ($responseData['status'] == 200) {
                $responseData = $responseData['data'];
            } else {
                return;
            }
            $qpay->checked_rows = json_encode($responseData['rows']);
            $qpay->checked_count = $responseData['count'];
            $qpay->checked_date = Carbon::now();
            $trans = $responseData['rows'];
            if ($responseData['count'] < 1) {
                $qpay->statusid = 0;
                $qpay->save();
                return ['status' => $qpay->statusid];
            }
            // QPAY гүйлгээ амжилттай болох
            if ($responseData['count'] > 0 && $qpay->statusid != 1) {
                $qpay->checked_paid_amount = $responseData['paid_amount'];
                $p2p = $trans[0]['p2p_transactions'] ?? [];
                $msg = '';
                if (!empty($qpay->created_by)) {
                    $user = ApCustUser::select('id', 'device_token', 'regno')->where('id', $qpay->created_by)->first();
                } else {
                    return;
                }
                $websocketid = rand(100, 999999);
                $inst = GPInstList::where('id', $this->instid)->first();
                switch ($qpay->typeid) {
                    case '2':
                    case '3':
                        $title = 'Орлогын гүйлгээ';
                        break;
                    case '4':
                        $title = 'Лавлагаа авах';
                        break;
                    default:
                        $title = 'Зээл төлөлт';
                        break;
                }
                try {
                    $tmpdata = [
                        'time' => Carbon::now(),
                        'id' => $websocketid,
                        'status' => 1,
                        'processName' => $title,
                        'txnName' => $title,
                        'createdBy' => $user->firstname,
                        'instName' => $inst->name,
                        'stage' => 1,
                        'channelName' => 'API'
                    ];
                    event(new ApTxnMonitoringEvent($tmpdata, $this->instid));
                    event(new ApTxnMonitoringEvent($tmpdata, 1));
                } catch (Exception $ex) {
                    Log::debug($ex);
                }

                try {
                    if (count($p2p) > 0) {
                        if ($p2p[0]['status'] == 'SUCCESS') {
                            $bankcode = substr($p2p[0]['account_bank_code'], 0, 2);
                            $bankaccount = $p2p[0]['account_number'] ?? '';
                            $pp = GPProviderConf::where("code", $bankcode)->where('statusid', 1)->where('instid', $this->instid)->first();
                            $providerBank = json_decode($pp->config, true);
                            if (!isset($providerBank['internal_bank_account_no'])) {
                                throw new MeException('RC000208');
                            }
                            $contaccountno = '';
                            $cust = ApCustomer::where('instid', $this->instid)->where('regno', $user->regno)->where('statusid', 1)->first();
                            if (empty($cust)) {
                                throw new MeException('RC000197');
                            }
                            $polaris = new PolarisApiRequestService($this->instid);

                            $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $this->instid]));

                            if ($qpay->typeid == '2') {
                                try {
                                    $acntService = new ApAcntService();

                                    $prodcode = null;

                                    $account = ApAcntDp::where('acnt_code', $qpay->to_account)->where('instid', $this->instid)->first();

                                    if (isset($account)) {
                                        $prodcode = $account->prod_code;
                                    }
                                    $respdata = $acntService->tdQpayTransaction(
                                        [
                                            "txnAcntCode" => $providerBank['internal_bank_account_no'],
                                            "txnAmount" => $qpay->amount,
                                            "rate" => 1,
                                            "contAcntCode" => $qpay->to_account,
                                            "contAmount" => $qpay->amount,
                                            "contRate" => 1,
                                            "txnDesc" => $qpay->invoice_description ?? 'Орлогын гүйлгээ',
                                            "instid" => $this->instid,
                                            "userid" => $qpay->created_by,
                                            "curCode" => $qpay->cur_code,
                                            "txn_date" => $sysDate,
                                            "cust" => $cust,
                                            "prodcode" => $prodcode,
                                        ]
                                    );

                                    $qpay->jrno = $respdata['txnJrno'] ?? 0;
                                    $qpay->statusid = 1;
                                    $msg = 'Хадгаламжийн дансруу амжилттай орлого хийгдлээ.';
                                } catch (Exception $ex) {
                                    $qpay->statusid = 2;
                                    $msg = 'Хадгаламжийн дансруу орлого хийх үйлдэл амжилтгүй. Суурь системд алдаа гарлаа.';
                                    Log::error($ex);
                                }
                            } else if ($qpay->typeid == '3') {
                                try {
                                    $prodcode = null;

                                    $account = ApAcntDp::where('acnt_code', $qpay->to_account)->where('instid', $this->instid)->first();

                                    if (isset($account)) {
                                        $prodcode = $account->prod_code;
                                    }

                                    $acntService = new ApAcntService();
                                    $respdata = $acntService->CasaQpayTransaction(
                                        [
                                            "txnAcntCode" => $providerBank['internal_bank_account_no'],
                                            "txnAmount" => $qpay->amount,
                                            "rate" => 1,
                                            "contAcntCode" => $qpay->to_account,
                                            "contAmount" => $qpay->amount,
                                            "contRate" => 1,
                                            "txnDesc" => $qpay->invoice_description ?? 'Орлогын гүйлгээ',
                                            "instid" => $this->instid,
                                            "userid" => $qpay->created_by,
                                            "txn_date" => $sysDate,
                                            "curCode" => $qpay->cur_code,
                                            "cust" => $cust,
                                            "prodcode" => $prodcode,
                                        ]
                                    );

                                    $qpay->jrno = $respdata['txnJrno'] ?? 0;
                                    $qpay->statusid = 1;
                                    $msg = 'Харилцах дансруу амжилттай орлого хийгдлээ.';
                                } catch (Exception $ex) {
                                    $qpay->statusid = 2;
                                    $msg = 'Харилцах дансруу орлого хийх үйлдэл амжилтгүй. Суурь системд алдаа гарлаа.';
                                    Log::error($ex);
                                }
                            } else if ($qpay->typeid == '4') {
                                try {
                                    // $userid = auth()->user()->id;
                                    $instconst = GPInstConst::where('code', 'MAIN_APP_INST_ID')
                                        ->where('statusid', '<>', -1)
                                        ->first();

                                    if ($instconst) {
                                        $onlineteller = CoreService::getInstGp($qpay['instid'], 'ONLINETELLERNUMBER');

                                        $service = new AdCreditInfoBueroService($instconst->value, $onlineteller);

                                        $inquiry = ApCustInquiry::where('id', $qpay->inquiry_id)
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

                                                $qpay->statusid = 1;
                                                $msg = 'Лавлагаа амжилттай авлаа';
                                            } else {
                                                $msg = 'Лавлагаа авах амжилтгүй боллоо. Суурь системд алдаа гарлаа.';
                                                throw new MeException($response['response']['responsemsg']);
                                            }
                                        } else {
                                            $msg = 'Лавлагаа авах амжилтгүй боллоо. Суурь системд алдаа гарлаа.';
                                            throw new MeException('RC000212', ['id' => $qpay->inquiry_id]);
                                        }
                                    } else {
                                        $msg = 'Лавлагаа авах амжилтгүй боллоо. Суурь системд алдаа гарлаа.';
                                        throw new MeException('RC000211');
                                    }
                                } catch (Exception $ex) {
                                    Log::error($ex);
                                    ApCustInquiry::where('id', $qpay->inquiry_id)
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

                                $account = ApAcntLn::where('acnt_code', $qpay->to_account)->where('instid', $this->instid)->first();

                                if (isset($account)) {
                                    $prodcode = $account->prod_code;
                                }

                                if (empty($contaccountno)) {
                                    throw new MeException('RC000209');
                                }
                                $acntService = new ApAcntService();
                                try {
                                    $tran1_data = $acntService->internalTocasaTran([
                                        "txnAcntCode" => $providerBank['internal_bank_account_no'],
                                        "txnAmount" => $qpay->amount,
                                        "rate" => 1,
                                        "contAcntCode" => $contaccountno,
                                        "curCode" => $qpay->cur_code,
                                        "contAmount" => $qpay->amount,
                                        "contRate" => 1,
                                        "txnDesc" => $qpay->invoice_description ?? 'Шимтгэл',
                                        "instid" => $this->instid,
                                        "userid" => $qpay->created_by,
                                        "txn_date" => $sysDate,
                                        "prodcode" => $prodcode,
                                    ]);

                                    $lnService = new ApLoanService();

                                    $tran1 = ApTxnJournal::where('core_jrno', $tran1_data['txnJrno'])->where('instid', $this->instid)->first();

                                    try {
                                        // Зээл хаах болон төлөлт хийх
                                        $respdata = $lnService->paymentLoan(
                                            $this->instid,
                                            $qpay,
                                            $contaccountno,
                                            $bankcode,
                                            $bankaccount,
                                            [
                                                'txn_date' => $sysDate,
                                            ]
                                        );

                                        $qpay->jrno = $respdata['txnJrno'] ?? 0;
                                        $qpay->statusid = 1;
                                        $tran1->statusid = 1;
                                        $tran1->err_desc = '';
                                        $tran1->parent_jrno = $respdata['txnJrno'];
                                        $tran1->save();
                                        $msg = 'Зээл төлөлт амжилттай.';
                                    } catch (Exception $ex) {
                                        // Эсрэг гүйлгээ хийх
                                        // Log::info("Эсрэг гүйлгээ хийх хэсэгрүү орлоо.");
                                        Log::error($ex);
                                        $qpay->statusid = 2;
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
                                    $qpay->statusid = 2;
                                    $msg = 'Зээл төлөлт амжилтгүй. Суурь системд алдаа гарлаа.';
                                    Log::error($ex);
                                    throw $ex;
                                }
                            }
                        }
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
                    throw $th;
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
                        'title' => $title,
                        'description' => $msg,
                        'instid' => $this->instid,
                        'created_by' => $qpay->created_by,
                        'statusid' => 1,
                        'usetemp' => 0,
                        'notiftype' => "PUSH",
                        'execfreq' => 1,
                        'reportActionCode' => 0,
                    ]);

                    if ($qpay->typeid == '4') {
                        $custuser = VwAdNotificationUsers::where('type', "MEAPP")->where('custid', $user->id)->where('statusid', '<>', -1)->first();
                    } else {
                        $custuser = VwAdNotificationUsers::where('type', "MEAPP")->where('instid', $this->instid)
                            ->where('custid', $user->id)->where('statusid', '<>', -1)->first();
                    }

                    if ($custuser) {
                        $service->sendNotification($notif, $custuser, []);
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                }

                Log::info($invoiceno . ' Гүйлгээ аль хэдийн хийгдсэн эсвэл QPAY гүйлгээ хүлээгдэж байна.');
            }
            if ($qpay->statusid == 3) {
                $qpay->statusid = 0;
            }
            $qpay->save();

            if ($qpay->typeid == '4') {
                return  ['status' => $qpay->statusid, 'pdf_url' => $inquiry->pdf_url];
            } else {
                return  ['status' => $qpay->statusid];
            }
        }
        return ['status' => 0];
    }


    public function internalTxn($data)
    {
        try {
            $cashController = new IaTxnService();
            $tP = $cashController->setParamToJrnEntity($data);
            return $cashController->doInternalToInternal($tP)->jsonSerialize();
        } catch (Exception $e) {
            throw $e;
        }
    }


    public function chargeAccount($param)
    {
        /* гүйлгээ хийх Start*/
    }

    public function paymentInvoice($param) {}

    public function responseRef($param)
    {
        /* гүйлгээ хийх Start*/
    }
}
