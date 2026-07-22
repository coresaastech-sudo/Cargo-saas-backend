<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCgwTxnDescCombination;
use Modules\Ad\Entities\AdCorporateGateway;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdCgwTransaction;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Cr\Entities\CrCustInd;
use Modules\Dp\Entities\DpAccount;
use Modules\Gp\Entities\GPInstCurRate;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ia\Entities\IaAccount;
use Modules\Ln\Entities\LnAccount;
use Modules\Tr\Http\Services\DpTxnService;
use Modules\Tr\Http\Services\IaTxnService;
use Modules\Tr\Http\Services\LnTxnService;
use PDO;

class AdCorporateGatewayStateService extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public $providerConfig;
    public $provider;
    public $connection;
    public $instid;
    public $userid;
    private $token;
    protected $invoiceservice;

    /**
     * "khan_credit_accountno": "44000003", -> Хаан банкнаас Гүйлгээ хийх дотоодын данс
     * "tdb_credit_accountno": "44000002", -> ХХБ Гүйлгээ хийх дотоодын данс
     * "golomt_credit_accountno": "44000001", -> Голомт Гүйлгээ хийх дотоодын данс
     * "transfer_internal": 1, -> Дотоодын данс руу дансны дугаараар гүйлгээ хийх
     * "corp_username": "123", -> Корп нэвтрэх нэр
     * "prodcode": "DP000001", -> Гүйлгээ хийх бүтээгдэхүүний код
     * "check_system_date": 0, -> Системын огноог шалгах
     * "pay_loan": 1 -> Зээлийг шууд төлөх эсэх
     * "systemCode": "ESYS8564"
     */
    public function __construct($instid, $userid)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->provider = VwGPProviderConf::where('code', '34')->where('instid', $instid)->first();
        if (isset($this->provider)) {
            $connConf = VwGPConnConf::where('id', $this->provider->connid)->where('instid', $instid)->first();

            $this->providerConfig = json_decode($this->provider->config, true);
            if (isset($connConf)) {
                $this->connection = json_decode($connConf->config, true);
            } else {
                throw new MeException('RC000174');
            }
        } else {
                        throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '34'
            ]);
        }
    }

    public function getToken()
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->connection['url'] . '/Token/GetToken', [
            'username' => $this->providerConfig['corp_username'],
            'password' => safeDecrypt($this->provider['sec1'])
        ]);

        // Response статус шалгах
        if (!$response->successful()) {
            Log::error("Token API request failed", [
                'url' => $this->connection['url'] . '/Token/GetToken',
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new MeException('RC000005');
        }

        $token = $response->json();

        return $token['accessToken'];
    }

    /**
     * Дансны хуулга авах
     * acntNo - Дансны дугаар
     * startDate - Эхлэх огноо. Формат: yyyy/MM/dd
     * endDate - Дуусах огноо. Формат: yyyy/MM/dd
     */
    public function getAccountStatement($accountNumber, $startDate = '', $endDate = '')
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $requestData = [
            'acntNo'   => $accountNumber,
            'startDate' => $startDate,
            'endDate'   => $endDate,
            'reList' => []
        ];


        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $this->connection['url'] . '/Statement/Statements';
        $r->method = 'POST';
        $r->request = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $r->save();

        $response = Http::withHeaders([
            'Authorization' => $this->token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($r->url, $requestData);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode($response->getBody(), true);
    }


    /**
     * Mobile app дээр ашиглаж байгаа
     * ТӨРИЙН БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ
     * CG007 – ТӨРИЙН БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ /DOMESTIC TRANSFER/
     *
     * @param  mixed $formattedData = [
     *  "txnNo": "String",
     *  "fromAcntNo": "String",
     *  "fromAcntNoPin": "String",
     *  "toAcntNo": "String",
     *  "amount": "Double",
     *  "curCode": "String",
     *  "txnDesc": String,
     * ]
     * @return void
     */
    public function transactionDemostic($senddata)
    {
        if (($key = array_search('toAccountName', $senddata)) !== false) {
            unset($senddata[$key]);
        }
        if (($key = array_search('toBank', $senddata)) !== false) {
            unset($senddata[$key]);
        }
        $senddata['fromAccount'] = $this->providerConfig['account_no'];

        return $this->transService($senddata,  '/Transaction/InsideBank');
    }

    /**
     * Mobile app дээр ашиглаж байгаа
     * transInterBank - БУСАД БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ
     * CG008 – БУСАД БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ /INTERBANK TRANSFER/
     *
     * @param  mixed $formattedData [
     *  "txnNo": "String",
     *  "fromAcntNo": "String",
     *  "fromAcntNoPin": "String",
     *  "toAcntNo": "String",
     *  "rcvBankNo": "String",
     *  "rcvAcntName": "String",
     *  "amount": "Double",
     *  "curCode": "String",
     *  "txnDesc": String,
     * ]
     * @return void
     */
    public function transInterBank($senddata)
    {
        if (isset($senddata['toBank']) && strlen($senddata['toBank']) == 2) {
            $senddata['toBank'] = $senddata['toBank'] . '0000';
        }
        $senddata['fromAccount'] = $this->providerConfig['account_no'];

        // Төрийн банкнаас дансны нэр баталгаажуулж чадахгүй байгаа учраас toAccountName parameter шалгав
        if (!isset($senddata['toAccountName']) || empty($senddata['toAccountName'])) {
            throw new MeException('RC000216');
        }

        return $this->transService($senddata, '/Transaction/InterBank');
    }


    public function transService($senddata, $url)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();
        $isInterbank = strpos($url, 'InterBank') !== false;

        // Банк хооронд болон доторх гүйлгээний хүсэлт угсарч байна.
        $requestData = [
            'txnNo' => $senddata['bankjrno'], // Гүйлгээний дугаар
            'fromAcntNo' => $senddata['fromAccount'],
            'fromAcntNoPin' => safeDecrypt($this->provider['sec2']),
            'toAcntNo' => $senddata['toAccount'],
            'amount' => $senddata['amount'],
            'curCode' => $senddata['currency'],
            'txnDesc' => $senddata['description'],
        ];

        if ($isInterbank) {
            $requestData['rcvBankNo'] = $senddata['toAccountName'];
            $requestData['rcvAcntName'] = $senddata['toAccountName'];
        }

        // Ямар нэгэн тохиолдлоор алдаатай болчихвол
        $senddata['statusid'] = 2;
        $user = auth()->user();
        $r = new GPLogRequestList();

        $cgwTransaction = $this->createTransaction($senddata);
        try {
            $r->userid = $user ? $user->userid : 1;
            $r->url = $this->connection['url'] . $url;

            $r->method = 'POST';
            $r->request = json_encode($senddata, JSON_UNESCAPED_UNICODE);
            $r->save();

            $response = Http::withHeaders([
                'Authorization' => $this->token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->post($this->connection['url'] . $url, $requestData);
        } catch (Exception $ex) {
            Log::error($ex);
            $r->response = $ex->getMessage();
            $r->responsecode = $response->status() ?? 500;
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();

            throw new MeException('Банкны гүйлгээ хийх үед алдаа гарлаа.');
        }
        $rstatus = $response->status();
        if ($rstatus == 200) {
            $body = json_decode((string) $response->getBody(), true);
            if (!empty($body['JrNo'])) {
                $cgwTransaction['jrno'] = $body['JrNo'];
                $cgwTransaction['statusid'] = 1;
                $cgwTransaction->save();
            }
        } else {
            $body = $response->body();
            $transactions = $this->getAccountStatement($senddata['fromAccount'], $senddata['startDate'], $senddata['endDate']);

            if (is_array($transactions) && $transactions['transactions']) {
                foreach ($transactions['transactions'] as $transaction) {
                    try {
                        if (isset($transaction['description'])) {
                            if ($transaction['amount'] < 0) {
                                $transaction['amount'] = $transaction['amount'] * -1;
                            }
                            if ($transaction['amount'] == $cgwTransaction['amount']) {
                                if ($transaction['amount'] == $cgwTransaction['amount']) {
                                    if (preg_match('/CODE: (\d+)/', $transaction['description'], $matches)) {
                                        $code = $matches[1];
                                        if (($cgwTransaction['transferid'] . '') == ($code . '')) {
                                            $rstatus = 200;
                                            $cgwTransaction['jrno'] = $transaction['JrNo'];
                                            $cgwTransaction['statusid'] = 1;
                                            $cgwTransaction->save();
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $ex) {
                        Log::debug($ex);
                    }
                }
            }
        }
        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();
        if ($rstatus != 200) {
            throw new MeException('Банкны гүйлгээ хийх үед алдаа гарлаа.');
        } else {
            return $body;
        }
    }

    /**
     * Mobile app дээр ашиглаж байгаа
     * createTransaction - CGW руу хийх гүйлгээний бичилт
     *
     * @param  mixed $senddata [
     *  "journal_no": "string",
     *  "fromAccount": "string",
     *  "toAccount": "string",
     *  "toCurrency": "string",
     *  "toAccountName": "string",
     *  "toBank": "string",
     *  "amount": decimal,
     *  "description": "string",
     *  "currency": "string",
     *  "transferid":" string ",
     *  "system_date":" date",
     *  "uuid":" string"
     * ]
     * @return AdCgwTransaction
     */

    public function createTransaction($data)
    {
        $bankAccount = ApCustBankAccount::where('acnt_code', $data['toAccount'])
            ->where('statusid', '<>', -1)
            ->first();

        $cgwTransaction = AdCgwTransaction::create([
            'jrno' => $data['journal_no'] ?? null,
            'from_account' => $data['fromAccount'] ?? null,
            'amount' => $data['amount'],
            'curcode' => $data['currency'],
            'description' => $data['description'] ?? null,
            'to_bank' => $data['toBank'] ?? '05',
            'to_account' => $data['toAccount'],
            'to_account_name' => $bankAccount->acnt_name ?? null,
            'transferid' => $data['transferid'] ?? null,
            'system_date' => $data['system_date'] ?? Carbon::now(),
            'uuid' => $data['uuid'] ?? '0',
            'source' => 1, // 1 - MeApp
            'statusid' => $data['statusid'],
            'instid' => $this->instid,
            'created_by' => $this->userid,
        ]);
        return $cgwTransaction;
    }

    /**
     * Mobile app дээр ашиглаж байгаа
     * getNameByAcc - CG006 - ДАНСНЫ ЭЗЭМШИГЧИЙН НЭР сервис
     */
    public function getNameByAcc($account, $bankcode)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $url = $this->connection['url'] . '/EGW_ACCOUNT/account/accountname';

        $requestData = [
            'acntCode' => $account,
            'bankId' => $bankcode,
        ];

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $url;
        $r->method = 'POST';
        $r->request = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $r->save();

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->providerConfig['corp_username'] . ':' . safeDecrypt($this->provider['sec1'])),
            'Content-Type' => 'application/json; charset=UTF-8',
            'accept' => 'application/json',
            'requestId' => '2',
            'systemCode' => $this->providerConfig['systemCode'],
        ])->post($url, $requestData);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode($response->getBody(), true);
    }

    /**
     * @example  $data
     * {
     *    "JrNo": "937490761920",
     *    "JrItemNo": "3",
     *    "AcntNo": "108900030783",
     *    "CurCode": "MNT",
     *    "TxnType": "0",
     *    "Amount": 1000.0,
     *    "Rate": 1.0,
     *    "Balance": 21250406.9,
     *    "TxnDate": "2025-08-31T00:00:00",
     *    "SysDate": "2025-08-31T01:37:06",
     *    "TxnDesc": "ДАНС ХӨТӨЛСНИЙ ШИМТГЭЛ - БАЙГУУЛЛАГА:[1000.00MNT]",
     *    "ContAcntNo": "SAFREQ001",
     *    "ContAcntName": null,
     *    "ContBankCode": null,
     *    "Location": null,
     *    "BranchNo": "3499",
     *    "Corr": "0"
     *},
     * $acntno => Данс байгууллагын дансны дугаар
     */
    public function checkStatement($data, $acntno)
    {
        $bankCode = '34'; // Төрийн банк код
        if ($data) {
            $tran = AdCorporateGateway::where('bankcode', $bankCode)
                ->where('bankjrno', $data['JrNo'])
                ->where('txnamount', $data['Amount'])
                ->when(!empty($data['ContAcntNo']), function ($query) use ($data) {
                    $acnt = $data['ContAcntNo'];
                    if (str_starts_with($acnt, 'MN') && strlen($acnt) >= 16) {
                        $shortAcnt = substr($acnt, 8);
                        return $query->where(function ($q) use ($acnt, $shortAcnt) {
                            $q->where('bankacntno', $acnt)
                              ->orWhere('bankacntno', $shortAcnt)
                              ->orWhere('bankacntno', 'like', '%' . $shortAcnt)
                              ->orWhereNull('bankacntno')
                              ->orWhere('bankacntno', '');
                        });
                    }
                    return $query->where(function ($q) use ($acnt) {
                        $q->where('bankacntno', $acnt)
                          ->orWhereNull('bankacntno')
                          ->orWhere('bankacntno', '');
                    });
                })
                ->where('statusid', '<>', -1)
                ->get();

            if ($tran->isEmpty()) {
                try {
                    $carbonDate = Carbon::parse($data['SysDate']);

                    $sign = $data['TxnType'] == 1 ? '+' : ($data['TxnType'] == 0 ? '-' : null);

                    $storeData = [
                        "instid" => $this->instid,
                        "bankcode" => $bankCode,
                        'banktxndate' => $carbonDate,
                        "bankacntno" => $data['ContAcntNo'] ?? null,
                        "bankfromacntno" => $acntno,
                        "sign" => $sign,
                        "bankjrno" => $data['JrNo'],
                        "txnamount" => $data['Amount'],
                        "curcode" =>  $data['CurCode'] ?? "MNT",
                        "txndesc" => $data['TxnDesc'],
                        "balance" => $data['Balance'],
                        'created_by' => $this->userid,
                    ];

                    $generalService = new AdCorporateGatewayService($this->instid, $this->userid, $this->providerConfig);
                    $corporateGateway = $generalService->storeCorporateGateway($storeData);

                    if ($data['Corr'] == 0) {
                        $generalService->processCorporateGateway($corporateGateway, $acntno);
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                }
            }
        }
    }
}
