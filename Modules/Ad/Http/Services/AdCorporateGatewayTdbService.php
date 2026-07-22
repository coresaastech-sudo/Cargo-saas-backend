<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCorporateGateway;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;

class AdCorporateGatewayTdbService extends Controller
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
        $this->provider = VwGPProviderConf::where('code', '04')->where('instid', $instid)->first();
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
                'code' => '04'
            ]);
        }
    }

    public function getToken()
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->connection['url'] . '/oauth2/token', [
            'grant_type' => "client_credentials",
            'client_id' => $this->providerConfig['client_id'],
            'client_secret' => safeDecrypt($this->provider['sec1']),
        ]);

        // Response статус шалгах
        if (!$response->successful()) {
            Log::error("Token API request failed", [
                'url' => $this->connection['url'] . '/oauth2/token',
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new MeException('RC000005');
        }

        $token = $response->json();

        return $token['token'];
    }

    /**
     * Дансны жагсаалт авах
     * [{
     * "ACNTNO": "400017494",
     * "IBAN": "MN910004000400017494",
     * "ACNTNAME": "ГИЛЛИ ХХК",
     * "ACNTMODE": "DP",
     * "CURCODE": "MNT",
     * "BALANCE": 12982817842.77,
     * "CUSTNO": "90475015949",
     * "AVAILABLEBAL": 12982797842.77,
     * "HOLDBAL": 0
     * }]
     */

    public function getAcntList()
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $requestData = [];


        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $this->connection['url'] . '/accounts';
        $r->method = 'GET';
        $r->request = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $r->save();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->get($r->url, $requestData);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        if (!$response->successful()) {
            Log::error("Token API request failed", [
                'url' => $this->connection['url'] . '/oauth2/token',
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new MeException('RC000005');
        }

        return json_decode($response->getBody(), true);
    }


    /**
     * Дансны хуулга авах
     * accountNumber - Дансны дугаар
     * startDate - Эхлэх огноо. Формат: yyyy/MM/dd
     * endDate - Дуусах огноо. Формат: yyyy/MM/dd
     */
    public function getAccountStatement($accountNumber, $startDate = '', $endDate = '')
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $requestData = [];

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $this->connection['url'] . '/accounts/statement/' . $accountNumber . '?from=' . $startDate . '&to=' . $endDate . '&page=1&size=1000';
        $r->method = 'GET';
        $r->request = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $r->save();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($r->url);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return json_decode($response->getBody(), true);
    }



    /**
     * @example  $data
     *{
     *  "txndate": "2023/08/23 16:52:08",
     *  "refno": 864400012574,
     *  "txndesc": "EB -test",
     *  "credit": 500,
     *  "debit": 0,
     *  "balance": 49660637569.63,
     *  "contacntno": "400018691",
     *  "currate": 1,
     *  "contacntname": "ГИЛЛИ ХХК",
     *  "fee": "0",
     *  "bankcode": "04"
     *},
     * $acntno => Данс байгууллагын дансны дугаар
     */
    public function checkStatement($data, $acntno, $curcode = 'MNT')
    {
        $bankCode = '04'; // Төрийн банк код
        if ($data) {
            $sign = '+';
            // amount талбарыг зөв авах: credit эсвэл debit-ээс эерэг утгыг авах
            if (!empty($data['credit']) && $data['credit'] > 0) {
                $amount = $data['credit'];
                $sign = '+';
            } elseif (!empty($data['debit'])) {
                // debit нь сөрөг эсвэл эерэг байж болно, үнэмлэхүй утгыг авна
                $amount = abs($data['debit']);
                $sign = '-';
            } else {
                $amount = 0;
            }

            $tran = AdCorporateGateway::where('bankcode', $bankCode)
                ->where('bankjrno', $data['refno'])
                ->where('txnamount', $amount)
                ->where('bankacntno', $data['contacntno'] ?? '')
                ->where('statusid', '<>', -1)
                ->get();

            if ($tran->isEmpty() && isset($data['contacntno'])) {
                try {
                    $carbonDate = Carbon::parse($data['txndate']);

                    $storeData = [
                        "instid" => $this->instid,
                        "bankcode" => $bankCode,
                        'banktxndate' => $carbonDate,
                        "bankacntno" => $data['contacntno'],
                        "bankfromacntno" => $acntno,
                        "sign" => $sign,
                        "bankjrno" => $data['refno'],
                        "txnamount" => $amount,
                        "curcode" =>  $curcode,
                        "txndesc" => $data['txndesc'],
                        "balance" => $data['balance'],
                        'created_by' => $this->userid,
                    ];

                    $generalService = new AdCorporateGatewayService($this->instid, $this->userid, $this->providerConfig);
                    $corporateGateway = $generalService->storeCorporateGateway($storeData);

                    if ($data['fee'] == '0') {
                        $generalService->processCorporateGateway($corporateGateway, $acntno);
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                }
            }
        }
    }
}
