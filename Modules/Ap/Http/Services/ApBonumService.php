<?php

namespace Modules\Ap\Http\Services;

use Illuminate\Support\Facades\Http;
use App\Exceptions\MeException;
use Carbon\Carbon;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Entities\GPLogRequestList;
// use Exception;
// use App\Models\User;
// use Illuminate\Http\Request;
// use App\Events\ApTxnMonitoringEvent;
// use Illuminate\Support\Facades\Log;

class ApBonumService
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


    public function __construct($instid, $userid)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->provider = VwGPProviderConf::where('code', 'BONUM')->where('instid', $instid)->first();
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
                'code' => 'BONUM'
            ]);
        }
    }


    /**
     * @param  $url_params {
     *   "scope": "cardzone",
     *   "grant_type": "client_credentials"
     * }
     */
    public function getToken()
    {
        $response = Http::withHeaders(
            [
                'Authorization' => 'Basic ' . base64_encode($this->providerConfig['bonum_username'] . ":" . safeDecrypt($this->provider['sec1'])),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        )->post($this->connection['url'] . '/oauth/token?scope=' . $this->providerConfig['scope'] . '&grant_type=' . $this->providerConfig['grant_type']);

        $token = json_decode((string) $response->getBody(), true);
        return $token['access_token'] ?? null;
    }

    /**
     * Write log into GPLogRequestList
     */
    private function logRequest($url, $method, $requestData, $response, $startTime)
    {
        $log = new GPLogRequestList();
        $user = auth()->user();
        $log->userid = $user ? $user->userid : 1;
        $log->url = $url;
        $log->method = strtoupper($method);
        $log->request = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $log->response = (string) $response->getBody();
        $log->responsecode = $response->status();
        $log->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $log->save();
    }

    /**
     * Карт үүсгэх
     *
     */
    public function createCard($data)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $reqData = [
            // "customerId" => $data['customerId'],
            "customerName" => $data['fullname'],
            "embossName" => $data['fullname'],
            "creditLimit" => $data['creditLimit'],
            "basicSupIndicator" => $data['basicsupindicator'] ?? $this->providerConfig['basicSupIndicator'] ?? null,
            "cardPlanId" =>  $data['cardplanid'] ?? $this->providerConfig['cardPlanId'],
            "branchId" => $this->providerConfig['branchId'],
            "embossIndicator" => $data['embossindicator'] ?? $this->providerConfig['embossIndicator'] ?? null,
            "cardDeliverMethod" => $this->providerConfig['cardDeliverMethod'],
            // "generateToken" => true,
            "profile" => [
                "gender" => $data['profile']['gender'],
                "name" => $data['fullname'],
                "identityNumber" => $data['profile']['identityNumber'],
                "birthdate" => $data['profile']['birthdate'],
                "mobile" => $data['profile']['mobile'],
                "email" => $data['profile']['email'],
                "nationality" => $data['profile']['nationality']
            ],
            "address" => [
                "addressLine1" => $data['address']['addressLine1'],
                "addressLine2" => $data['address']['addressLine2'],
                "addressLine3" => $data['address']['addressLine3'],
                "country" => $data['profile']['nationality'],
                "state" => $data['address']['state'],
                "city" => $data['address']['city'],
                "email" => $data['profile']['email']
            ]
        ];

        $url = $this->connection['url'] . '/cardzone/cards';

        $response = Http::withHeaders(
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ]
        )->post($url, $reqData);

        $this->logRequest($url, 'POST', $reqData, $response, $startTime);

        return $response;
    }


    /**
     * Картын дэлгэрэнгүй мэдээлэл
     * @param string $cardId
     */
    public function getCardDetail($cardId)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $url = $this->connection['url'] . "/cardzone/cards/{$cardId}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->get($url);

        $this->logRequest($url, 'GET', ['cardId' => $cardId], $response, $startTime);

        return $response;
    }


    /**
     * Харилцагчийн картын мэдээллүүд
     * @param string $regNo
     */
    public function getCustCardInfo($regNo)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $url = $this->connection['url'] . "/cardzone/cards?registration={$regNo}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->get($url);

        $this->logRequest($url, 'GET', ['regNo' => $regNo], $response, $startTime);

        return $response;
    }


    /**
     * Картын статус солих
     * @param string $cardId
     * @param string $status
     */
    public function setCartStatus($cardId, $status)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $url = $this->connection['url'] . "/cardzone/cards/{$cardId}?status=" . $status;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->put($url);

        $this->logRequest($url, 'PUT', ['cardId' => $cardId, 'status' => $status], $response, $startTime);

        return $response;
    }


    /**
     * Картын пин код солих
     * @param string $cardId Картын дугаар
     * @param string $pinCode Шинэ пин код
     */
    public function setCartPin($cardId, $pinCode)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $encodedPin = urlencode($pinCode);
        $url = $this->connection['url'] . "/cardzone/cards/{$cardId}/pin?pinCode={$encodedPin}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->post($url);

        $this->logRequest($url, 'POST', ['cardId' => $cardId], $response, $startTime);

        return $response;
    }


    /**
     * Харилцагчийн дэлгэрэнгүй мэдээлэл
     * @param string $regNo
     */
    public function getCustDetail($regNo)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $encodedRegNo = urlencode($regNo);
        $url = $this->connection['url'] . "/cardzone/customers/{$encodedRegNo}/info";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->get($url);

        $this->logRequest($url, 'GET', ['regNo' => $regNo], $response, $startTime);

        return $response;
    }

    /**
     * Харилцагчийн бүх картны үлдэгдэлийг авна.
     * @param string  $regNo
     * @param string  $date     @example 202409 (YYYYMM)
     */
    public function getCustBalance($regNo, $date)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $encodedRegNo = urlencode($regNo);
        $url = $this->connection['url'] . "/cardzone/customers/{$encodedRegNo}/balance?date=" . $date;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->get($url);

        $this->logRequest($url, 'GET', ['regNo' => $regNo], $response, $startTime);

        return $response;
    }

    /**
     * Харилцагчийн картын гүйлгээний мэдээллүүд мэдээллүүд
     * @param array $validated
     */
    public function getCardTransactions($validated)
    {
        $this->token = $this->getToken();
        $startTime = Carbon::now()->getTimestampMs();

        $url = $this->connection['url'] . "/cardzone/cards/transactions";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => '*/*',
        ])->get($url, $validated);

        $this->logRequest($url, 'GET', $validated, $response, $startTime);

        return $response;
    }

    /**
     * Тохиргооноос картын бүтээгдэхүүний жагсаалт авах
     */
    public function getCardPlans()
    {
        return $this->providerConfig['cardPlans'] ?? [];
    }
}
