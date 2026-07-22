<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCgwTransaction;
use Modules\Ad\Entities\AdCorporateGateway;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;


class AdCorporateGatewayXacService extends Controller
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
        $this->provider = VwGPProviderConf::where('code', '32')
            ->where('instid', $instid)
            ->first();
        if (isset($this->provider)) {
            $connConf = VwGPConnConf::where('id', $this->provider->connid)
                ->where('instid', $instid)
                ->first();

            $this->providerConfig = json_decode($this->provider->config, true);
            if (isset($connConf)) {
                $this->connection = json_decode($connConf->config, true);
            } else {
                throw new MeException('RC000174');
            }
        } else {
            throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '32'
            ]);
        }
    }

    /**
     * Дансны жагсаалтыг провайдерын тохиргооноос (providerConfig) авна.
     * CGW рүү HTTP хүсэлт явуулахгүй.
     *
     * Дэмжих формат:
     *  - providerConfig['accounts'] = [ { "account_no": "...", "currency": "MNT", ... }, ... ]
     *  - эсвэл providerConfig['account_no'] (нэг данс) — fallback
     */
    public function getAccountList()
    {
        $accounts = [];

        if (!empty($this->providerConfig['accounts']) && is_array($this->providerConfig['accounts'])) {
            $accounts = $this->providerConfig['accounts'];
        } elseif (!empty($this->providerConfig['account_no'])) {
            $accounts[] = [
                'account_no' => $this->providerConfig['account_no'],
                'currency'   => $this->providerConfig['currency'] ?? 'MNT',
            ];
        }

        return $accounts;
    }

    public function getAccountInfo($account)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $url = $this->connection['url'] . '/EGW_ACCOUNT/account/info?accountNumber=' . $account;

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $url;
        $r->method = 'GET';
        $r->save();

        $response = $this->getHttpClient()
            ->get($url);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode($response->getBody(), true);
    }


    /**
     * Дансны хуулга авах
     * startDate and endDate are required
     */
    public function getAccountStatement($account, $startDate, $endDate)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $url = $this->connection['url'] . '/EGW_ACCOUNT/account/statement';

        $requestData = [
            'account' => $account,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $url;
        $r->method = 'POST';
        $r->request = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $r->save();

        $response = $this->getHttpClient()
            ->post($url, $requestData);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode($response->getBody(), true);
    }


    /**
     * Mobile app дээр ашиглаж байгаа
     * ХАС БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ
     * CG007 – ХАС БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ /DOMESTIC TRANSFER/
     *
     * @param  mixed $formattedData = [
     *  "debitAccountId": "string",
     *  "creditAmount": "numeric",
     *  "creditAccountId": "string",
     *  "description": "string",
     *  "isCustomer": boolean,
     *  "creditCurrency": string,
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

        return $this->transService($senddata, '/EGW_TRANSACTION/transaction/internal');
    }

    /**
     * Mobile app дээр ашиглаж байгаа
     * transInterBank - БУСАД БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ
     * CG008 – БУСАД БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ /INTERBANK TRANSFER/
     *
     * @param  mixed $formattedData [
     *  "creditAmount": "numeric",
     *  "debitAccountId": "string",
     *  "creditAccountId": "string",
     *  "creditAccountName": "string",
     *  "bankId": "string",
     *  "creditCurrency": "string",
     *  "description": "string",
     *  "isCustomer": boolean,
     *  "cash": "string", Y or N  --not required
     *  "chargeWaiver": "string" Y or N --not required
     * ]
     * @return void
     */
    public function transInterBank($senddata)
    {
        if (isset($senddata['toBank']) && strlen($senddata['toBank']) == 2) {
            $senddata['toBank'] = $senddata['toBank'] . '0000';
        }
        $senddata['fromAccount'] = $this->providerConfig['account_no'];

        $bankAccount = ApCustBankAccount::where('acnt_code', $senddata['toAccount'])
            ->where('statusid', '<>', -1)
            ->first();

        if ($bankAccount) {
            $iban = $this->getNameByAcc($bankAccount['acnt_code'], $senddata['toBank']);
        } else {
            throw new MeException('RC000022');
        }

        if (isset($iban['acntName'])) {
            // Банкны дансны бүртгэл болон CGW-ээс ирсэн дансны нэрийг адил эсэхийг шалгаад таарч байвал ирсэн нэрийг ашиглах хэрэгтэй байх
            $senddata['toAccountName'] = $iban['acntName'];
            // $senddata['toAccount'] = $iban['iban'];
        } else {
            throw new MeException('RC000216');
        }

        return $this->transService($senddata, '/EGW_TRANSACTION/transaction/external');
    }


    public function transService($senddata, $url)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $isInterbank = strpos($url, 'external') !== false;

        // Банк хооронд болон доторх гүйлгээний хүсэлт угсарч байна.
        $requestData = [
            'debitAccountId' => $senddata['fromAccount'],
            'creditAccountId' => $senddata['toAccount'],
            'creditAmount' => $senddata['amount'],
            'description' => $senddata['description'],
            'creditCurrency' => $senddata['currency'],
        ];

        if ($isInterbank) {
            $requestData['creditAccountName'] = $senddata['toAccountName'];
            $requestData['bankId'] = $senddata['toBank'];
            $requestData['isCustomer'] = $senddata['isCustomer'] ?? false;
        } else {
            $requestData['isSystemToCustomer'] = true;
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

            $response = $this->getHttpClient()
                ->post($this->connection['url'] . $url, $requestData);
        } catch (Exception $ex) {
            Log::error($ex);
            $r->response = $ex->getMessage();
            $r->responsecode = 500;
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();

            throw new MeException('Банкны гүйлгээ хийх үед алдаа гарлаа.');
        }
        $rstatus = $response->status();
        if ($rstatus == 200) {
            $body = json_decode((string) $response->getBody(), true);
            if (!empty($body['journalNo'])) {
                $cgwTransaction['jrno'] = $body['journalNo'];
                $cgwTransaction['uuid'] = $body['uuid'] ?? "1";
                $cgwTransaction['transferid'] = $body['transferid'] ?? 1;
                $cgwTransaction['system_date'] = $body['systemDate'] ?? "";
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
                                            $cgwTransaction['jrno'] = $transaction['journal'];
                                            $cgwTransaction['system_date'] = $transaction['tranDate'] ?? "";
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

        $response = $this->getHttpClient()
            ->post($url, $requestData);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode($response->getBody(), true);
    }

    private function getHttpClient($requestId = null)
    {
        if (is_null($requestId)) {
            $requestId = (string) Carbon::now()->getTimestampMs();
        }

        $options = [
            'timeout' => 120,
            'curl' => [
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_FRESH_CONNECT => true,
            ],
        ];

        // SSL/TLS Certificates (Identity/KeyStore) from Provider Config
        $certPath = $this->providerConfig['cert_path'] ?? '';
        $certPassword = $this->providerConfig['cert_password'] ?? '';

        if (!empty($certPath)) {
            // Check if absolute path exists, if not, try relative to storage/app
            if (!file_exists($certPath)) {
                $certPath = storage_path('app/' . $certPath);
            }

            if (file_exists($certPath)) {
                if (str_ends_with(strtolower($certPath), '.p12')) {
                    $options['curl'][CURLOPT_SSLCERTTYPE] = 'P12';
                    $options['curl'][CURLOPT_SSLCERT] = $certPath;
                    $options['curl'][CURLOPT_SSLCERTPASSWD] = $certPassword;
                } else {
                    $options['cert'] = [$certPath, $certPassword];
                }
            }
        }

        // TrustStore (Server Verification)
        // Тэргүүлэх дараалал:
        //   1) providerConfig.verify_ssl === false  → verify=false (дотоод/self-signed CGW)
        //   2) ca_path байвал → тухайн CA-аар verify
        //   3) бусад тохиолдолд → системийн default CA-аар verify
        $verifySsl = $this->providerConfig['verify_ssl'] ?? true;
        if ($verifySsl === false || $verifySsl === 0 || $verifySsl === '0') {
            $options['verify'] = false;
        } else {
            $caPath = $this->providerConfig['ca_path'] ?? '';
            if (!empty($caPath)) {
                if (!file_exists($caPath)) {
                    $caPath = storage_path('app/' . $caPath);
                }
                $options['verify'] = file_exists($caPath) ? $caPath : true;
            } else {
                $options['verify'] = true;
            }
        }

        // TLS Version - Default to TLS 1.2
        $options['curl'][CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;

        // Hostname verify-ыг тусад нь унтраах боломж: CGW endpoint-д IP-ээр хандах ч
        // серверийн cert-ийн CN нь hostname (жнь "egateway") байх тохиолдолд хэрэгтэй.
        // verify_host=false үед cert итгэлийг (CA verify) хэвээр шалгана — зөвхөн
        // hostname-CN таарахыг алгасна.
        $verifyHost = $this->providerConfig['verify_host'] ?? true;
        if ($verifyHost === false || $verifyHost === 0 || $verifyHost === '0') {
            $options['curl'][CURLOPT_SSL_VERIFYHOST] = 0;
        }

        $username = $this->providerConfig['corp_username'] ?? '';
        $password = safeDecrypt($this->provider['sec1']) ?? '';
        $systemCode = $this->providerConfig['systemCode'] ?? '';

        return Http::withOptions($options)
            ->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json; charset=UTF-8',
                'accept' => 'application/json',
                'requestId' => $requestId,
                'systemCode' => $systemCode,
            ]);
    }

    /**
     * @example  $data
     * {
     *       "BRANCHNAME": "XACБАНК - ТӨВ АЛБА-100",
     *       "CUSTOMERNAME": "AMGALAN BALDAN",
     *       "ACCOUNTID": "5005835494",
     *       "CUSTOMERID": "10096535",
     *       "PRODUCTNAME": "ХАРИЛЦАХ ДАНС- ИРГЭН",
     *       "OPENDATE": "2024-08-13 08:00:00.0",
     *       "MATURITYDATE": "",
     *       "CURRENCY": "MNT",
     *       "RATE": 0.0,
     *       "TRXDATE": "",
     *       "VALUEDATE": "2025-03-10",
     *       "TRNBRANCHNAME": "",
     *       "CREDITAMOUNT": 0,
     *       "DEBITAMOUNT": 0,
     *       "DESCRIPTION": "Opening Balance",
     *       "EODBALANCE": 0,
     *       "TRNACNAME": "",
     *       "TRNACNO": "",
     *       "USERID": "",
     *       "BRANCHID": "100",
     *       "AU": "",
     *       "SERIAL": "",
     *       "CLOSINGBALANCE": 0,
     *       "OPENINGBALANCE": 0,
     *       "LIMIT_AMOUNT": "0",
     *       "AVAILABLE_LIMIT": "4218253272.97",
     *       "TRN_REF_NO": ""
     *   },
     * $acntno => Данс байгууллагын дансны дугаар
     */
    public function checkStatement($data, $acntno, $curcode = 'MNT')
    {
        $bankCode = '32'; // ХАС банк код
        if ($data) {
            $sign = '+';
            // amount талбарыг зөв авах: credit эсвэл debit-ээс эерэг утгыг авах
            if (!empty($data['CREDITAMOUNT']) && $data['CREDITAMOUNT'] > 0) {
                $amount = $data['CREDITAMOUNT'];
                $sign = '+';
            } elseif (!empty($data['DEBITAMOUNT'])) {
                // debit нь сөрөг эсвэл эерэг байж болно, үнэмлэхүй утгыг авна
                $amount = abs($data['DEBITAMOUNT']);
                $sign = '-';
            } else {
                $amount = 0;
            }

            $tran = AdCorporateGateway::where('bankcode', $bankCode)
                ->where('bankjrno', $data['TRN_REF_NO'])
                ->where('txnamount', $amount)
                ->where('bankacntno', $data['TRNACNO'] ?? '')
                ->where('statusid', '<>', -1)
                ->get();

            if ($tran->isEmpty() && isset($data['TRNACNO']) && isset($data['TRN_REF_NO'])) {
                try {
                    $carbonDate = Carbon::parse($data['TRXDATE']);

                    $storeData = [
                        "instid" => $this->instid,
                        "bankcode" => $bankCode,
                        'banktxndate' => $carbonDate,
                        "bankacntno" => $data['TRNACNO'],
                        "bankfromacntno" => $acntno,
                        "sign" => $sign,
                        "bankjrno" => $data['TRN_REF_NO'],
                        "txnamount" => $amount,
                        "curcode" => $curcode,
                        "txndesc" => $data['DESCRIPTION'],
                        "balance" => $data['EODBALANCE'],
                        'created_by' => $this->userid,
                    ];

                    $generalService = new AdCorporateGatewayService($this->instid, $this->userid, $this->providerConfig);
                    $corporateGateway = $generalService->storeCorporateGateway($storeData);

                    $generalService->processCorporateGateway($corporateGateway, $acntno);
                } catch (Exception $ex) {
                    Log::error($ex);
                }
            }
        }
    }
}
