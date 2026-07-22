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

class AdCorporateGatewayGolomtService extends Controller
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
    public $username;
    public $clientId;
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

        $this->provider = VwGPProviderConf::where('code', '15')
            ->where('instid', $instid)
            ->first();
        if (isset($this->provider)) {
            $connConf = VwGPConnConf::where('id', $this->provider->connid)
                ->where('instid', $instid)
                ->first();

            $this->providerConfig = json_decode($this->provider->config, true);
            $this->clientId = $this->providerConfig['clientId'];
            if (isset($connConf)) {
                $this->connection = json_decode($connConf->config, true);
            } else {
                throw new MeException('RC000174');
            }
        } else {
            throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '15'
            ]);
        }
    }

    /**
     * Нууц үгээр нэвтрэх
     *
     */
    public function login()
    {
        $startTime = Carbon::now()->getTimestampMs();
        $password = safeDecrypt($this->provider['sec1']);
        $this->username = $this->providerConfig['username'];

        $encryptedPass = $this->encryptPassword($password);

        $url = $this->connection['url'] . '/v1/auth/login';
        $data = [
            'name' => $this->username,
            'password' => $encryptedPass
        ];

        $logData = [
            'name' => $this->username,
            'password' => '****'
        ];

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $url;
        $r->method = 'POST';
        $r->request = json_encode($logData, JSON_UNESCAPED_UNICODE);
        $r->save();

        $response = Http::withHeaders(
            [
                'X-Golomt-Service' => 'LGIN',
            ]
        )->post($url, $data);

        $r->response = (string) $response->getBody();
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        $res = $response->json();

        $accessToken = isset($res['token']) ? $res['token'] : null;

        return $accessToken;
    }


    /**
     * Дансны үлдэгдэл шалгах
     *
     */
    public function sendRequest($service, $url, $data, $golomtCode = null)
    {
        $token = $this->login();
        $startTime = Carbon::now()->getTimestampMs();

        $checkSum = $this->generateChecksum($data, $this->providerConfig['sessionKey'], $this->providerConfig['ivKey']);

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $this->connection['url'] . $url;
        $r->method = 'POST';
        $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
        $r->save();
        $header =  [
            'Authorization' => 'Bearer ' . $token,
            'X-Golomt-Service' => $service,
            'X-Golomt-Checksum' => $checkSum
        ];

        if ($golomtCode != null) {
            $header['X-Golomt-Code'] = $golomtCode;
        }

        $response = Http::withHeaders(
            $header
        )->post($r->url, $data);

        $responseBody = (string) $response->getBody();
        $decrypted = $this->decryptResponse($responseBody);

        $r->response = json_encode($decrypted, JSON_UNESCAPED_UNICODE);
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();

        return $decrypted;
    }



    /**
     * Дансны үлдэгдэл шалгах
     *
     */
    public function checkAccountBalance()
    {
        $state = '';
        $scope = '';
        $url = "/v1/account/balance/inq?client_id={$this->clientId}&state=$state&scope=$scope";
        $registerNo = $this->providerConfig['registerNo'];
        $accountId = $this->providerConfig['accountId'];

        $data = [
            'registerNo' => $registerNo,
            'accountId' =>  $accountId
        ];

        return $this->sendRequest('ACCTBALINQ', $url, $data);
    }


    /**
     * Дансны төрөл шалгах
     *
     */
    public function checkAccountType()
    {
        $url = "/v1/account/type/inq";
        $accountId = $this->providerConfig['accountId'];

        $data = [
            'accountId' => $accountId
        ];

        return $this->sendRequest('ACCTTYPEINQ', $url, $data);
    }



    /**
     * Харилцах дансны дэлгэрэнгүй мэдээлэл харах
     * @return void
     */
    public function operAccountDetail()
    {
        $state = '';
        $scope = '';
        $url = "/v1/account/operative/details?client_id={$this->clientId}&state={$state}&scope={$scope}";
        $registerNo = $this->providerConfig['registerNo'];
        $accountId = $this->providerConfig['accountId'];

        $data = [
            'accountId' => $accountId,
            'registerNo' => $registerNo
        ];

        return $this->sendRequest('OPERACCTDET', $url, $data);
    }


    /**
     * Харилцах дансны хуулга харах
     * @return void
     */
    public function operAccountStatement($accountId, $startDate, $endDate)
    {
        $state = '';
        $scope = '';
        $url = "/v1/account/operative/statement/?client_id={$this->clientId}&state={$state}&scope={$scope}";
        $registerNo = $this->providerConfig['registerNo'];

        $data = [
            'accountId' => $accountId,
            'registerNo' => $registerNo,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        return $this->sendRequest('OPERACCTSTA', $url, $data);
    }


    /**
     * Дансны жагсаалт татах
     *
     */
    public function getAccountList()
    {
        $state = '';
        $scope = '';
        $url = "/v1/account/list?client_id={$this->clientId}&state=$state&scope=$scope";
        $registerNo = $this->providerConfig['registerNo'];

        $data = [
            'registerNo' => $registerNo,
        ];

        return $this->sendRequest('ACCTLST', $url, $data);
    }

    /**
     * Данс эзэмшигчийн мэдээлэл авах/Голомтын болон Голомтын бус данс эзэмшигч/
     * @return
     */

    public function checkAccount($bankCode)
    {
        $url = "/v1/account/check/account";
        $accountId = $this->providerConfig['accountId'];

        $data = [
            'accountId' => $accountId,
            'bankCode' => $bankCode
        ];
        return $this->sendRequest('ACCTLST', $url, $data);
    }

    public function transferFromAccount($acctName, $value, $acctName2, $acttNo, $acctNo1)
    {
        $state = '';
        $scope = '';
        $base32Secret = $this->providerConfig['X-Golomt-Key'];
        $golomtCode = $this->generateTotpCode($base32Secret);

        $url = "/v1/transaction/cgw/transfer?client_id=&state=&scope=";

        $data = [
            "genericType" => null,
            "registerNumber" => $this->providerConfig['registerNo'],
            "type" => "TSF",
            "refCode" => "123",
            "initiator" => [
                "genericType" => null,
                "acctName" => $acctName,
                "acctNo" => $acctNo1,
                "amount" => [
                    "value" => floatval($value),
                    "currency" => "MNT"
                ],
                "particulars" => "remarks",
                "bank" => "15"
            ],
            "receives" => [[
                "genericType" => null,
                "acctName" => $acctName2,
                "acctNo" => $acttNo,
                "amount" => [
                    "value" => floatval($value),
                    "currency" => "MNT"
                ],
                "particulars" => "remarks",
                "bank" => "15"
            ]],
            "remarks" => "remarks"
        ];

        $res =  $this->sendRequest('CGWTXNADD', $url, $data, (string) $golomtCode);
        return ['res' => $res, 'X-Golomt-Code' => $golomtCode];
    }

    /**
     * @example  $data
     * {
     *   "requestId": "77a9c565ecf4475c827a7ccd2483b6a2",
     *   "recNum": 2,
     *   "tranId": "GB10064",
     *   "tranDate": "2025-10-08",
     *   "drOrCr": "Debit",
     *   "tranAmount": 20000,
     *   "tranDesc": "44000179-",
     *   "tranPostedDate": "2025-10-08T16:20:46",
     *   "tranCrnCode": "MNT",
     *   "exchRate": 1,
     *   "balance": "85199194.54",
     *   "accName": "JCI ИХ ХҮРЭЭ",
     *   "accNum": "1105028077"
     * },
     *  $acntno => Голомт банк данс байгууллагын дансны дугаар
     */
    public function checkStatement($data, $acntno)
    {
        $bankCode = '15';
        if ($data) {
            $tran = AdCorporateGateway::where('bankcode', $bankCode)
                ->where('bankjrno', $data['tranId'])
                ->where('txnamount', $data['tranAmount'])
                ->where('bankacntno', $data['accNum'] ?? '')
                ->where('statusid', '<>', -1)
                ->get();

            if ($tran->isEmpty() && isset($data['accNum'])) {
                try {
                    $carbonDate = Carbon::parse($data['tranPostedDate']);

                    $sign = $data['drOrCr'] == 'Credit' ? '+' : ($data['drOrCr'] == 'Debit' ? '-' : null);

                    $storeData = [
                        "instid" => $this->instid,
                        "bankcode" => $bankCode,
                        'banktxndate' => $carbonDate,
                        "bankacntno" => $data['accNum'],
                        "bankfromacntno" => $acntno,
                        "sign" => $sign,
                        "bankjrno" => $data['tranId'],
                        "txnamount" => $data['tranAmount'],
                        "curcode" =>  $data['tranCrnCode'] ?? "MNT",
                        "txndesc" => $data['tranDesc'],
                        "balance" => $data['balance'],
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

    public function encryptPassword($data)
    {
        $key = mb_convert_encoding($this->providerConfig['sessionKey'], 'ISO-8859-1', 'UTF-8');
        $iv  = mb_convert_encoding($this->providerConfig['ivKey'], 'ISO-8859-1', 'UTF-8');

        $cipher = (strlen($key) === 16) ? 'AES-128-CBC' : 'AES-256-CBC';

        $encrypted = openssl_encrypt(
            $data,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($encrypted);
    }

    public function decryptResponse(string $response)
    {
        $payload = trim($response);

        if ($payload === '') {
            throw new Exception("Golomt response body is empty");
        }

        $decodedPayload = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decodedPayload)) {
                $payload = $decodedPayload;
            } elseif (is_array($decodedPayload)) {
                return $decodedPayload;
            }
        }

        $payload = preg_replace('/\s+/', '', $payload);

        // 1. Base64 decode (same as CryptoJS.enc.Base64.parse())
        $encrypted = base64_decode($payload, true);

        if ($encrypted === false) {
            throw new Exception("Golomt response is not valid base64: " . $this->responsePreview($response));
        }

        // 2. Convert keys to binary (same as CryptoJS.enc.Latin1.parse())
        $sessionKeyBinary = $this->providerConfig['sessionKey']; // Already binary string
        $ivKeyBinary = $this->providerConfig['ivKey']; // Already binary string

        // 3. Decide AES key size automatically
        $keyLen = strlen($sessionKeyBinary);
        $cipher = match ($keyLen) {
            16 => 'AES-128-CBC',
            24 => 'AES-192-CBC',
            32 => 'AES-256-CBC',
            default => throw new Exception("Invalid sessionKey length: $keyLen"),
        };

        $blockSize = openssl_cipher_iv_length($cipher);
        if ($blockSize === false || strlen($encrypted) % $blockSize !== 0) {
            throw new Exception("Invalid Golomt encrypted response block length: " . strlen($encrypted));
        }

        // 4. Decrypt with AES-CBC (same as CryptoJS.AES.decrypt())
        $decrypted = openssl_decrypt(
            $encrypted,
            $cipher,
            $sessionKeyBinary,
            OPENSSL_RAW_DATA,
            $ivKeyBinary
        );

        if ($decrypted === false) {
            throw new Exception("AES decryption failed: " . openssl_error_string());
        }

        // 5. Convert to string (same as decrypted.toString(CryptoJS.enc.Latin1))
        $plain = $decrypted;

        // 6. Parse JSON (same as JSON.parse())
        $responseData = json_decode($plain, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON: " . json_last_error_msg());
        }

        return $responseData;
    }

    private function responsePreview(string $response): string
    {
        $preview = trim($response);
        $preview = preg_replace('/\s+/', ' ', $preview);

        return mb_substr($preview, 0, 500);
    }

    function generateChecksum(array $data, string $sessionKey, string $ivKey): string
    {
        // 1. Encode body exactly like Postman sends it
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 2. SHA256 hash in hex (same as CryptoJS.SHA256().toString(CryptoJS.enc.Hex))
        $hash = hash('sha256', $body);

        // 3. Convert sessionKey and ivKey to binary (same as CryptoJS.enc.Latin1.parse())
        $sessionKeyBinary = $sessionKey; // Already binary string
        $ivKeyBinary = $ivKey; // Already binary string

        // 4. Decide AES key size automatically
        $keyLen = strlen($sessionKeyBinary);
        $cipher = match ($keyLen) {
            16 => 'AES-128-CBC',
            24 => 'AES-192-CBC',
            32 => 'AES-256-CBC',
            default => throw new Exception("Invalid sessionKey length: $keyLen"),
        };

        // 5. Encrypt with AES-CBC (same as CryptoJS.AES.encrypt())
        $encrypted = openssl_encrypt(
            $hash,
            $cipher,
            $sessionKeyBinary,
            OPENSSL_RAW_DATA,
            $ivKeyBinary
        );

        if ($encrypted === false) {
            throw new Exception("AES encryption failed: " . openssl_error_string());
        }

        // 6. Return base64 encoded result (same as encrypted.toString())
        return base64_encode($encrypted);
    }

    function generateTotpCode(string $base32Secret): string
    {
        $timeStepSeconds = 30;
        $numDigits = 6;

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper($base32Secret);
        $bits = '';
        $key = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $val = strpos($alphabet, $base32[$i]);
            if ($val === false) {
                throw new Exception("Invalid Base32 character: " . $base32[$i]);
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        $timeMillis = (int)(microtime(true) * 1000);
        $value = intdiv(intdiv($timeMillis, 1000), $timeStepSeconds);
        $data = pack('N*', 0) . pack('N*', $value);

        $hash = hash_hmac('sha1', $data, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $truncatedHash = unpack('N', substr($hash, $offset, 4))[1];
        $truncatedHash = $truncatedHash & 0x7FFFFFFF;
        $truncatedHash = $truncatedHash % 1000000;


        return str_pad((string)$truncatedHash, $numDigits, '0', STR_PAD_LEFT);
    }
}
