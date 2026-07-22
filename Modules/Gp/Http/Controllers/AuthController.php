<?php

namespace Modules\Gp\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use App\Resolvers\IpAddressResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstUserIp;
use Modules\Gp\Entities\GpInstBrch;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpUserAccessToken;
use Modules\Gp\Entities\Views\VwGpInstUser;
use Modules\Gp\Entities\Views\VwGpInstUserRole;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Http\Services\GoogleAuthenticator\GoogleAuthenticator;
use Modules\Gp\Jobs\SendMailJob;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdLoginActivityLog;
use Modules\Ad\Entities\AdNotifications;
use Modules\Ad\Entities\AdSvUser;
use Modules\Ad\Http\Controllers\AdMeLpUserController;
use Modules\Cr\Entities\Views\VwCrCustNotifications;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC lo000100
     * @return Response
     */
    public function login(Request $request)
    {
        return $this->loginToServer($request, false);
    }

    public function loginWithGoogle(Request $request)
    {
        $validated = $this->validate($request, [
            'username' => 'required|max:50',
            'password' => 'required',
            // 'secret'=>'required',
            'code' => 'required',
        ]);
        $googleAuth = new GoogleAuthenticator();
        $validated['username'] = Str::lower($validated['username']);
        $user = GpInstUser::where('email', $validated['username'])->where('status', '1')->first();
        if ($googleAuth->checkCode($user->google_auth_key, $validated['code'])) {
            return $this->loginToServer($request, true);
        } else {
            return response()->json('Баталгаажуулах код буруу байна.', 500);
        }
    }

    public function loginToServer(Request $request, $checkGoogleAuth)
    {
        // return auth()->user();
        $validated = $this->validateMe($request, [
            'username' => 'required',
            'password' => 'required',
            'code' => 'nullable',
        ], [
            'username.required' => "VC000001",
            'password.required' => "VC000002",
        ]);
        $validated['username'] = Str::lower($validated['username']);

        $user = GpInstUser::where('username', $validated['username'])->where('statusid', '<>', '-1')->first();
        if (isset($validated['code']) && !empty($validated['code'])) {
            $googleAuth = new GoogleAuthenticator();
            if ($googleAuth->checkCode($user->google_auth_key, $validated['code'])) {
                $checkGoogleAuth = true;
            } else {
                $this->error('Баталгаажуулах код буруу байна.');
            }
        }

        if ($user) {
            if (@$user->iprest == 1) {
                $ipRecord = GpInstUserIp::where('userid', $user->id)
                    ->where('ip_address', IpAddressResolver::resolve())
                    ->where('instid', $user->instid)
                    ->where('statusid', '>', 0)
                    ->first();
                if (!$ipRecord) {
                    throw new MeException("RC000265");
                }
            }

            if ($user->statusid != "1") {
                // Deactive
                throw new MeException("RC000005");
            }

            $limit = (int) CoreService::getInstGp($user->instid, "PassWrongTimes");
            if ($limit != 0 && $user->passwrong >= $limit) {
                $this->error('RC000168');
            }

            $userPassword = $user->password;
            // $validated['password'] = $passpolicy->safeDecrypt($validated['password']);
            // return $validated['password'];
            if (Hash::check($validated['password'], $userPassword)) {
                //reset wrong pass count
                $user->passwrong = 0;
                $user->save();

                // Google authenticator идэвхжүүлж логин хийгдсэн эсэх
                if (!$checkGoogleAuth && $user->use_google_auth == "1") {
                    return ['use_google_auth' => 1, 'google_auth_key' => true];
                }

                $startDate = new Carbon($user->startdate);
                $endDate = new Carbon($user->enddate);
                $txndate = CoreService::getTxnDate($user->instid);
                $gldate = CoreService::getGlDate($user->instid);
                if ($txndate == '1900-01-01') {
                    $txndate = Carbon::now();
                } else {
                    $txndate = new Carbon($txndate);
                }
                if ($gldate == '1900-01-01') {
                    $gldate = Carbon::now();
                } else {
                    $gldate = new Carbon($gldate);
                }
                // Хэрэглэгчийн хүчинтэй хугацааг шалгана.
                if (!$txndate->between($startDate, $endDate)) {
                    $this->error('RC000123');
                }
                //generate token
                $token = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));

                // Идэвхтэй токеныг цэвэрлэх
                // $user->tokens()->delete();
                if ($user->tokenlimit == 1) {
                    GpUserAccessToken::where('userid', $user->id)
                        ->where('channel', 'BACK')
                        ->where('name', 'login')
                        ->delete();
                } else {
                    $tokens = GpUserAccessToken::where('userid', $user->id)->where('channel', 'BACK')->get();

                    if ($user->tokenlimit <= count($tokens)) {
                        $items = GpUserAccessToken::where('userid', $user->id)->where('channel', 'BACK')
                            ->orderBy('id', 'DESC')
                            ->skip($user->tokenlimit - 1)
                            ->pluck('id');
                        if ($items) {
                            GpUserAccessToken::where('userid', $user->id)
                                ->whereIn('id', $items)
                                ->delete();
                        }
                    }
                }

                $inst = GpInstList::where('id', $user->instid)->first();
                if ($inst->statusid == 0) {
                    throw new MeException("RC000224", ["name" => $inst->name]);
                }

                //insert token
                GpUserAccessToken::create([
                    'userid' => $user->id,
                    'name' => 'login',
                    'token' => $token,
                    'abilities' => '',
                    'last_used_at' => getNow(),
                    'created_at' => getNow(),
                    'updated_at' => null,
                    'channel' => 'BACK'
                ]);

                AdLoginActivityLog::create([
                    'userid' => $user->id,
                    'agent' => $request->header('User-Agent'),
                    'device_ip' => IpAddressResolver::resolve(),
                    'statusid' => 1,
                    'channel' => 'BACK',
                    'deviceid' => 'Unknown',
                    'devicename' => 'Unknown',
                    'created_by' => $user->id
                ]);

                $brchno = GpInstBrch::where('brchno', $user->brchno)
                    ->where('instid', $user->instid)
                    ->first();
                // $token_lifetime = (int) $passpolicy->getPolicyValue("TOKEN_LIFETIME");
                $svcount = AdSvUser::where('userid', $user->id)
                    ->where('instid', $user->instid)
                    ->where('svtype', 0)
                    ->where('statusid', 1)->count();
                $allowtxn = true;
                if ($svcount > 0) {
                    $svonlinecount = AdSvUser::where('userid', $user->id)
                        ->where('instid', $user->instid)
                        ->where('svtype', 1)
                        ->where('statusid', 1)->count();
                    if ($svonlinecount == 0) {
                        $allowtxn = false;
                    }
                }
                $changerate = (new GpController())->checkActionCode("tr010300", $user->id);
                $userInfo = [
                    'userid' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'lname' => $user->lname,
                    'brchno' => $user->brchno,
                    'perms' => [],
                    'is_temp' => $user->is_temp,
                    'instid' => $user->instid,
                    'instname' => $inst->name,
                    'brchno_name' => $user->brchno . ' - ' . $brchno->name,
                    'username' => $user->name,
                    'checksupervisor' => $svcount > 0 ? true : false,
                    'allowtxn' => $allowtxn,
                    'changerate' => $changerate,
                ];
                $token_lifetime = (int) CoreService::getInstGp($user->instid, "TOKEN_LIFETIME");
                $download_list = (boolean) CoreService::getInstGp($user->instid, "DownloadList");
                $defalutListFilterType = (int) CoreService::getInstGp($user->instid, "defalutListFilterType");
                $instcolor = CoreService::getInstGp($user->instid, "INSTPRIMARYCOLOR") ?? "#3B6EB6";


                $unreadCount = VwCrCustNotifications::where('statusid', '<>', -1)
                    ->where('notifstatusid', '<>', -1)
                    ->where('notiftype', 'WEB')
                    ->where('custid', $user->id)
                    ->where('is_read', 0)
                    ->count();


                return [
                    'user' => $userInfo,
                    'token' => $token,
                    'txn_date' => $txndate->format('Y-m-d'),
                    'gl_date' => $gldate->format('Y-m-d'),
                    'server_ip' => config('app.ip_address'),
                    'token_time' => $token_lifetime,
                    'inst_color' => $instcolor,
                    'inst_typeid' => $inst->inst_typeid,
                    'download_list' => $download_list,
                    'defalutListFilterType' => $defalutListFilterType,
                    'unreadCount' => $unreadCount
                ];
            }

            //increase wrong pass count
            $user->passwrong = $user->passwrong + 1;
            $user->save();
        }
        throw new MeException("RC000004");
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        if ($user && gettype($user) != 'string') {
            $token = $request->bearerToken();
            GpUserAccessToken::where('userid', $user->id)
                ->where('token', $token)
                ->where('channel', 'BACK')
                ->where('name', 'login')->delete();
        } else {
            throw new MeException("RC000006");
        }
    }

    public function checkAuth(Request $request)
    {
        $user = auth()->user();
        if (empty($user)) {
            $this->error('RC000006');
        }
        if (gettype($user) == 'string') {
            $this->error('RC000006');
        }

        if (@$user->iprest == 1) {
            $ipRecord = GpInstUserIp::where('userid', $user->id)
                ->where('ip_address', IpAddressResolver::resolve())
                ->where('instid', $user->instid)
                ->where('statusid', '>', 0)
                ->first();
            if (!$ipRecord) {
                throw new MeException("RC000265");
            }
        }


        $inst = GpInstList::where('id', $user->instid)->first();
        $brchno = GpInstBrch::where('brchno', $user->brchno)
            ->where('instid', $user->instid)->first();
        $perms = [];
        $svcount = AdSvUser::where('userid', $user->id)
            ->where('instid', $user->instid)
            ->where('svtype', 0)
            ->where('statusid', 1)->count();
        $allowtxn = true;
        if ($svcount > 0) {
            $svonlinecount = AdSvUser::where('userid', $user->id)
                ->where('instid', $user->instid)
                ->where('svtype', 1)
                ->where('statusid', 1)->count();
            if ($svonlinecount == 0) {
                $allowtxn = false;
            }
        }
        $changerate = (new GpController())->checkActionCode("tr010300", $user->id);
        $userInfo = [
            'userid' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'name' => $user->name,
            'lname' => $user->lname,
            'brchno' => $user->brchno,
            'perms' => $perms,
            'is_temp' => $user->is_temp,
            'instid' => $user->instid,
            'instname' => $inst->name,
            'brchno_name' => $user->brchno . ' - ' . $brchno->name,
            'username' => $user->name,
            'checksupervisor' => $svcount > 0 ? true : false,
            'allowtxn' => $allowtxn,
            'changerate' => $changerate,
            // 'token_time' => $token_lifetime,
        ];
        $txndate = new Carbon(CoreService::getTxnDate($user->instid));
        $gldate = new Carbon(CoreService::getGlDate($user->instid));
        $token_lifetime = (int) CoreService::getInstGp($user->instid, "TOKEN_LIFETIME");
        $instcolor = CoreService::getInstGp($user->instid, "INSTPRIMARYCOLOR") ?? "#3B6EB6";
        $download_list = (boolean) CoreService::getInstGp($user->instid, "DownloadList");
        $defalutListFilterType = (int) CoreService::getInstGp($user->instid, "defalutListFilterType");
        $token = $request->bearerToken();
        $auth = GpUserAccessToken::where('token', $token)
            ->where('channel', 'BACK')->where('name', 'login')->first();

        $unreadCount = VwCrCustNotifications::where('statusid', '<>', -1)
            ->where('notifstatusid', '<>', -1)
            ->where('notiftype', 'WEB')
            ->where('custid', $user->id)
            ->where('is_read', 0)
            ->count();

        $data = [
            'user' => $userInfo,
            'txn_date' => $txndate->format('Y-m-d'),
            'gl_date' => $gldate->format('Y-m-d'),
            'server_ip' => config('app.ip_address'),
            'token_time' => $token_lifetime,
            'inst_color' => $instcolor,
            'inst_typeid' => $inst->inst_typeid,
            'download_list' => $download_list,
            'defalutListFilterType' => $defalutListFilterType,
            'unreadCount' => $unreadCount
        ];

        if (!empty($auth->abilities)) {
            $adminuser = GpInstUser::select([
                'id',
                'name',
                'lname',
            ])->where('id', $auth->userid)->first();
            $data['admin_user'] = $adminuser;
        }

        return $data;
    }

    public function changeInstUserPassword(Request $request)
    {
        $validated = $this->validateMe(
            $request,
            [
                'old_password' => 'required',
                'password' => 'required|confirmed',
            ],
            [
                'old_password.required' => 'VC000004',
                'password.required' => 'VC000003',
                'password.confirmed' => 'VC000005',
            ]
        );
        $password = $validated['password'];
        $user = GpInstUser::where("id", auth()->user()->id)->first();
        if ($user) {
            // MeLP ruu shine password yvuulah
            $admelpcontroller = new AdMeLpUserController();
            $admelpcontroller->handlePasswordChange($user, $password);
            if (Hash::check($validated['old_password'], $user->password)) {
                $passHistCount = (int) CoreService::getInstGp($user->instid, "PassHistCount");
                $histories = $user->passwordHistories()->orderBy('createdate', 'DESC')->take($passHistCount)->get();
                foreach ($histories as $history) {
                    if (Hash::check($validated['password'], $history->password)) {
                        throw new MeException("RC000007");
                    }
                }
                $user->changePassword($password);
                $user->update([
                    'passwrong' => 0,
                    'updated_at' => getNow(),
                    'password_changed_at' => getNow(),
                    'updated_by' => $user->userid,
                ]);
                return 'Нууц үг амжилттай солигдлоо.';
            } else {
                $this->error("VC000015");
            }
        }
        $this->error('RC000008');
    }

    public function forgotPassword(Request $request)
    {
        $validated = $this->validateMe($request, [
            'username' => 'required',
        ], [
            'username.required' => "VC000001",
        ]);
        $validated['username'] = Str::lower($validated['username']);
        $user = GpInstUser::where('username', $validated['username'])->where('statusid', '<>', '-1')->first();

        if ($user) {
            $data['name'] = $user->name;
            if ($user->tokenstatus == "3") {
                $token = $user->passtoken;
            } else {
                $token = generateRandomString(50);
            }
            $user->update(['passtoken' => $token, 'passtokendate' => getNow(), 'passtokenstatus' => 1]);

            $data['token_life_time'] = "15";
            $data['token'] = $token;
            $data['url'] = config('app.backoffice_url');
            $email = [
                "to" => $user->email,
                "subject" => "ME-CORE. Forgotten password request",
                "data" => $data,
                "template" => "GP::emails.reset-password"
            ];
            dispatch(new SendMailJob($email));
            return mask_email($user->email) . ' цахим хаягт нууц үг сэргээх холбоос илгээсэн.';
        }
        $this->error('RC000015');
    }

    public function resetPassword(Request $request)
    {
        $validated = $this->validateMe($request, [
            'token' => 'required|max:64',
            'password' => 'required|confirmed',
        ], [
            'token.required' => 'VC000006',
            'password.required' => 'VC000002',
            'password.confirmed' => 'VC000005',
        ]);
        // $passpolicy = new DicPassPolicyService();
        // $password = $passpolicy->safeDecrypt($validated['password']);
        $password = $validated['password'];
        // $passpolicy->checkPassPolicy($password);

        $token = $validated['token'];
        $user = GpInstUser::where("passtoken", $token)->first();
        if ($user) {
            try {
                // MeLP ruu shine password yvuulah
                $admelpcontroller = new AdMeLpUserController();

                $admelpcontroller->handlePasswordChange($user, $password);
            } catch (MeException $e) {
                Log::error($e);
            }
            try {
                DB::beginTransaction();
                $histories = $user->passwordHistories()->orderBy('createdate', 'DESC')->take(3)->get();
                foreach ($histories as $history) {
                    if (Hash::check($validated['password'], $history->password)) {
                        throw new MeException("RC000007");
                    }
                }
                $user->changePassword($password);
                $user->update([
                    'passwrong' => 0,
                    'updated_at' => getNow(),
                    'password_changed_at' => getNow(),
                    'updated_by' => $user->userid,
                    'passtoken' => ''
                ]);
                DB::commit();
                return 'Нууц үг амжилттай солигдлоо.';
            } catch (MeException $e) {
                DB::rollBack();
                throw $e;
            }
        }
        $this->error('RC000008');
    }

    public function profile(Request $request)
    {
        $validated = $this->validateMe($request, [
            'not_get_role' => 'nullable|boolean'
        ]);
        $user = auth()->user();
        $id = $user->id;
        $GPinstuser = VwGpInstUser::where("id", $id)->first();
        if ($GPinstuser) {
            if (
                empty($validated['not_get_role'])
                || @$validated['not_get_role'] == false
            ) {
                $roles = VwGpInstUserRole::where('userid', $id)
                    ->where('statusid', '<>', -1)->get();
            } else {
                $roles = [];
            }
            $ipList = [];
            if($user->iprest == 1) {
                $ipList = GpInstUserIp::where('userid', $id)->where('statusid', '>', 0)->get();
            }

            $GPinstuser->iplist = $ipList;
            $GPinstuser->use_google_auth = $user->use_google_auth;
            $GPinstuser->google_auth_key = $user->google_auth_key;
            return ['user' => $GPinstuser, 'roles' => $roles];
        } else {
            $this->error('RC000027');
        }
    }

    /**
     * lo020100 - Google Authenticator код шалгах
     *
     * @return void
     */
    public function lo020100(Request $request)
    {
        $validated = $this->validate($request, [
            'google_auth_key' => 'required|string',
            'auth_code' => 'required',
        ]);
        $res = array();
        $service = new GoogleAuthenticator();
        if ($service->checkCode($validated['google_auth_key'], $validated['auth_code'])) {
            $res['isSuccess'] = true;
        } else {
            $res['isSuccess'] = false;
        }
        return $res;
    }

    /**
     * lo020200 - Google Authenticator QR код үүсгэх
     *
     * @return void
     */
    public function lo020200(Request $request)
    {
        $validated = $this->validate($request, [
            'secret' => 'required|string',
        ]);
        $user = auth()->user();
        $service = new GoogleAuthenticator();
        $data = $service->getUrl(config('app.name'), $user->email, $validated['secret']);
        return $data;
    }

    /**
     * lo020300 - Google Authenticator QR код засварлах
     *
     * @return void
     */
    public function lo020300(Request $request)
    {
        $validated = $this->validate($request, [
            'use_google_auth' => 'required',
        ]);
        $user = GpInstUser::where('id', auth()->user()->id)->where('statusid', '1')->first();
        $user->use_google_auth = $validated['use_google_auth'];
        $user->updated_by = auth()->user()->id;
        $user->save();
    }

    /**
     * lo030100	- Ирсэн мэдэгдлүүд авах
     *
     * @return void
     */
    public function lo030100(Request $request)
    {
        $user = auth()->user();
        $notifications = VwCrCustNotifications::select("id", "is_read", "url", "title", "description", "created_at")
            ->where('statusid', '<>', -1)
            ->where('notifstatusid', '<>', -1)
            ->where('notiftype', 'WEB')
            ->where('custid', $user->id)
            ->orderBy('id', 'desc')
            ->get();
        return $notifications;
    }

    /**
     * lo010101	- Нэвтэрсэн хэрэглэгчийн түүхийг харуулах
     *
     * @return void
     */
    public function lo010101(Request $request)
    {
        $user = auth()->user();

        if ($user->isadmin == 1 && $user->instid == 1) {
            $v = $request->userid ?? $user->id;
        } else {
            $v = $user->id;
        }
        $logs = AdLoginActivityLog::where('userid', $v)
            ->where('channel', 'BACK')
            ->orderBy('created_at', 'desc');

        return $this->getGridData($request, $logs);
    }
}
