<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use App\Resolvers\ChannelResolver;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApDanResponse;
use Modules\Gp\Entities\GPUserAccessToken;
use Modules\Gp\Enums\LoginTypeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Entities\GPInstAddField;
use Modules\Cr\Entities\CrCustAdd;
use Modules\Gp\Entities\GpppList;
use Illuminate\Http\Request;

class ApAuthService extends Controller
{
    public function __construct()
    {
    }



    public function getDanUserDetail($code, $generateTokenUri, $userid)
    {
        $danResponse = ApDanResponse::where("code", $code)->first();
        if (!$danResponse) {
            throw new MeException("RC000246");
        }

        if (!$services = json_decode($danResponse->services)) {
            throw new MeException("RC000246");
        }

        $citizenCardInfo = null;
        foreach ($services as $service) {
            if (property_exists($service, 'services')) {
                if (property_exists($service->services, 'WS100101_getCitizenIDCardInfo')) {
                    $citizenCardInfo = $service->services->WS100101_getCitizenIDCardInfo->response;
                }
                if (property_exists($service->services, 'WS100307_getLegalEntityInfoWithRegnum')) {
                    if (property_exists($service->services->WS100307_getLegalEntityInfoWithRegnum, 'response')) {
                        if ($service->services->WS100307_getLegalEntityInfoWithRegnum->resultCode != 0) {
                            throw new MeException($service->services->WS100307_getLegalEntityInfoWithRegnum->resultMessage .
                                ". Baiguullagiin dugaar: " . $service->services->WS100307_getLegalEntityInfoWithRegnum->request->legalEntityNumber);
                        }
                    }
                }
            }
        }
        if (!$citizenCardInfo) {
            throw new MeException("RC000248");
        }

        $user = ApCustUser::find($userid);

        try {
            $user->reg_no = mb_strtoupper($citizenCardInfo->regnum);
            $user->firstname = $citizenCardInfo->firstname;
            $user->lastname = $citizenCardInfo->lastname;
            $user->statusid = 1;
            $user->save();
        } catch (QueryException $ex) {
            return $this->error($ex->getMessage());
        }

        $danResponse->userid = $userid;
        $danResponse->save();


        return redirect($generateTokenUri . "/" . $code);
    }



    public function generateToken($code)
    {
        $user = auth()->user();
        $danResponse = ApDanResponse::where('code', $code)->where('isused', null)->where('userid', '<>', null)->first();
        if ($danResponse) {
            $token_lifetime = (int) CoreService::getInstGp($user->instid, "TOKEN_LIFETIME");
            $lastused = new Carbon($danResponse->created_at);
            $now = new Carbon();
            if ($now->diffInMinutes($lastused) < $token_lifetime) {

                $token = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));

                GPUserAccessToken::create([
                    'userid' => $danResponse->userid,
                    'name' => 'login',
                    'token' => $token,
                    'channel' => ChannelResolver::resolve(),
                    'last_used_at' => Carbon::now(),
                ]);
                return $token;
            } else {
                throw new MeException('SR1170');
            }
        }
        return $this->error("SR1170");
    }

    /**
     *  - Google эсвэл Apple Id login хийсний дараа token шалгах
     *
     * @param  string $provider
     * @param  string $token
     * @return response
     */
    public function verify_token($token, $provider)
    {
        try {
            if ($provider === LoginTypeEnum::google) {
                $valid = $this->verifyGoogleToken($token);
            } elseif ($provider === LoginTypeEnum::apple) {
                $valid = $this->verifyAppleToken($token);
            }

            if ($valid) {
                $validData = json_decode($valid, true);
                $response = [
                    "valid" => true,
                    "message" => "Token verification success",
                    "email" => $validData['email'],
                ];
            } else {
                $response = [
                    "valid" => false,
                    "message" => "Token verification failed"
                ];
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Token verification failed: ' . $e->getMessage());
            $this->error('Token verification failed');
        }
    }

    function verifyGoogleToken($idToken)
    {
        $clientId = config('app.google_client_id');

        $response = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$idToken}");

        if ($response->successful()) {
            $payload = $response->json();
            return $payload['aud'] == $clientId ? json_encode($payload) : null;
        }

        return null;
    }

    function verifyAppleToken($idToken)
    {
        $appleKeyUrl = "https://appleid.apple.com/auth/keys";
        $publicKeys = json_decode(file_get_contents($appleKeyUrl), true)['keys'];

        $decodedHeader = json_decode(base64_decode(explode('.', $idToken)[0]), true);
        $kid = $decodedHeader['kid'];

        // Find the matching public key
        $key = null;
        foreach ($publicKeys as $publicKey) {
            if ($publicKey['kid'] === $kid) {
                $key = $publicKey;
                break;
            }
        }

        if (!$key) {
            throw new Exception("No matching Apple key found.");
        }

        // Convert JWK to PEM format and parse it correctly
        $parsedKey = JWK::parseKey($key);

        $res = JWT::decode($idToken, $parsedKey);
        return json_encode($res);
    }


    function getAvgSalary($data, $duration)
    {
        if (empty($data)) {
            return 0;
        }

        $paidRecords = array_filter($data, fn($item) => $item->paid === true);

        // Sort DESC by year/month
        usort(
            $paidRecords,
            fn($a, $b) => $b->year === $a->year ? $b->month - $a->month : $b->year - $a->year
        );

        // Last 6 paid months
        $lastSix = array_slice($paidRecords, 0, $duration);

        // Calculate net received salary
        $totalNet = array_reduce($lastSix, function ($carry, $item) {
            return $carry + ($item->salaryAmount - $item->salaryFee);
        }, 0);

        $avgNet = count($lastSix) ? $totalNet / count($lastSix) : 0;

        return $avgNet;
    }

    function createAddField($instid, $key, $value, $crCust)
    {
        $cnst = GPInstAddField::where('code', $key)
            ->where('instid', $instid)
            ->where('statusid', '<>', -1)->first();

        if ($cnst) {
            CrCustAdd::create([
                'keyfield' => $cnst->id,
                'itemvalue' => $value,
                'statusid' => 1,
                'custid' => $crCust->id,
                'custtypecode' => $crCust->custtypecode,
                'instid' => $instid,
                'created_by' => $crCust->id,
                'updated_by' => $crCust->id,
            ]);
        }
    }

    /**
     *  - Mobile app шалгах
     *
     */

    public function checkMobileApp(Request $request)
    {
        // Ali app ashiglaj bgag shalgah
        if ($request->hasHeader('X-App-Identifier') && $request->hasHeader('X-App-Secret')) {
            $app = GpppList::where('app_identifier', $request->header('X-App-Identifier'))
                ->where('app_secret', $request->header('X-App-Secret'))
                ->where('statusid', 1)
                ->first();
            return $app;
            if (!$app) {
                throw new MeException("RC000249");
            }
        }
        return null;
    }
}
