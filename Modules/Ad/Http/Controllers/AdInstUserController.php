<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\Views\VwGPInstUser;
use Modules\Gp\Http\Requests\GPInstUserRequest;
use Illuminate\Support\Str;
use Modules\Ca\Entities\CaCashBal;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstUserIp;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Http\Services\GoogleAuthenticator\GoogleAuthenticator;
use Modules\Gp\Jobs\SendMailJob;

class AdInstUserController extends Controller
{
    /**
     * Display a listing of the resource.
     * AC ad020000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwGPInstUser::where('instid', auth()->user()->instid)
                ->where('isadmin', 0)
                ->where('statusid', '<>', -1),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GPInstUserRequest $request)
    {
        $validated = $request->validated();
        // Нэвтрэх нэр давхардаж байгаа эсэхийг шалгах
        $validated['username'] = trim(Str::lower($validated['username']));
        $GPuser = GPInstUser::where('username', $validated['username'])
            ->where('statusid', '<>', -1)->first();
        if ($GPuser) {
            $this->error('VC000016');
        }
        $instid = auth()->user()->instid;
        if (auth()->user()->isadmin == 1) {
            $instid = $validated['instid'];
        }
        $checkBrchno = GPInstBrch::where('instid', $instid)
            ->where('brchno', $validated['brchno'])
            ->where('statusid', 1)
            ->first();
        if (empty($checkBrchno)) {
            $this->error('RC000019', ['brchno' => $validated['brchno']]);
        }
        $validated['statusid'] = 1;
        $validated['isadmin'] = 0;
        $validated['tokenlimit'] = 1;
        $validated['instid'] = $instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        $random_password = '#@1' . Str::random(8);

        $validated['password'] = Hash::make(CoreService::hmac($random_password));
        $token = generateRandomString(50);
        $validated['passtoken'] = $token;
        $validated['passtokendate'] = getNow();
        $validated['passtokenstatus'] = 1;
        $googleAuth = new GoogleAuthenticator();
        $validated['google_auth_key'] = $googleAuth->generateSecret();
        GPInstUser::create($validated);

        $data = array();
        $data['token_life_time'] = "15";
        $data['token'] = $token;
        $data['url'] = config('app.backoffice_url');
        $data['username'] = $validated['username'];
        $email = [
            "to" => $validated['email'],
            "subject" => "Бүртгэл амжилттай хийгдлээ.",
            "data" => $data,
            "template" => "GP::emails.new-register"
        ];
        dispatch(new SendMailJob($email));
        return $validated['email'] . ' цахим хаягт нууц үг тохируулах мэдээлэл илгээсэн.';
    }

    /**
     * Show the specified resource.
     * AC ad020100
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $GPinst = GPInstUser::where('id', $validated['id'])
            ->where('instid', $user->instid)
            ->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            $melpuse = GPInstGp::where('instid', $user->instid)
                ->where('itemname', 'USEMELP')->where('itemvalue', 1)->first();
            if (!empty($melpuse)) {
                $GPinst->use_melp = true;
            }
            return $GPinst;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(GPInstUserRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['username'] = trim(Str::lower($validated['username']));
        // Нэвтрэх нэр давхардаж байгаа эсэхийг шалгах
        $GPuser = GPInstUser::where('id', '!=', $validated['id'])
            ->where('username', $validated['username'])
            ->where('statusid', '<>', -1)->first();
        if ($GPuser) {
            $this->error('VC000016');
        }
        $validated['instid'] = auth()->user()->instid;
        $validated['updated_by'] = auth()->user()->id;
        $record = GPInstUser::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)
            ->first();
        if ($record) {
            $record->update($validated);
            try {
                // MeLP ruu salbar zasvarlah
                $admelpcontroller = new AdMeLpUserController();

                $admelpcontroller->handleBranchChange($record);
            } catch (Exception $e) {
                Log::error($e);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $cashbal = CaCashBal::where('userid', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('bal', '!=', 0)
            ->where('statusid', 1)->first();

        if ($cashbal) {
            $this->error("RC000227", [
                'id' => $validated['id'],
                'acntno' => $cashbal->acntcode
            ]);
        }
        GPInstUser::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }

    /**
     * Unblock user.
     * @param int $id
     * @return Response
     */
    public function ad020500(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $sql = GPInstUser::where('id', $validated['id']);

        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }
        $user = $sql->first();
        if ($user) {
            $user->update([
                'passwrong' => 0,
                'updated_by' => auth()->user()->id
            ]);
        }
    }

    /**
     * Get user ip list
     * @param int $id
     * @return Response
     */
    public function ad022000(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        return $this->getGridData(
            $request,
            GPInstUserIp::where('statusid', '>', 0)
                ->where('userid',  $validated['id']),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * User ip details
     * @param int $id
     * @return Response
     */
    public function ad022100(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $GPinstuserip = GPInstUserIp::where('id', $validated['id'])
            ->where('instid', $user->instid)
            ->where('statusid', '<>', -1)->first();
        if ($GPinstuserip) {
            return $GPinstuserip;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Register user ip
     * @param Request $request
     * @return Response
     */
    public function ad022200(Request $request)
    {
        $validated = $this->validateMe($request, [
            'userid' => 'required',
            'ip_address' => 'required'
        ], [
            'userid.required' => "RC000011",
            'ip_address.required' => ResponseCodeEnum::required,
        ]);

        $user = GPInstUser::where('id', $validated['userid'])
            ->where('statusid', '>', 0)
            ->first();

        if (!$user) {
            $this->error("RC000010", $validated);
        }

        $existingIp = GPInstUserIp::where('userid', $validated['userid'])
            ->where('ip_address', $validated['ip_address'])
            ->where('statusid', '>', 0)
            ->first();

        if ($existingIp) {
            $this->error('RC000028');
        }

        $ipData = [
            'userid' => $validated['userid'],
            'ip_address' => $validated['ip_address'],
            'instid' => $user->instid,
            'statusid' => 1,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
            'created_at' => getNow(),
            'updated_at' => getNow()
        ];

        GPInstUserIp::create($ipData);
    }

    /**
     * Edit user ip registration
     * @param Request $request
     * @return Response
     */
    public function ad022300(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
            'ip_address' => 'required'
        ], [
            'id.required' => "RC000011",
            'ip_address.required' => ResponseCodeEnum::required,
        ]);

        $ipRecord = GPInstUserIp::where('id', $validated['id'])
            ->where('statusid', '>', 0)
            ->first();

        if (!$ipRecord) {
            $this->error("RC000010", $validated);
        }

        // Check if new IP already exists for this user (excluding current record)
        $existingIp = GPInstUserIp::where('userid', $ipRecord->userid)
            ->where('ip_address', $validated['ip_address'])
            ->where('id', '!=', $validated['id'])
            ->where('statusid', '>', 0)
            ->first();

        if ($existingIp) {
            $this->error('RC000028');
        }

        // Update IP record
        $ipRecord->update([
            'ip_address' => $validated['ip_address'],
            'updated_by' => auth()->user()->id,
            'updated_at' => getNow()
        ]);
    }

    /**
     * Delete user ip registration
     * @param Request $request
     * @return Response
     */
    public function ad022400(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $ipRecord = GPInstUserIp::where('id', $validated['id'])
            ->where('statusid', '>', 0)
            ->first();

        if (!$ipRecord) {
            $this->error("RC000010", $validated);
        }

        $count = GPInstUserIp::where('ip_address', $ipRecord->ip_address)
            ->where('userid', $ipRecord->userid)
            ->count();

        $ipRecord->update([
            'statusid' =>  "-" . $count,
            'updated_by' => auth()->user()->id,
            'updated_at' => getNow()
        ]);
    }
}
