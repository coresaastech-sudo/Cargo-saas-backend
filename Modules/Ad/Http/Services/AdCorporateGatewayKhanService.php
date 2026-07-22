<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCorporateGateway;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdCgwTransaction;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Http\Services\CoreService;

class AdCorporateGatewayKhanService extends Controller
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
    private $is_use_acnt_name;
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
     *
     */
    public function __construct($instid, $userid)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->is_use_acnt_name = false;
        $this->provider = VwGPProviderConf::where('code', '05')->where('instid', $instid)->first();
        if (isset($this->provider)) {
            $connConf = VwGPConnConf::where("id", $this->provider->connid)->where('instid', $instid)->first();

            $this->providerConfig = json_decode($this->provider->config, true);
            if (isset($connConf)) {
                $this->connection = json_decode($connConf->config, true);
            } else {
                throw new MeException("RC000174");
            }
        } else {
            throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '05'
            ]);
        }
    }

    public function getToken()
    {
        $response = Http::withHeaders(
            [
                'Authorization' => 'Basic ' . base64_encode($this->providerConfig['corp_username'] . ":" . safeDecrypt($this->provider['sec1'])),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->post($this->connection['url']  . '/v1/auth/token?grant_type=client_credentials');
        $token = json_decode((string) $response->getBody(), true);
        // Response статус шалгах
        if (!$response->successful()) {
            Log::error("Token API request failed", [
                'url' => $this->connection['url'] . '/v1/auth/token?grant_type=client_credentials',
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new MeException('RC000222', [
                'field' => $this->instid . ' дугаартай байгууллага CGW нэвтэрч чадахгүй байна.'
            ]);
        }

        return $token['access_token'];
    }

    public function getAccountList()
    {
        $this->token = $this->getToken();
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->get($this->connection['url']  . '/v1/accounts');

        return json_decode((string) $response->getBody(), true);
    }

    public function getAccountBalance($account)
    {
        $this->token = $this->getToken();
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->get($this->connection['url'] . '/v1/accounts/' . $account . '/balance');

        return json_decode((string) $response->getBody(), true);
    }

    public function getAccountInfo($account)
    {
        $this->token = $this->getToken();
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->get($this->connection['url']  . '/v1/accounts/' . $account . '/');

        return json_decode((string) $response->getBody(), true);
    }

    public function getAccountStatement($account)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;

        $date = new Carbon(CoreService::getTxnDate(auth()->user()->instid));

        if (auth()->user()->instid == 1) {
            $date =  Carbon::now();
        }

        $fromDate = $date->format('Ymd');

        $r->url = $this->connection['url'] . '/v1/statements/' . $account . '?from=' . $fromDate . '&to=' . $fromDate;
        $r->method = 'GET';
        $r->request = json_encode([], JSON_UNESCAPED_UNICODE);
        $r->save();

        $this->token = $this->getToken();
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->get($this->connection['url'] . '/v1/statements/' . $account . '?from=' . $fromDate . '&to=' . $fromDate);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @example  $data {
     *      "record": 2,
     *      "tranDate": "2022-02-08",
     *      "postDate": "2022-02-08",
     *      "time": "14082902",
     *      "branch": "5000",
     *      "teller": "99944",
     *      "journal": 8393028,
     *      "code": 4045,
     *      "amount": 100,
     *      "balance": 100,
     *      "debit": 0,
     *      "correction": 0,
     *      "description": "test ikhee",
     *      "relatedAccount": "5337040310"
     * }
     * $acntno => Хаан банк данс байгууллагын дансны дугаар
     */
    public function checkStatement($data, $acntno)
    {
        $bankCode = '05';
        if ($data) {

            $tran = AdCorporateGateway::where('bankcode', $bankCode)
                ->where('bankjrno', $data['journal'])
                ->where('txnamount', $data['amount'])
                ->when(@$data['relatedAccount'], function ($query, $relatedAccount) {
                    return $query->where('bankacntno', $relatedAccount);
                })
                ->where('statusid', '<>', -1)
                ->get();

            if ($tran->isEmpty()) {
                try {
                    $carbonDate = Carbon::parse($data['postDate']);
                    $carbonDate->setTime(
                        substr($data['time'], 0, 2),
                        substr($data['time'], 2, 2),
                        substr($data['time'], 4, 2)
                    );

                    $sign = $data['amount'] > 0 ? '+' : ($data['amount'] < 0 ? '-' : null);


                    $storeData = [
                        "instid" => $this->instid,
                        "bankcode" => $bankCode,
                        'banktxndate' => $carbonDate,
                        "bankacntno" => @$data['relatedAccount'] ?? '',
                        "bankfromacntno" => $acntno,
                        "sign" => $sign,
                        "bankjrno" => $data['journal'],
                        "txnamount" => $data['amount'],
                        "curcode" =>  $data['curcode'] ?? "MNT",
                        "txndesc" => $data['description'],
                        "balance" => $data['balance'],
                        'created_by' => $this->userid,
                    ];

                    $generalService = new AdCorporateGatewayService($this->instid, $this->userid, $this->providerConfig);
                    $corporateGateway = $generalService->storeCorporateGateway($storeData);

                    if ($data['correction'] == 0) {
                        $generalService->processCorporateGateway($corporateGateway, $acntno);
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                }
            }
        }
    }



    public function storeCorporateGateway($data)
    {
        $push = new AdCorporateGateway();
        foreach ($push->getFillable() as $field) {
            if (array_key_exists($field, $data)) {
                $push->$field = $data[$field];
            }
        }
        $push->statusid = 1;
        $push->save();
        return $push;
    }
    /**
     * Mobile app дээр ашиглаж байгаа
     * ХААН БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ
     * CG007 – ХААН БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ /DOMESTIC TRANSFER/
     *
     * @param  mixed $senddata = [
     *  "fromAccount": "string",
     *  "toAccount": "string",
     *  "toCurrency": "string",
     *  "amount": decimal,
     *  "description": "string",
     *  "currency": "string",
     *  "transferid":" string "
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
        if ($senddata['acnttype'] === 'LINE') {
            $senddata['fromAccount'] = @$this->providerConfig['account_no_line'] ?? @$this->providerConfig['account_no'];
        } else {
            $senddata['fromAccount'] = $this->providerConfig['account_no'];
        }

        $bankAccount = ApCustBankAccount::where('acnt_code', $senddata['toAccount'])
            ->where('statusid', '<>', -1)->first();

        if ($bankAccount) {
            $iban = $this->getNameByAcc($bankAccount['acnt_code'], '05');
            if (isset($iban['name'])) {
                $senddata['toAccountName'] = $this->resolveAccountName($bankAccount->acnt_name, $iban['name']);
            }
        }

        return $this->transService($senddata, '/v1/transfer/domestic');
    }

    /**
     * Банкны дансны бүртгэл дээрх нэр болон CGW-ээс ирсэн нэрийг үг тус бүрээр
     * тулгаж шалгана. Үгийн дараалал өөр байсан ч бүх үг агуулагдаж байвал
     * CGW-ээс ирсэн нэрийг ашиглана.
     */
    private function resolveAccountName($localName, $remoteName)
    {
        try {
            $local = mb_strtolower(trim((string) $localName));
            $remote = mb_strtolower(trim((string) $remoteName));

            if ($local === '' || $remote === '') {
                return $localName;
            }

            $parts = preg_split('/\s+/u', $local);
            $matches = true;
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (!Str::contains($remote, $part, true)) {
                    $matches = false;
                    break;
                }
            }

            return $matches ? $remoteName : $localName;
        } catch (Exception $ex) {
            Log::error($ex);
            return $localName;
        }
    }

    /**
     * Mobile app дээр ашиглаж байгаа
     * transInterBank - БУСАД БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ
     * CG008 – БУСАД БАНКНЫ ДАНС РУУ ГҮЙЛГЭЭ ХИЙХ /INTERBANK TRANSFER/
     *
     * @param  mixed $senddata [
     *  "fromAccount": "string",
     *  "toAccount": "string",
     *  "toCurrency": "string",
     *  "toAccountName": "string",
     *  "toBank": "string"
     *  "amount": decimal,
     *  "description": "string",
     *  "currency": "string",
     *  "transferid":" string "
     * ]
     * @return void
     */
    public function transInterBank($senddata)
    {
        if (isset($senddata['toBank']) && strlen($senddata['toBank']) == 2) {
            $senddata['toBank'] = $senddata['toBank'] . '0000';
        }
        if ($senddata['acnttype'] === 'LINE') {
            $senddata['fromAccount'] = $this->providerConfig['account_no_line'] ?? $this->providerConfig['account_no'];
        } else {
            $senddata['fromAccount'] = $this->providerConfig['account_no'];
        }
        $bankAccount = ApCustBankAccount::where('acnt_code', $senddata['toAccount'])
            ->where('statusid', '<>', -1)->first();


        if ($bankAccount) {
            if ($bankAccount['bank_code'] == 'IBAN') {
                $iban = $this->getNameByIBAN($bankAccount['acnt_code']);
            } else {
                $iban = $this->getNameByAcc($bankAccount['acnt_code'], $senddata['toBank']);
            }
        } else {
            throw new MeException('RC000022');
        }


        $senddata['toAccountName'] = $bankAccount->acnt_name;

        if (isset($iban['iban']) && isset($iban['name'])) {
            // Банкны дансны бүртгэл болон CGW-ээс ирсэн дансны нэрийг үг тус бүрээр
            // тулгаад бүгд таарвал ирсэн нэрийг ашиглана (үгийн дараалал хамаагүй).
            $senddata['toAccountName'] = $this->resolveAccountName($bankAccount->acnt_name, $iban['name']);

            $senddata['toAccount'] = $iban['iban'];
        } else {
            throw new MeException('RC000216');
        }


        // return $senddata;
        return $this->transService($senddata, '/v1/transfer/interbank');
    }

    /**
     * Харилцагч данс нэмэх үед ДНС-аас лавлаж нэр тулгах
     * checkBankAccount - CG006 - ДАНСНЫ ЭЗЭМШИГЧИЙН НЭР сервис
     *
     *  @param  mixed $senddata [
     *  "cust": "CrCustInd",
     *  "acntno": "string",
     *  "bankcode": "string"
     * ]
     */

    public function checkBankAccount($cust, $acntno, $bankcode)
    {
        $iban = $this->getNameByAcc($acntno, $bankcode);

        if (isset($iban['iban']) && isset($iban['name'])) {
            // Банкны дансны бүртгэл болон CGW-ээс ирсэн дансны нэрийг адил эсэхийг шалгаад таарч байвал ирсэн нэрийг ашиглах хэрэгтэй байх
            try {

                if (mb_strtolower($iban['name']) == mb_strtolower($cust->name)) {
                    $success = true;
                    $iban['success'] = $success;
                    return $iban;
                } else {
                    $success = false;
                    $iban['success'] = $success;
                    return $iban;
                }
            } catch (Exception $ex) {
                throw new MeException('RC000257');
            }
        } else {
            throw new MeException('RC000258');
        }
    }

    public function transService($senddata, $url)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();
        $senddata['loginName'] = $this->providerConfig['loginName'];
        $senddata['tranPassword'] = safeDecrypt($this->provider['sec2']);
        // Ямар нэгэн тохиолдлоор алдаатай болчихвол
        $senddata['statusid'] = 2;
        $user = auth()->user();
        $r = new GPLogRequestList();

        $cgwTransaction =  $this->createTransaction($senddata);
        try {
            $r->userid = $user ? $user->userid : 1;
            $r->url = $this->connection['url'] . $url;
            $r->method = 'POST';
            $r->request = json_encode($senddata, JSON_UNESCAPED_UNICODE);
            $r->save();
            $response = Http::withHeaders(
                [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                ]
            )->post($this->connection['url'] . $url, $senddata);
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
            $transactions = $this->getAccountStatement($senddata['fromAccount']);
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
        $r->response = (string)$response->getBody();
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
     *  "toBank": "string"
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
            ->where('statusid', '<>', -1)->first();

        $cgwTransaction =  AdCgwTransaction::create([
            'jrno' => $data['journal_no'] ?? null,
            'from_account' => $data['fromAccount'] ?? null,
            'amount' => $data['amount'],
            'curcode' => $data['currency'],
            'description' => $data['description'] ?? null,
            'to_bank' => $data['toBank'] ?? "05",
            'to_account' => $data['toAccount'],
            'to_account_name' => $bankAccount->acnt_name ?? null,
            'transferid' => $data['transferid'] ?? null,
            'system_date' => $data['system_date'] ?? Carbon::now(),
            'uuid' => $data['uuid'] ?? '0',
            'source' => 1, // 1 - MeApp
            'statusid' => $data['statusid'],
            'instid' => $this->instid,
            'created_by' => $this->userid
        ]);
        return $cgwTransaction;
    }

    /**
     * Mobile app дээр ашиглаж байгаа
     * getNameByAcc - CG006 - ДАНСНЫ ЭЗЭМШИГЧИЙН НЭР сервис
     */

    public function getNameByAcc($account, $bankcode)
    {
        $this->token = $this->getToken();
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->get($this->connection['url']  . '/v1/accounts/cam?acct=' . $account . '&bank_code=' . $bankcode);

        $customer = json_decode((string) $response->getBody(), true);
        return $customer;
    }

    public function getNameByIBAN($iban)
    {
        $this->token = $this->getToken();
        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->get($this->connection['url']  . '/v1/accounts/cam?iban=' . $iban);

        $customer = json_decode((string) $response->getBody(), true);
        return $customer;
    }
}
