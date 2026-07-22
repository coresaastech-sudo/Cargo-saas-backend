<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPInstUser;
use Modules\Ad\Http\Requests\AdMeLpUserRequest;
use Modules\Gp\Entities\GPConnConf;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Enums\StatusCodeEnum;

class AdMeLpUserController extends Controller
{
    public function sendRequestMelp($AC, $data, $instid = null)
    {
        if (empty($instid)) {
            $instid = auth()->user()->instid;
        }

        $providerConf = VwGPProviderConf::where('code', 'MELP')->where('instid', $instid)->where('statusid', 1)->first();
        if (empty($providerConf)) {
            $this->error('RC000202', [
                "type" => "MELP"
            ]);
        }
        $connConf = GPConnConf::where('instid', $instid)->where('id', $providerConf->connid)->where('statusid', 1)->first();
        if (empty($connConf)) {
            $this->error('RC000203', [
                "type" => "MELP"
            ]);
        }

        $providerConfig = json_decode($providerConf->config, true);
        $connConf = json_decode($connConf->config, true);
        $req['url'] = $connConf['url'];
        $req['instid'] = $providerConfig['instid'];
        $req['username'] = $providerConfig['username'];

        $req['password'] = safeDecrypt($providerConf->sec1);

        //Core-ын username бүхий хэрэглэгчид лавлах - ad031006
        $header = [
            'AC' => $AC,
            'melp-username' => $req['username'],
            'melp-signature' => $req['password']
        ];

        $startTime = Carbon::now()->getTimestampMs();
        $url = $req['url'];

        $r = new GPLogRequestList();
        $user = auth()->user();
        $r->userid = ($user && gettype($user) != 'string') ? $user->id : 1;
        $r->url = $url;
        $r->method = 'GET';
        $r->save();


        $http = Http::withHeaders($header)->post($req['url'], $data);

        $r->response = (string) $http->getBody();
        $r->responsecode = $http->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();


        if ($http->ok()) {
            $token = json_decode((string) $http->getBody(), true);
            if ($token['response_code'] == ResponseCodeEnum::success) {
                return $token['response'];
            } else {
                $this->error($token['response']);
            }
        } else {
            $this->error("MELP рүү хүсэлт илгээхэд алдаа гарлаа.");
        }
    }
    /**
     * Display a listing of the resource.
     * AC ad020002
     * index
     * @return Response
     */
    public function ad020002(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $reqUser = GPInstUser::where('instid', $user->instid)->where('id', $validated['id'])->where('statusid', 1)->first();
        if (empty($reqUser)) {
            $this->error('RC000010', [
                "id" => $validated['id']
            ]);
        }

        $melpMultiInst = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'melpMultiInst')->where('itemvalue', 1)->first();
        $lpParamName = !empty($melpMultiInst) ? 'username' . $user->instid : 'username';

        $data = $this->sendRequestMelp('ad031006', [
            'username' => $reqUser->username,
            'paramname' => $lpParamName,
        ]);
        return $data;
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function ad020202(AdMeLpUserRequest $request)
    {
        $user = auth()->user();
        $providerConf = VwGPProviderConf::where('code', 'MELP')->where('instid', $user->instid)->where('statusid', 1)->first();
        if (empty($providerConf)) {
            $this->error('RC000202', [
                "type" => "MELP"
            ]);
        }
        $providerConfig = json_decode($providerConf->config, true);


        $validated = $request->validated();
        $user = auth()->user();
        $reqUser = GPInstUser::where('instid', $user->instid)
            ->where('id', $validated['id'])
            ->where('statusid', 1)->first();
        if (empty($reqUser)) {
            $this->error('RC000010', [
                "id" => $validated['id']
            ]);
        }

        $validated['roleno'] = $this->parseRoleno($validated['roleno'] ?? []);

        if (empty($validated['roleno'])) {
            $this->error("Эрхийн бүлэг холболт хийнэ үү");
        }

        $branchlist = $this->sendRequestMelp('ad010401', [])['data']; // Lp-s irsen response dotor dahiad data field bdg
        $corebranch = $reqUser->brchno;
        if (!isset($providerConfig['branchlist']) || !isset($providerConfig['branchlist'][$corebranch])) {
            $this->error("MELP provider бүртгэл дээр $corebranch салбар бүртгэлгүй байна.");
        }
        $Lpbranchno = $providerConfig['branchlist'][$corebranch];
        $Lpbranchid = 0;

        foreach ($branchlist as $item) {
            if ($item['brchno'] == $Lpbranchno) {
                $Lpbranchid = $item['id'];
            }
        }

        $melpMultiInst = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'melpMultiInst')->where('itemvalue', 1)->first();

        // melpMultiInst үед: ижил имэйлтэй MELP хэрэглэгч аль хэдийн байвал ШИНЭ
        // хэрэглэгч үүсгэхгүй — тэр хэрэглэгчийг ашиглаж зөвхөн энэ inst-ийн параметр,
        // insts, role-ийг нэмнэ (нэг хүн = нэг MELP хэрэглэгч). MeCore дээр олон inst
        // дээр ижил имэйлтэй хэрэглэгч бүртгэгдсэн ч MELP дээр 1 хэрэглэгч байна.
        $existingLpUser = null;
        if (!empty($melpMultiInst) && !empty($reqUser->email)) {
            $existingLpUser = $this->sendRequestMelp('ad031008', ['email' => $reqUser->email]);
        }

        // melpMultiInst үед MELP-ийн login username-ийг ИМЭЙЛЭЭР бүртгэнэ — нэг хүн (email)
        // = нэг MELP хэрэглэгч болгож, олон inst дээрх өөр өөр MeCore username-аас үл хамаарч
        // тогтвортой нэр болгоно. (me-username header нь username{inst} param-аар тусдаа явна.)
        $lpUsername = !empty($melpMultiInst) ? $reqUser->email : $reqUser->username;

        if (!empty($existingLpUser) && !empty($existingLpUser['id'])) {
            $data = $existingLpUser; // ['id' => , 'username' => , 'email' => ]
        } else {
            $data = $this->sendRequestMelp('ad010503', [
                'email' => $reqUser->email,
                'phone' => $reqUser->phone,
                'username' => $lpUsername,
                'password' => Hash::make($reqUser->password),
                'statusid' => 1,
                'passneverexpire' => null,
                'passmustchange' => null,
                'iprest' => null,
                'startdate' => $request->startdate,
                'enddate' => $request->enddate,
                'firstname' => $reqUser->name,
                'lastname' => $reqUser->lname,
                'civilid' => $reqUser->regno,
                'regno' => $reqUser->regno,
                'branchid' => $Lpbranchid,
                'positionid' => $request->posno,
                'isadmin' => 0,
            ]);
        }

        $usernameParamName = 'username';
        if (!empty($melpMultiInst)) {
            $usernameParamName = 'username' . $user->instid;
        }

        // username{inst} param нь ТУХАЙН inst-ийн MeCore username байх ёстой (me-username
        // header болж ашиглагдана). Reuse үед $data['username'] нь өөр inst-ийнх байж
        // болзошгүй тул $reqUser->username-ийг шууд ашиглана.
        $param = $this->sendRequestMelp('ad031003', [
            'instid' => $providerConfig['instid'],
            'userid' => $data['id'],
            'appid' => $providerConfig['appid'],
            'paramname' => $usernameParamName,
            'paramvalue' => $reqUser->username,
            'encryption' => 0
        ]);

        if (!empty($melpMultiInst)) {
            // ⚠ password{instid} (me-signature)-ийг ЭНД бичихгүй. me-signature нь
            // ТҮҮХИЙ нууц үг байх ёстой (MeCore core-token guard: Hash::check). Харин
            // $reqUser->password нь bcrypt hash тул энд бичвэл буруу me-signature болж
            // MELP урсгал эвдэрнэ. me-signature-ийг ЗӨВХӨН handlePasswordChange (нууц үг
            // тохируулах/солих, түүхий нууц үгтэй) эзэмшинэ.

            // Save or update 'insts' parameter
            $params = $this->sendRequestMelp('ad031001', ['userid' => $data['id']]);
            $instsParamId = 0;
            $instsParamVal = [];
            if (!empty($params) && isset($params['data'])) {
                foreach ($params['data'] as $p) {
                    if ($p['paramname'] == "insts") {
                        $instsParamId = $p["id"];
                        $instsParamVal = json_decode($p["paramvalue"], true) ?: [];
                        break;
                    }
                }
            }

            $exists = false;
            foreach ($instsParamVal as $item) {
                if (isset($item['instid']) && $item['instid'] == $user->instid) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $instsParamVal[] = [
                    "instid" => $user->instid,
                    "userid" => $reqUser->id
                ];
            }

            $instsJsonVal = json_encode($instsParamVal);

            if ($instsParamId) {
                $this->sendRequestMelp('ad031004', [
                    'appid' => $providerConfig['appid'],
                    'cmd' => "edit",
                    'id' => $instsParamId,
                    'encryption' => false,
                    'instid' => $providerConfig['instid'],
                    'paramname' => 'insts',
                    'paramvalue' => $instsJsonVal,
                    'sorting' => "1",
                    'userid' => $data['id'],
                ]);
            } else {
                $this->sendRequestMelp('ad031003', [
                    'instid' => $providerConfig['instid'],
                    'userid' => $data['id'],
                    'appid' => $providerConfig['appid'],
                    'paramname' => 'insts',
                    'paramvalue' => $instsJsonVal,
                    'encryption' => 0
                ]);
            }
        }

        $paramUserId = $this->sendRequestMelp('ad031003', [
            'instid' => $providerConfig['instid'],
            'userid' => $data['id'],
            'appid' => $providerConfig['appid'],
            'paramname' => 'userid',
            'paramvalue' => $reqUser['id'],
            'encryption' => 0
        ]);

        $rolesdata = [];
        foreach ($validated['roleno'] as $role) {
            array_push($rolesdata, [
                'roleid' => $role['id'],
                'startdate' => $validated['startdate'],
                'enddate' => $validated['enddate']
            ]);
        }

        // Эрх тохируулах
        $final = $this->sendRequestMelp('ad010507', [
            'userid' => $data['id'],
            'roles' => $rolesdata
        ]);
        return $final;
    }


    /**
     * Show the specified resource.
     * AC ad020100
     * @param int $id
     * @return Response
     */
    public function ad020102(Request $request)
    {
        if ($request['type'] != null) {
            if ($request['type'] == "Position") {
                $data = $this->sendRequestMelp('ad010601', []);
            } else if ($request['type'] == "Role") {
                $data = $this->sendRequestMelp('ad010301', []);
            }
            return $data;
        }



        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $reqUser = GPInstUser::where('instid', $user->instid)->where('id', $validated['id'])->where('statusid', 1)->first();
        if (empty($reqUser)) {
            $this->error('RC000010', [
                "id" => $validated['id']
            ]);
        }

        $melpMultiInst = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'melpMultiInst')->where('itemvalue', 1)->first();
        $lpParamName = !empty($melpMultiInst) ? 'username' . $user->instid : 'username';

        $data = $this->sendRequestMelp('ad031006', [
            'username' => $reqUser->username,
            'paramname' => $lpParamName,
        ]);

        if (!empty($data)) {
            $data2 = $this->sendRequestMelp('gp040002', [
                'id' => $data[0]['userid'],
            ]);
            $data2['posno'] = $data2['positionid'];

            $roledata = $this->sendRequestMelp('ad010506', ['userid' => $data[0]['userid']]);
            $data2['roleno'] = $roledata['data'];
            $data2['id'] = $validated['id'];
            $data2['statusname'] = __('messages.' . StatusCodeEnum::toString($data2['statusid']));
            return $data2;
        } else {
            return $data;
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function ad020302(AdMeLpUserRequest $request)
    {
        $user = auth()->user();
        $providerConf = VwGPProviderConf::where('code', 'MELP')->where('instid', $user->instid)->where('statusid', 1)->first();
        if (empty($providerConf)) {
            $this->error('RC000202', [
                "type" => "MELP"
            ]);
        }
        $providerConfig = json_decode($providerConf->config, true);


        $validated = $request->validated();
        $user = auth()->user();
        $reqUser = GPInstUser::where('instid', $user->instid)
            ->where('id', $validated['id'])
            ->where('statusid', 1)->first();
        if (empty($reqUser)) {
            $this->error('RC000010', [
                "id" => $validated['id']
            ]);
        }

        $validated['roleno'] = $this->parseRoleno($validated['roleno'] ?? []);

        if (empty($validated['roleno'])) {
            $this->error("Эрхийн бүлэг холболт хийнэ үү");
        }

        $branchlist = $this->sendRequestMelp('ad010401', [])['data']; // Lp-s irsen response dotor dahiad data field bdg
        $corebranch = $reqUser->brchno;
        $Lpbranchno = $providerConfig['branchlist'][$corebranch];
        $Lpbranchid = 0;

        foreach ($branchlist as $item) {
            if ($item['brchno'] == $Lpbranchno) {
                $Lpbranchid = $item['id'];
            }
        }

        // melpMultiInst үед MELP login username нь имэйл (ad020202-той тууштай).
        $isMultiInstUpd = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'melpMultiInst')->where('itemvalue', 1)->first();
        $lpUsernameUpd = !empty($isMultiInstUpd) ? $reqUser->email : $reqUser->username;

        $data = $this->sendRequestMelp('ad010504', [
            'email' => $reqUser->email,
            'phone' => $reqUser->phone,
            'username' => $lpUsernameUpd,
            'password' => $reqUser->password,
            'statusid' => 1,
            'id' => $validated['userid'],
            'passneverexpire' => null,
            'passmustchange' => null,
            'iprest' => null,
            'startdate' => $request->startdate,
            'enddate' => $request->enddate,
            'firstname' => $reqUser->name,
            'lastname' => $reqUser->lname,
            'civilid' => $reqUser->regno,
            'regno' => $reqUser->regno,
            'branchid' => $Lpbranchid,
            'positionid' => $request->posno,
            'isadmin' => 0,
        ]);

        // Roles

        $rolesdata = [];

        $data2 = $this->sendRequestMelp('ad010507', [
            'userid' => $validated['userid'],
            'roles' => $rolesdata
        ]);
        foreach ($validated['roleno'] as $role) {
            array_push($rolesdata, [
                'roleid' => $role['id'],
                'startdate' => $request->startdate,
                'enddate' => $request->enddate
            ]);
        }

        $data2 = $this->sendRequestMelp('ad010507', [
            'userid' => $validated['userid'],
            'roles' => $rolesdata
        ]);


        $melpMultiInst = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'melpMultiInst')->where('itemvalue', 1)->first();

        $usernameParamName = 'username';
        $passwordParamName = 'password';
        if (!empty($melpMultiInst)) {
            $usernameParamName = 'username' . $user->instid;
            $passwordParamName = 'password' . $user->instid;
        }

        // parameter id oloh
        $params = $this->sendRequestMelp('ad031001', ['userid' => $validated['userid']]);
        $paramid = 0;
        $passwordParamId = 0;
        $instsParamId = 0;
        $instsParamVal = [];
        if (!empty($params) && isset($params['data'])) {
            foreach ($params['data'] as $p) {
                if ($p['paramname'] == $usernameParamName) {
                    $paramid = $p["id"];
                }
                if ($p['paramname'] == $passwordParamName) {
                    $passwordParamId = $p["id"];
                }
                if ($p['paramname'] == "insts") {
                    $instsParamId = $p["id"];
                    $instsParamVal = json_decode($p["paramvalue"], true) ?: [];
                }
            }
        }

        $data3 = $this->sendRequestMelp('ad031004', [
            'appid' => $providerConfig['appid'],
            'cmd' => "edit",
            'id' => $paramid,
            'encryption' => false,
            'instid' => $providerConfig['instid'],
            'paramname' => $usernameParamName,
            'paramvalue' => $reqUser->username,
            'sorting' => "1",
            'userid' => $validated['userid'],
        ]);

        if (!empty($melpMultiInst)) {
            // ⚠ password{instid} (me-signature)-ийг ЭНД бичих/дарж бичихгүй. me-signature нь
            // ТҮҮХИЙ нууц үг байх ёстой (MeCore core-token guard: Hash::check), харин
            // $reqUser->password нь bcrypt hash. Энд дарж бичвэл handlePasswordChange-ийн
            // тавьсан зөв (raw) me-signature-ийг гажуудуулж MELP урсгал эвдэрнэ. me-signature-ийг
            // ЗӨВХӨН handlePasswordChange (нууц үг тохируулах/солих) эзэмшинэ.

            // Save or update 'insts' parameter
            $exists = false;
            foreach ($instsParamVal as $item) {
                if (isset($item['instid']) && $item['instid'] == $user->instid) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $instsParamVal[] = [
                    "instid" => $user->instid,
                    "userid" => $reqUser->id
                ];
            }

            $instsJsonVal = json_encode($instsParamVal);

            if ($instsParamId) {
                $this->sendRequestMelp('ad031004', [
                    'appid' => $providerConfig['appid'],
                    'cmd' => "edit",
                    'id' => $instsParamId,
                    'encryption' => false,
                    'instid' => $providerConfig['instid'],
                    'paramname' => 'insts',
                    'paramvalue' => $instsJsonVal,
                    'sorting' => "1",
                    'userid' => $validated['userid'],
                ]);
            } else {
                $this->sendRequestMelp('ad031003', [
                    'instid' => $providerConfig['instid'],
                    'userid' => $validated['userid'],
                    'appid' => $providerConfig['appid'],
                    'paramname' => 'insts',
                    'paramvalue' => $instsJsonVal,
                    'encryption' => 0
                ]);
            }
        }
        return $data3;
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function ad020402(Request $request)
    {
        $validated = $this->validateMe($request, [
            'userid' => 'required'
        ], [
            'userid.required' => "RC000011"
        ]);

        $data2 = $this->sendRequestMelp('ad010505', [
            'id' => $validated['userid'],
        ]);
        return $data2;
    }

    /**
     * Update the specified resource in LP param.
     * @param User $user
     * @param string $password
     */
    public function handlePasswordChange($user, $password)
    {
        $melpuse = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'USEMELP')->where('itemvalue', 1)->first();
        if (!empty($melpuse)) {
            $providerConf = VwGPProviderConf::where('code', 'MELP')->where('instid', $user->instid)->where('statusid', 1)->first();
            if (empty($providerConf)) {
                $this->error('RC000202', [
                    "type" => "MELP"
                ]);
            }
            $providerConfig = json_decode($providerConf->config, true);
            $response = $this->sendRequestMelp('ad031007', [
                "username" => $user->username,
                "password" => $password,
                "appid" => $providerConfig['appid'],
                "userid" => $user->id,
            ], $user->instid);

            // Determine username and password parameter names based on melpMultiInst
            $melpMultiInst = GPInstGp::where('instid', $user->instid)
                ->where('itemname', 'melpMultiInst')
                ->where('itemvalue', 1)
                ->first();
            $isMulti = !empty($melpMultiInst);
            $usernameParamName = $isMulti ? 'username' . $user->instid : 'username';
            $passwordParamName = $isMulti ? 'password' . $user->instid : 'password';

            $lpusers = $this->sendRequestMelp('ad031006', [
                'username' => $user->username,
                'paramname' => $usernameParamName,
            ], $user->instid);
            if (!empty($lpusers)) {
                $lpUserId = $lpusers[0]['userid'];
                $params = $this->sendRequestMelp('ad031001', ['userid' => $lpUserId], $user->instid);
                $passwordParamId = 0;
                if (!empty($params) && isset($params['data'])) {
                    foreach ($params['data'] as $p) {
                        if ($p['paramname'] == $passwordParamName) {
                            $passwordParamId = $p["id"];
                            break;
                        }
                    }
                }
                if ($passwordParamId) {
                    $this->sendRequestMelp('ad031004', [
                        'appid' => $providerConfig['appid'],
                        'cmd' => "edit",
                        'id' => $passwordParamId,
                        'encryption' => false,
                        'instid' => $providerConfig['instid'],
                        'paramname' => $passwordParamName,
                        'paramvalue' => $password,
                        'sorting' => "1",
                        'userid' => $lpUserId,
                    ], $user->instid);
                } else {
                    $this->sendRequestMelp('ad031003', [
                        'instid' => $providerConfig['instid'],
                        'userid' => $lpUserId,
                        'appid' => $providerConfig['appid'],
                        'paramname' => $passwordParamName,
                        'paramvalue' => $password,
                        'encryption' => 0
                    ], $user->instid);
                }
            }
        }
    }


    /**
     * Update the specified resource in LP param.
     * @param User $user
     * @param string $password
     */
    public function handleBranchChange($user)
    {
        $melpuse = GPInstGp::where('instid', $user->instid)
            ->where('itemname', 'USEMELP')->where('itemvalue', 1)->first();

        if (!empty($melpuse)) {
            $providerConf = VwGPProviderConf::where('code', 'MELP')->where('instid', $user->instid)->where('statusid', 1)->first();
            if (empty($providerConf)) {
                $this->error('RC000202', [
                    "type" => "MELP"
                ]);
            }
            $providerConfig = json_decode($providerConf->config, true);
            $branchlist = $this->sendRequestMelp('ad010401', [])['data']; // Lp-s irsen response dotor dahiad data field bdg
            $corebranch = $user->brchno;
            $Lpbranchno = $providerConfig['branchlist'][$corebranch];
            $Lpbranchid = 0;

            foreach ($branchlist as $item) {
                if ($item['brchno'] == $Lpbranchno) {
                    $Lpbranchid = $item['id'];
                }
            }

            $melpMultiInst = GPInstGp::where('instid', $user->instid)
                ->where('itemname', 'melpMultiInst')->where('itemvalue', 1)->first();
            $lpParamName = !empty($melpMultiInst) ? 'username' . $user->instid : 'username';
            $lpusers = $this->sendRequestMelp('ad031006', [
                'username' => $user->username,
                'paramname' => $lpParamName,
            ]);


            if ($lpusers) {
                return $this->sendRequestMelp('ad010509', [
                    'id' => $lpusers[0]['userid'],
                    'branchid' => $Lpbranchid,
                ]);
            }
        }
    }

    private function parseRoleno($roleno)
    {
        if (empty($roleno)) {
            return [];
        }

        $roles = [];
        $rolenoRaw = $roleno;

        if (is_string($rolenoRaw)) {
            $decoded = json_decode($rolenoRaw, true);
            if (is_array($decoded)) {
                $rolenoRaw = $decoded;
            } else {
                $rolenoRaw = [$rolenoRaw];
            }
        }

        if (is_array($rolenoRaw)) {
            foreach ($rolenoRaw as $item) {
                if (is_string($item)) {
                    $decodedItem = json_decode($item, true);
                    if (is_array($decodedItem)) {
                        if (array_keys($decodedItem) === range(0, count($decodedItem) - 1)) {
                            foreach ($decodedItem as $subItem) {
                                if (isset($subItem['id'])) {
                                    $roles[] = $subItem;
                                } elseif (is_numeric($subItem)) {
                                    $roles[] = ['id' => (int)$subItem];
                                }
                            }
                        } else {
                            if (isset($decodedItem['id'])) {
                                $roles[] = $decodedItem;
                            }
                        }
                    } else if (is_numeric($item)) {
                        $roles[] = ['id' => (int)$item];
                    }
                } elseif (is_array($item)) {
                    if (isset($item['id'])) {
                        $roles[] = $item;
                    }
                } elseif (is_numeric($item)) {
                    $roles[] = ['id' => $item];
                }
            }
        }

        return $roles;
    }
}
