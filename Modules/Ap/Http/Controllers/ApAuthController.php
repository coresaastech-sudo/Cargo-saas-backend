<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use App\Resolvers\IpAddressResolver;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdLoginActivityLog;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApDanResponse;
use Modules\Ap\Http\Services\ApAuthService;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GpppList;
use Modules\Gp\Entities\GPConnConf;
use Modules\Gp\Entities\GPUserAccessToken;
use Modules\Gp\Enums\LoginTypeEnum;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Gp\Jobs\SendMailJob;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Http\Controllers\GPInstController;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Entities\ApUserImage;
use Modules\Ap\Http\Requests\ApAddUserRequest;
use Modules\Ap\Http\Services\InstCustConnService;
use Modules\Cr\Entities\CrCustAddr;
use Modules\Gp\Enums\SourceCodeEnum;
use Modules\Cr\Entities\CrCustImage;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Entities\GPInstAddField;
use Modules\Cr\Entities\CrCustAdd;

class ApAuthController extends Controller
{
    public function oi000010(Request $request, $checkGoogleAuth = false)
    {
        $validated = $this->validate($request, [
            'username' => 'required|max:50',
            'password' => 'required',
            'deviceid' => 'nullable',
            'devicename' => 'nullable',
            'pushToken' => 'nullable',
        ]);
        $validated['username'] = Str::lower($validated['username']);

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $loginType = 'email';
        if ($app != null && $app->app_data) {
            $appData = json_decode($app->app_data, true);
            if (isset($appData['login_type'])) {
                $loginType = strtolower($appData['login_type']);
            }
        }

        $q = ApCustUser::where('statusid', '<>', '-1');
        if ($loginType === 'phone') {
            $q->where('phone', $validated['username']);
        } elseif ($loginType === 'both') {
            $q->where(function ($query) use ($validated) {
                $query->where('email', $validated['username'])
                    ->orWhere('phone', $validated['username']);
            });
        } else {
            $q->where('email', $validated['username']);
        }

        if ($app != null) {
            $user = $q->where('app_id', $app->id)->first();
        } else {
            $user = $q->first();
        }

        if ($user) {
            $limit = 5;
            if ($limit != 0 && $user->passwrong >= $limit) {
                $this->error('RC000168');
            }
            $userPassword = $user->password;
            if (Hash::check($validated['password'], $userPassword)) {
                //generate token
                $token = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));
                // Идэвхтэй токеныг цэвэрлэх
                GPUserAccessToken::where('userid', $user->id)
                    ->where('channel', 'APP')
                    ->where('name', 'login')
                    ->delete();
                //insert token
                GPUserAccessToken::create([
                    'userid' => $user->id,
                    'name' => 'login',
                    'token' => $token,
                    'abilities' => '',
                    'last_used_at' => Carbon::now(),
                    'channel' => 'APP'
                ]);

                //reset wrong pass count
                $user->passwrong = 0;
                $user->device_token = $validated['pushToken'] ?? "";
                $expiryDay = 365;
                $password_changed_at = new Carbon(($user->password_changed_at) ? $user->password_changed_at : $user->createdate);
                // Log::info(Carbon::now()->diffInDays($password_changed_at) . " " . $user->password_changed_at);
                if (Carbon::now()->diffInDays($password_changed_at) >= $expiryDay) {
                    $user->mustchGPss = '1';
                }
                $user->save();

                // Google authenticator идэвхжүүлж логин хийгдсэн эсэх
                if (!$checkGoogleAuth && $user->use_google_auth == "1") {
                    return ['use_google_auth' => 1, 'google_auth_key' => true];
                }

                // Login хийгдсэн түүх хадгална.
                AdLoginActivityLog::create([
                    'userid' => $user->id,
                    'agent' => $request->header('User-Agent'),
                    'device_ip' => IpAddressResolver::resolve(),
                    'statusid' => 1,
                    'channel' => 1,
                    'deviceid' => $validated['deviceid'] ?? 'Unknown',
                    'devicename' => $validated['devicename'] ?? 'Unknown',
                    'created_by' => $user->id
                ]);

                $perms = [];
                // foreach ($user->activeRoles as $userRole) {
                //     if ($userRole->role && $userRole->role->perms) {
                //         foreach ($userRole->role->perms as $perm) {
                //             $perms[$perm->permid] = 1;
                //         }
                //     }
                // }

                $userInfo = [
                    'userid' => $user->id,
                    'email' => $user->email,
                    'regno' => $user->regno,
                    'phone' => $user->phoneuser ?? '',
                    'firstname' => $user->firstname,
                    'firstname2' =>  cyrillic2latin($user->firstname),
                    'lastname' => $user->lastname,
                    'lastname2' =>  cyrillic2latin($user->lastname),
                    'branch' => $user->branch,
                    'perms' => $perms,
                    'mustchGPss' => $user->mustchGPss
                ];

                return ['user' => $userInfo, 'token' => $token];
            }

            //increase wrong pass count
            $user->passwrong = $user->passwrong + 1;
            $user->save();
        }
        $this->error('RC000004');
    }

    /**
     * Logout
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000020(Request $request)
    {
        $token = $request->bearerToken();
        $user = auth()->user();
        if ($user && gettype($user) != 'string') {
            $token = $request->bearerToken();
            GPUserAccessToken::where('userid', $user->id)
                ->where('token', $token)
                ->where('channel', 'APP')
                ->where('name', 'login')->delete();
        } else {
            $this->error("RC000006");
        }
    }

    public function oi000030()
    {
        $user = auth()->user();
        if (empty($user)) {
            $this->error('RC000006');
        }
        if (gettype($user) == 'string') {
            $this->error('RC000006');
        }
        $userInfo = [
            'userid' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'branch' => $user->branch
        ];
        return $userInfo;
        // $token =  $request->bearerToken();
        // $user = User::whereHas('tokens', function($q) use ($token) {$q->where('token', $token); })->first();
        // if ($user){
        //     return response()->json($user);
        // }
        // return response()->json('Session not found!', 404);
    }

    public function checkVersion(Request $request)
    {
        $validated = $this->validate($request, [
            'version' => 'required',
        ]);


        $max = GPInstConst::where('code', "MAX")->where('statusid', '<>', '-1')->first();
        $min = GPInstConst::where('code', "MIN")->where('statusid', '<>', '-1')->first();

        if (!isset($max)) {
            $this->error("Max хувилбар тохиргоо хийгдээгүй байна.");
        }

        if (!isset($min)) {
            $this->error("Min хувилбар тохиргоо хийгдээгүй байна.");
        }

        if ((intval($validated['version']) >= intval($min->value)) && (intval($validated['version']) <= intval($max->value))) {
            return ['success' => 1];
        } else {
            return ['success' => 0];
        }
    }

    /**
     * oi000050 - resetPassword
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000050(Request $request)
    {
        $validated = $this->validate($request, [
            'token' => 'required|min:7',
            'password' => 'required|confirmed',
        ], [
            'token.required' => 'VC000006',
            'password.required' => 'VC000002',
            'password.confirmed' => 'VC000005',
        ]);
        $password = $validated['password'];

        $token = $validated['token'];
        $user = ApCustUser::select("*")
            ->selectRaw("
                CASE
                    WHEN passtokendate IS NULL THEN 1
                    WHEN passtokenstatus = 0 THEN 2
                    WHEN passtokendate < NOW() - INTERVAL '1440 minutes' THEN 3
                    ELSE 4
                END AS tokenstatus
            ")->where("passtoken", $token)->first();
        if ($user) {
            $checkTkn = $this->checkToken($user->tokenstatus);
            if ($checkTkn['code']) {
                $user->changePassword($password);
                $user->update([
                    'passwrong' => 0,
                    'password_changed_at' => Carbon::now(),
                    'updated_by' => $user->userid,
                    'mustchGPss' => 0,
                ]);
                return 'Нууц үг амжилттай солигдлоо.';
            } else {
                $this->error($checkTkn['msg']);
            }
        }
        $this->error('RC000008');
    }

    /**
     * oi000060 - Forgotpassword
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000060(Request $request)
    {
        $validated = $this->validate($request, [
            'email' => 'required|email',
        ], [
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
        ]);

        $email = Str::lower($validated['email']);
        $data = array();
        $hostname = config("app.url");
        $user = ApCustUser::where("email", $email)->where('statusid', '<>', '-1')->first();
        $data['hostname'] = $hostname;
        if ($user) {
            $token = rand(100000, 999999);
            $user->update(['passtoken' => $token, 'passtokendate' => getNow(), 'passtokenstatus' => 1]);
            $data['token'] = $token;
            $email = [
                "to" => $user->email,
                "subject" => "Me. Нууц үг сэргээх хүсэлт",
                "data" => $data,
                "template" => "ap::mail.resetPasswordMobile"
            ];
            dispatch(new SendMailJob($email));

            // MailService::sendMail($user->email, "mail.forgotPassword", $data, "ME апп систем. Forgotten password request");
            return mask_email($user->email) . ' цахим хаягт баталгаажуулах код илгээлээ.';
        }
        $this->error('RC000015');
    }

    /**
     * oi000070 - passTokenConfirm
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000070(Request $request)
    {
        $validated = $this->validate($request, [
            'token' => 'required',
            'email' => 'required|email',
        ], [
            'token.required' => ResponseCodeEnum::required,
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
        ]);

        $token = $validated['token'];
        $user = ApCustUser::select("*")
            ->selectRaw("
        CASE
            WHEN passtokendate IS NULL THEN 1
            WHEN passtokenstatus = 0 THEN 2
            WHEN passtokendate < NOW() - INTERVAL '1440 minutes' THEN 3
            ELSE 4
        END AS tokenstatus
    ")
            ->where('email', Str::lower($validated['email']))
            ->where("passtoken", $token)->first();
        if ($user) {
            $checkTkn = $this->checkToken($user->tokenstatus);
            if ($checkTkn['code']) {
                $token = generateRandomString(50);
                $user->update(['passtoken' => $token, 'passtokendate' => getNow(), 'passtokenstatus' => 1]);
                return ['token' => $token];
            } else {
                $this->error($checkTkn['msg']);
            }
        }
        $this->error('VC000005');
    }

    /**
     * oi000080 - passPolicy
     *
     * @return void
     */
    public function oi000080()
    {
        return GPInstGp::select([
            'itemname',
            'itemdesc',
            'itemvalue'
        ])->whereIn('itemname', [
            "PassWrongTimes",
            "PassLowLength",
            "PassHighLength",
            "ExpirePassDay",
            "PassHistCount",
            "DomainName",
            "Numbers",
            "UpperLetter",
            "LowerLetter",
            "Punctuation",
            "MustNumber",
            "MustUpperLetter",
            "MustLowerLetter",
            "MustPunctuation",
        ])->where('instid', 1)->get();
    }

    public static function checkToken($token)
    {
        $resp = array();
        if ($token == "4") {
            $resp['code'] = true;
        } else {
            $resp['code'] = false;
            switch ($token) {
                case "1":
                    $msg = "Reset request not created!";
                    break;
                case "2":
                    $msg = "Password changed previously by this token";
                    break;
                case "3":
                    $msg = "Token expired!";
                    break;
            }
            $resp['msg'] = $msg;
        }
        return $resp;
    }


    /**
     * oi000040 - Пасс солих
     *
     * @return void
     */
    public function oi000040(Request $request)
    {
        $validated = $this->validate($request, [
            'oldpassword' => 'required',
            'newpassword' => 'required',
            'newpassword2' => 'required',
        ], [
            'oldpassword.required' => ResponseCodeEnum::required,
            'newpassword2.required' => ResponseCodeEnum::required,
            'newpassword.required' => ResponseCodeEnum::required,
        ]);

        // $passpolicy = new DicPassPolicyService();
        // $password = $passpolicy->safeDecrypt($validated['newpassword']);
        $password = $validated['newpassword'];
        // $passpolicy->checkPassPolicy($password);

        if ($validated['newpassword'] != $validated["newpassword2"]) {
            $this->error('VC000005');
        }

        $user = ApCustUser::where('id', auth()->user()->id)
            ->where('statusid', '<>', '-1')->first();
        if (!$user) {
            $this->error('RC000008');
        }
        if (!Hash::check($validated["oldpassword"], $user->password)) {
            $this->error("VC000015");
        }
        $user->update([
            'password' => Hash::make($password),
            'updated_by' => auth()->user()->id,
            'updated_at' => Carbon::now(),
            'password_changed_at' => Carbon::now(),
            'mustchGPss' => 0,
        ]);
    }



    /**
     *  - Mobile app-ийн хэрэглэгч бүртгэх
     *
     * @return void
     */
    public function oi000580(Request $request)
    {
        // Эхлээд use_auth_type-г шалгах
        $validated =  $this->validate($request, [
            'email' => 'required|email',
            'use_auth_type' => 'required|in:GOOGLE,APPLE,EMAIL',
            'deviceid' => 'nullable',
            'pushToken' => 'nullable',
            'devicename' => 'nullable',
        ], [
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
            'use_auth_type.required' => ResponseCodeEnum::required,
        ]);

        // Хэрэв Google эсвэл Apple бол token шаардах
        if (in_array($request->use_auth_type, ['GOOGLE', 'APPLE'])) {
            $this->validate($request, [
                'token' => 'required',
            ], [
                'token.required' => ResponseCodeEnum::required,
            ]);
        }

        DB::beginTransaction();
        try {
            // Хэрэглэгч бүртгэлтэй эсэхийг шалгах
            $service = new ApAuthService();
            $app = $service->checkMobileApp($request);
            $existingUser = ApCustUser::where('email', Str::lower($validated['email']))
                ->where('statusid', '<>', -1);

            if ($app != null) {
                if ($app->app_data) {
                    $appData = json_decode($app->app_data, true);

                    if (@$appData['enable_register'] != 1) {
                        $this->error("RC000252");
                    }
                }
                $existingUser = $existingUser->where('app_id', $app->id);
            }

            $existingUser = $existingUser->first();
            if (!empty($existingUser) && @$existingUser->statusid == 1) {
                $this->error('RC000250');
            }

            if (!empty($existingUser) && @$existingUser->statusid == 0) {
                $user = $existingUser;
            } else {
                $user = new ApCustUser();
                $user->regno = '';
                $user->firstname = '';
                $user->created_by = 1;
            }

            $random_password = generateUserFriendlyPassword();

            $user->use_auth_type = $request->use_auth_type;
            $user->email = Str::lower($request->email);
            $user->device_token = $validated['pushToken'];
            $user->password = Hash::make(meapp_hmac($random_password));

            $appid = $app ? $app->id : null;
            $user->app_id = $appid;

            $provider = $request->use_auth_type == LoginTypeEnum::google ? LoginTypeEnum::google : ($request->use_auth_type == LoginTypeEnum::apple ? LoginTypeEnum::apple : '');

            if ($request->use_auth_type == LoginTypeEnum::google || $request->use_auth_type == LoginTypeEnum::apple) {
                $service = new ApAuthService();
                $res = $service->verify_token($request->token, $provider);
                if ($res['valid']) {
                    $user->statusid = 1;
                    $user->mustchGPss = '0';
                    $user->save();
                    return $user;
                } else {
                    $this->error('RC000004');
                }
            } else {
                $user->statusid = 0;
                $user->mustchGPss = '1';

                // OTP илгээх
                $timestamp = time();
                srand($timestamp);
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                $user->passtoken = $otp;
                $user->save();

                $email = [
                    "to" => $user->email,
                    "subject" => "Имэйл баталгаажуулалт.",
                    "data" => [
                        'otp' => $otp,
                        'appname' => $app ? $app->app_name : 'MeApp',
                    ],
                    "template" => "ap::mail.confirmMailOTP"
                ];
                dispatch(new SendMailJob($email));
                return $user;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->error('VC000009');
    }

    /**
     *  - Mobile app-ийн Имэйл OTP баталгаажуулалт/хэрэглэгч бүртгэх
     *
     * @return void
     */
    public function oi000590(Request $request)
    {
        $validated = $this->validate($request, [
            'email' => 'required|email',
            'otp' => 'required',
        ], [
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
            'otp.required' => ResponseCodeEnum::required,
        ]);
        $validated['email'] = Str::lower($validated['email']);

        $random_password = generateUserFriendlyPassword();

        $q = ApCustUser::where('email', $validated['email'])
            ->where('passtoken', $validated['otp']);

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        if ($app != null) {
            $user = $q->where('app_id', $app->id)->first();
        } else {
            $user = $q->first();
        }

        if (!$user) {
            $this->error('VC000009');
        }

        if ($user->statusid == 1) {
            $this->error('RC000250');
        }

        DB::beginTransaction();
        try {
            // $user->google_auth_key = $googleAuth->generateSecret();
            $user->mustchGPss = '1';
            $user->statusid = 1;
            $user->passtoken = rand(100000, 999999);

            $user->password = Hash::make(meapp_hmac($random_password));
            // $user->created_by = auth()->user() ? auth()->user()->id : 1;
            $user->save();
            $data = array();
            $data['random_password'] = $random_password;

            $data['hostname'] = config('app.frontoffice_url') . "/mobile-api/cust-user";
            $data['firstname'] = $user->firstname;
            // MailService::sendMail($user->email, 'mailRegister', $data, 'Бүртгэл амжилттай хийгдлээ.');

            $email = [
                "to" => $user->email,
                "subject" => "Бүртгэл амжилттай хийгдлээ.",
                "data" => $data,
                "template" => "ap::mail.mailRegister"
            ];

            dispatch(new SendMailJob($email));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $user;
    }


    /**
     *  - Mobile app-ийн хэрэглэгч Social-р нэвтрэх
     *
     * @return void
     */
    public function oi000600(Request $request)
    {
        $validated = $this->validate($request, [
            'email' => 'nullable',
            'use_auth_type' => 'required|in:GOOGLE,APPLE',
            'token' => 'required',
            'deviceid' => 'nullable',
            'pushToken' => 'nullable',
            'devicename' => 'nullable',
            'userIdentifier' => 'nullable'
        ]);



        $provider = $validated['use_auth_type'] == 'GOOGLE' ? LoginTypeEnum::google : ($validated['use_auth_type'] == 'APPLE' ? LoginTypeEnum::apple : '');
        if ($provider == '') {
            $this->error('RC000004');
        }

        $service = new ApAuthService();
        $verification = $service->verify_token($validated['token'], $provider);

        if ($verification['valid']) {
            $q = ApCustUser::where('email', $validated['email'] ?? @$verification['email'])
                ->where('statusid', '<>', '-1');

            $service = new ApAuthService();
            $app = $service->checkMobileApp($request);

            if ($app != null) {
                $user = $q->where('app_id', $app->id)->first();
            } else {
                $user = $q->first();
            }

            if (!$user) {
                if ($app != null) {
                    if ($app->app_data) {
                        $appData = json_decode($app->app_data, true);

                        if (@$appData['enable_register'] != 1) {
                            $this->error("RC000197");
                        }
                    }
                }

                $user = new ApCustUser();
                $random_password = generateUserFriendlyPassword();

                $user->use_auth_type = $request->use_auth_type;
                $user->email = Str::lower($request->email ?? $verification['email']);
                $user->device_token = '';
                $user->password = Hash::make(meapp_hmac($random_password));

                $appid = $app ? $app->id : null;
                $user->app_id = $appid;

                $provider = $request->use_auth_type == LoginTypeEnum::google ? LoginTypeEnum::google : ($request->use_auth_type == LoginTypeEnum::apple ? LoginTypeEnum::apple : '');

                $user->regno = '';
                $user->firstname = '';
                $user->created_by = 0;

                $user->save();
            }


            // Идэвхтэй токеныг цэвэрлэх
            GPUserAccessToken::where('userid', $user->id)
                ->where('channel', 'APP')
                ->where('name', 'login')
                ->delete();

            $token = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));

            //insert token
            GPUserAccessToken::create([
                'userid' => $user->id,
                'name' => 'login',
                'token' => $token,
                'abilities' => '',
                'last_used_at' => Carbon::now(),
                'channel' => 'APP'
            ]);

            $user->device_token = $validated['pushToken'] ?? "";

            // Login хийгдсэн түүх хадгална.
            AdLoginActivityLog::create([
                'userid' => $user->id,
                'agent' => $request->header('User-Agent'),
                'device_ip' => IpAddressResolver::resolve(),
                'statusid' => 1,
                'channel' => 1,
                'deviceid' => $validated['deviceid'] ?? 'Unknown',
                'devicename' => $validated['devicename'] ?? 'Unknown',
                'created_by' => $user->id
            ]);

            $userInfo = [
                'userid' => $user->id,
                'email' => $user->email,
                'phone' => $user->phoneuser ?? '',
                'regno' => $user->regno,
                'firstname' => $user->firstname,
                'firstname2' =>  cyrillic2latin($user->firstname),
                'lastname' => $user->lastname,
                'lastname2' =>  cyrillic2latin($user->lastname),
                'branch' => $user->branch,
                'perms' => [],
                'mustchGPss' => $user->mustchGPss
            ];

            return ['user' => $userInfo, 'token' => $token];
        }
        $this->error('RC000004');
    }


    /**
     *  - Дан систем холболтын тохиргоо авах
     *
     * @return void
     */
    public function oi000620(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required',
        ], [
            'instid.required' => ResponseCodeEnum::required,
        ]);

        $connectionConfig = GPConnConf::where('instid', $validated['instid'])->where('code', 'DAN')->where('statusid', '<>', -1)->first();

        if (!$connectionConfig) {
            $this->error('RC000004');
        } else {
            return $connectionConfig;
        }
    }


    /**
     *  - Дан систем холболтын тохиргоо авах
     *
     * @return void
     */
    public function oi000630(Request $request)
    {
        try {
            $v = $this->validate($request, [
                'code' => 'required|max:500',
                'instid' => 'required',
            ], [
                'code.required' => ResponseCodeEnum::required,
                'instid.required' => ResponseCodeEnum::required,
            ]);
            return $this->getAccessToken($v['code'], $v['instid']);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     *  - Бүртгүүлж болох байгууллагын жагсаалт
     *
     * @return void
     */
    public function oi000640(Request $request)
    {
        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);
        if ($app) {
            $appData = json_decode($app->app_data, true);
            if (@$appData['instList'] != null) {
                $instList = GPInstList::select('logo', 'id', 'name', 'name2')->whereIn('id', $appData['instList'])->where('statusid', '<>', -1)->get();
                return $instList;
            } else {
                $this->error("RC000027");
            }
        } else {
            $this->error("RC000010", ['id' => $request->header('X-App-Identifier')]);
        }
    }


    /**
     *  - Нэмэлт мэдээлэл бүртгэх
     *
     * @return void
     */
    public function oi000650(ApAddUserRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $userId = auth()->user()->id;
            $instId = $validated['instid'];
            $phone = $validated['phonenumber'];
            $position = $validated['position'];
            $workplace = $validated['employer'];
            $authUser = auth()->user();
            $regno = $authUser->regno ?? null;
            $updaterId = $authUser->id ?? 1;
            $now = Carbon::now();

            // Update ApCustUser
            $user = ApCustUser::where('id', $userId)->where('statusid', '<>', -1)->first();
            if (!$user) {
                throw new MeException("RC000010", ['id' => $userId]);
            }

            $user->update([
                'phone' => $phone,
                'updated_at' => $now,
                'updated_by' => $updaterId,
            ]);

            // Update ApCustomer
            $apCust = ApCustomer::where('regno', $regno)->where('instid', $instId)->where('statusid', '<>', -1)->first();
            if (!$apCust) {
                throw new MeException("RC000010", ['id' => $userId, 'type' => 'ApCustomer']);
            }

            $apCust->update([
                'phone' => $phone,
                'employment' => $position,
                'updated_at' => $now,
                'updated_by' => $updaterId,
            ]);

            // Update CrCustInd
            $crCustInd = CrCustInd::where('id1', $regno)->where('instid', $instId)->where('statusid', '<>', -1)->first();
            if (!$crCustInd) {
                throw new MeException("RC000010", ['id' => $userId, 'type' => 'CrCustInd']);
            }

            $crCustInd->update([
                'handphone' => $phone,
                'workplace' => $workplace,
                'position' => $position,
                'updated_at' => $now,
            ]);

            DB::commit();

            return "RC000206";
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getAccessToken($code, $instid)
    {
        try {
            $tokenConfig = GPConnConf::where('instid', $instid)->where('code', 'DAN')->first();
            if (!$tokenConfig) {
                throw new MeException("RC000237");
            }

            if (!$tokenConfig = json_decode($tokenConfig->config)) {
                throw new MeException("RC000238");
            }
            $postFields = array(
                "code" => $code,
                "grant_type" => $tokenConfig->grant_type, //"authorization_code"
                "client_id" => $tokenConfig->client_id,
                "client_secret" => $tokenConfig->client_secret,
                "redirect_uri" => $tokenConfig->redirect_uri, //"https://auth.sainscore.mn/front/dan/response",
            );

            $startTime = Carbon::now()->getTimestampMs();
            $r = GPLogRequestList::create([
                'userid' => 1,
                'url' => $tokenConfig->url_token,
                'request' => json_encode($postFields),
                'method' => 'CURL',
                'instid' => $instid,
            ]);

            $ch = curl_init($tokenConfig->url_token);
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_POST => 1,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_POSTFIELDS => $postFields,
                )
            );
            $result = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $r->update([
                'responsetime' => (Carbon::now()->getTimestampMs() - $startTime) / 1000,
                'response' => $curlError ?: $result,
                'responsecode' => $httpCode,
            ]);


            if (!$result) {
                throw new MeException("RC000239");
            }
            if (!$result = json_decode($result)) {
                throw new MeException("RC000240");
            }

            // $serviceConfig = GPConnConf::where('instid', 1)->where('code', 103)->first();
            // if (!$serviceConfig) {
            //     throw new MeException("RC000241");
            // }

            // if (!$serviceConfig = json_decode($serviceConfig->config)) {
            //     throw new MeException("RC000242");
            // }

            // Log::debug($serviceConfig->generate_token_uri);
            // return redirect($serviceConfig->generate_token_uri . "/" . $code);
            // dd($result);
            if (!property_exists($result, 'access_token')) {
                throw new MeException("RC000243");
            }

            $access_token = $result->access_token;
            $authorization = "Authorization: Bearer " . $access_token;


            $startTime = Carbon::now()->getTimestampMs();
            $r = GPLogRequestList::create([
                'userid' => 1,
                'url' => $tokenConfig->url_service,
                'request' => '{}',
                'method' => 'CURL',
                'instid' => $instid,
            ]);

            $ch = curl_init($tokenConfig->url_service);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $r->update([
                'responsetime' => (Carbon::now()->getTimestampMs() - $startTime) / 1000,
                'response' => $curlError ?: $result,
                'responsecode' => $httpCode,
            ]);

            if (!$result) {
                throw new MeException("RC000244");
            }

            if (!$services = json_decode($result)) {
                throw new MeException("RC000245");
            }
            // dd($result);
            //1. Save DAN response to DB
            $danResponse = ApDanResponse::create([
                'created_at' => Carbon::now(),
                'created_by' => auth()->user()->id,
                'code' => $code,
                'access_token' => $access_token,
                'services' => $result,
                'statusid' => 1,
                'userid' => auth()->user()->id,
                'instid' => $instid,
            ]);

            $citizenCardInfo = null;
            foreach ($services as $service) {
                if (property_exists($service, 'services')) {
                    if (property_exists($service->services, 'WS100101_getCitizenIDCardInfo')) {
                        if (property_exists($service->services->WS100101_getCitizenIDCardInfo, 'response')) {
                            $citizenCardInfo = $service->services->WS100101_getCitizenIDCardInfo->response;
                        }
                    }
                }
            }

            if ($citizenCardInfo) {
                return $this->createDanUser($code, $instid);
            } else {
                throw new MeException("RC000245");
            }
        } catch (Exception $e) {
            throw new MeException($e->getMessage());
        }
    }

    public function getDanResponse($code)
    {
        $danResponse = ApDanResponse::where('code', $code)->first();

        if (!$danResponse)
            throw new MeException("RC000246");

        if (!$services = json_decode($danResponse->services)) {
            throw new MeException("RC000247");
        }

        $citizenCardInfo = null;
        foreach ($services as $service) {
            if (property_exists($service, 'services')) {
                if (property_exists($service->services, 'WS100101_getCitizenIDCardInfo')) {
                    if (property_exists($service->services->WS100101_getCitizenIDCardInfo, 'response')) {
                        $citizenCardInfo = $service->services->WS100101_getCitizenIDCardInfo->response;
                    }
                }
            }
        }
        if (!$citizenCardInfo)
            throw new MeException("SR1187");
        return [
            'firstName' => $citizenCardInfo->firstname,
            'lastName' => $citizenCardInfo->lastname,
            'regno' => $citizenCardInfo->regnum,
        ];
    }

    public function createDanUser($code, $instid)
    {
        DB::beginTransaction();

        try {
            if (empty($code)) {
                $this->error("RC000010", ['id' => 'Code']);
            }

            if (empty($instid)) {
                $this->error("RC000010", ['id' => 'instid']);
            }

            // Дан хариу мэдээлэл авах
            $danResponse = ApDanResponse::where("code", $code)
                ->where('instid', $instid)
                ->firstOrFail();

            $services = json_decode($danResponse->services);
            if (!$services) {
                throw new MeException("RC000246");
            }

            $cardInfo = $marriageInfo = $addressInfo = $salaryInfo = null;

            foreach ($services as $service) {
                if (isset($service->services)) {
                    $s = $service->services;
                    $cardInfo = $s->WS100101_getCitizenIDCardInfo->response ?? $cardInfo;
                    $marriageInfo = $s->WS100104_getCitizenMarriageInfo->response ?? $marriageInfo;
                    $addressInfo = $s->WS100103_getCitizenAddressInfo->response ?? $addressInfo;
                    $salaryInfo = $s->WS100501_getCitizenSalaryInfo->response ?? $salaryInfo;
                }
            }

            if (empty($cardInfo?->regnum)) {
                throw new MeException("RC000246");
            }

            $data = [
                'id1typecode' => 'YY99999999',
                'id1' => strtoupper($cardInfo->regnum),
                'id2typecode' => '999999999999',
                'id2' => $cardInfo->civilId ?? '',
                'sexcode' => $cardInfo->gender === 'Эрэгтэй' ? 1 : 0,
                'birthdate' => Carbon::make($cardInfo->birthDateAsText)?->format('Y-m-d'),
                'familyname' => strtoupper($cardInfo->surname ?? ''),
                'familyname2' => strtoupper(cyrillic2latin($cardInfo->surname ?? '')),
                'lname' => strtoupper($cardInfo->lastname ?? ''),
                'lname2' => strtoupper(cyrillic2latin($cardInfo->lastname ?? '')),
                'name' => strtoupper($cardInfo->firstname ?? ''),
                'name2' => strtoupper(cyrillic2latin($cardInfo->firstname ?? '')),
                'custno' => GPInstController::getCustomerSeq($instid),
                'countrycode' => '496', // Монгол
                'email' => auth()->user()->email ?? '',
                'prevstatusid' => 1,
                'statusid' => 1,
                'instid' => $instid,
                'segcode' => '81', // Монгол улсын иргэн
                'workplace' => '',
                'position' => '',
                'inducode' => '99', // Иргэн
                'indusubcode' => '02', // Ажилгүй
                'maritalstatuscode' => @$marriageInfo->isMarried == 1 ? 1 : 2,
                'sourcecode' => SourceCodeEnum::APP,
                'instid' => $instid,
                'custtypecode' => 0,
            ];

            // Зураг оруулах
            if (!empty($cardInfo->image)) {
                try {
                    // Get the extension
                    $fileName = substr(uniqid('img_', true), 0, 20) . '.png';
                    $imageBinary = pg_escape_bytea($cardInfo->image);

                    $photo = CrCustImage::create([
                        'image' => $imageBinary,
                        'statusid' => 1,
                        'instid' => $instid,
                        'filename' => $fileName,
                        'name' => $fileName,
                    ]);

                    $data['image'] = $photo->id ?? null;
                } catch (Exception $ex) {
                    Log::error($ex->getMessage());
                }
            }

            // Онлайн теллер
            $onlineteller = CoreService::getInstGp($instid, 'ONLINETELLERNUMBER');
            $user = GPInstUser::where('instid', $instid)->find($onlineteller);

            $data['brchno'] = $user->brchno ?? '';
            $data['created_name'] = $user->name ?? '';
            $data['created_by'] = $user->id ?? 1;
            $data['managerno'] = $user->id ?? 1;
            $data['managername'] = $user->name ?? '';

            // Кор харилцагч бүртгэх
            $crCust = CrCustInd::where('instid', $instid)
                ->where('id1',  $data['id1'])
                ->where('statusid', '<>', -1)
                ->first();

            if ($crCust) {
                $this->error("RC000175", [
                    'id' => $data['id1']
                ]);
            }

            $crCust = new CrCustInd($data);
            $crCust->save();

            if ($crCust) {
                $address = [];
                $address['custtypecode'] = $crCust->custtypecode;
                $address['custid'] = $crCust->id;
                $address['addrtypecode'] = 1;
                $address['state'] = $addressInfo->aimagCityCode ?? '';
                $address['region'] = $addressInfo->soumDistrictCode ?? '';
                $address['subregion'] = $addressInfo->bagKhorooCode ?? '';
                $address['address'] = $addressInfo->fullAddress ?? '';
                $address['statusid'] = 1;
                $address['instid'] = $instid;
                $address['created_by'] = $user->id ?? 1;
                $address['updated_by'] = $user->id ?? 1;
                CrCustAddr::create($address);

                $service = new ApAuthService();
                $value =  $service->getAvgSalary(@$salaryInfo->list, 6);

                $key = 'c_salary';
                $service->createAddField($instid, $key, $value, $crCust);

                $key = 'c_salary_date';
                $datenow = Carbon::now();
                $service->createAddField($instid, $key, $datenow, $crCust);
            }


            // Апп харилцагч бүртгэх
            $cust = ApCustomer::where('instid', $instid)
                ->where('regno', $crCust->id1)
                ->where('statusid', '<>', -1)
                ->first();
            if ($cust) {
                $this->error("RC000175", [
                    'id' => $crCust->id1
                ]);
            }


            $data = [
                'cif' => $crCust->custno,
                'corrid' => $crCust->id,
                'familyname' => $crCust->familyname,
                'familyname2' => $crCust->familyname2,
                'lname' => $crCust->lname,
                'lname2' => $crCust->lname2,
                'fname' => $crCust->name,
                'fname2' => $crCust->name2,
                'gender' => $crCust->sexcode,
                'regno' => $crCust->id1,
                'register_mask_code' => $crCust->id1typecode,
                'nationality' => $crCust->nationcode ?? '',
                'birthday' => Carbon::make($crCust->birthdate),
                'lang' => $crCust->langcode ?? '',
                'ethnicity' => $cardInfo->nationality ?? '',
                'segment' => $crCust->segcode,
                'employment' => $crCust->profession ?? '',
                'education' => $crCust->educode ?? '',
                'maritalstatus' => $crCust->maritalstatuscode,
                'phone' => $crCust->handphone ?? '',
                'email' => $crCust->email ?? '',
                'familysize' => $crCust->familymembercount ?? null,
                'industry' => $crCust->inducode,
                'shortname' => mb_substr($crCust->lname, 0, 1) . ". " . $crCust->name,
                'shortname2' => mb_substr($crCust->lname2, 0, 1) . ". " . $crCust->name2,
                'ispolitical' => $crCust->ispolitical ?? 0,
                'birthplace' => $cardInfo->birthPlace ?? '',
                'region' => $addressInfo->aimagCityName ?? '',
                'subregion' => $addressInfo->soumDistrictName ?? '',
                'address' => $addressInfo->fullAddress ?? '',
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ];


            $cust = ApCustomer::create($data);

            // Апп хэрэглэгч дээрх мэдээллийг шинэчлэх холбох /Регистер дугаар, овог нэр, хаяг/
            $userExist = ApCustUser::where('email', $crCust->email)
                ->where('app_id', 33)
                ->where('statusid', '<>', -1)
                ->first();

            $instConnCust = new InstCustConnService();

            if ($userExist) {
                $photoUrl = null;
                // Зураг оруулах
                if (!empty($cardInfo->image)) {
                    try {
                        $base64WithPrefix = 'data:image/jpg;base64,' . $cardInfo->image;

                        // Get the extension
                        $fileName = substr(uniqid('img_', true), 0, 20) . '.png';
                        $imageBinary = pg_escape_bytea($cardInfo->image);

                        $photo = ApUserImage::create([
                            'image' => $imageBinary,
                            'statusid' => 1,
                            'name' => $fileName,
                            'created_by' => auth()->user()->id,
                            'created_at' => Carbon::now(),
                        ]);

                        $photoUrl = $photo->id ?? null;
                    } catch (Exception $ex) {
                        Log::error($ex->getMessage());
                    }
                }


                $userExist->update([
                    'regno' => $crCust->id1,
                    'firstname' => $crCust->name,
                    'lastname' => $crCust->lname,
                    'region' => $addressInfo->aimagCityCode ?? '',
                    'subregion' => $addressInfo->soumDistrictCode ?? '',
                    'address' => $addressInfo->fullAddress ?? '',
                    'photo_url' => $photoUrl,
                ]);

                $instConnCust->connect($instid, $userExist->id);

                DB::commit();
                return $userExist;
            }

            DB::commit();
            return [
                'cust' => $cust,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("DAN USER CREATE FAILED", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
