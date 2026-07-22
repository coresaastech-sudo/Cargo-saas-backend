<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpUserAccessToken;
use Modules\Gp\Entities\GpUserActList;
use Modules\Gp\Entities\Views\VwGpUserActList;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstUserRepresentController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gp020001(Request $request)
    {
        $v = $this->validate($request, [
            'userid' => 'required',
        ]);

        $token = $request->bearerToken();
        $auth = GpUserAccessToken::where('token', $token)
            ->where('channel', 'BACK')->where('name', 'login')->first();
        $adminuser = GpInstUser::where('id', $auth->userid)->first();

        if (!empty($auth->abilities)) {
            $v['userid'] = $auth->userid;
        }

        if ($adminuser->instid != 1) {
            $this->error("RC000026");
        }

        return $this->getGridData($request, VwGpUserActList::where('statusid', 1)
            ->where('instid', 1)
            ->where('userid', $v['userid']), [['field' => 'act_instname', 'dir' => 'ASC']]);
    }
    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function gp020101(Request $request)
    {
        $v = $this->validate($request, [
            'id' => 'required|numeric',
        ], [
            'id.required' => "RC000011",
        ]);
        if (auth()->user()->instid !== 1) {
            $this->error("RC000026");
        }
        $detail = VwGpUserActList::where('id', $v['id'])
            ->where('statusid', 1)
            ->first();
        if (!$detail) {
            $this->error("RC000010", ['id' => $v['id']]);
        }
        return $detail;
    }
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp020201(Request $request)
    {
        $v = $this->validate($request, [
            'instid' => 'required',
            'userid' => 'required',
            'act_instid' => 'required',
            'act_userid' => 'required',
        ]);
        if (auth()->user()->instid !== 1) {
            $this->error("RC000026");
        }
        $user = auth()->user();
        $v['created_by'] = $user->id;
        $v['statusid'] = 1;

        $insterted = GpUserActList::create($v);
        return $insterted;
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp020301(Request $request)
    {
        $v = $this->validate($request, [
            'id' => 'required|numeric',
            'act_instid' => 'required|numeric',
            'act_userid' => 'required|numeric',
        ], [
            'id.required' => "RC000011",
        ]);
        try {
            DB::beginTransaction();

            if (auth()->user()->instid === 1) {
                GpUserActList::where('id', $v['id'])
                    ->update([
                        'act_instid' => $v['act_instid'],
                        'act_userid' => $v['act_userid'],
                        'updated_by' => auth()->user()->id,
                    ]);
                DB::commit();
            } else {
                $this->error("RC000026");
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gp020401(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        if (auth()->user()->instid !== 1) {
            $this->error("RC000026");
        }

        $dtl = GpUserActList::where('instid', 1)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();

        $minStatusId = GpUserActList::where('instid', 1)
            ->where('instid', $dtl['instid'])
            ->where('userid', $dtl['userid'])
            ->where('act_instid', $dtl['act_instid'])
            ->where('act_userid', $dtl['act_userid'])
            ->min('statusid');
        $newStatusId = $minStatusId ? ($minStatusId - 1) : -1;

        $dtl->update([
            'statusid' => $newStatusId,
            'updated_by' => auth()->user()->id,
        ]);
    }

    // Connect as acted user
    public function gp020202(Request $request)
    {
        $validate = $this->validate($request, [
            'userid' => 'required',
        ], [
            'userid.required' => ResponseCodeEnum::required,
        ]);

        $token = $request->bearerToken();
        $auth = GpUserAccessToken::where('token', $token)
            ->where('channel', 'BACK')->where('name', 'login')->first();

        $loginuser = GpUserActList::where('act_userid', $validate['userid'])
            ->where('userid', $auth->userid)
            ->where('statusid', 1)->first();

        $adminuser = GpInstUser::where('id', $auth->userid)->first();
        if ($adminuser->instid != 1) {
            $this->error("RC000026");
        }

        if ($loginuser) {
            if ($auth) {
                if (!empty($auth->abilities)) {
                    GpUserAccessToken::where('token', $auth->abilities)
                        ->where('channel', 'BACK')
                        ->where('name', 'act-login')->delete();
                }
                $newtoken = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));
                $auth->abilities = $newtoken;
                $auth->save();
                GpUserAccessToken::create([
                    'userid' => $validate['userid'],
                    'name' => 'act-login',
                    'token' => $newtoken,
                    'abilities' => '',
                    'last_used_at' => getNow(),
                    'created_at' => getNow(),
                    'updated_at' => null,
                    'channel' => 'BACK'
                ]);
            }
        } else {
            $this->error("RC000004");
        }
    }

    public function gp020402(Request $request)
    {
        $token = $request->bearerToken();
        $auth = GpUserAccessToken::where('token', $token)
            ->where('channel', 'BACK')->where('name', 'login')->first();
        if ($auth) {
            if (!empty($auth->abilities)) {
                GpUserAccessToken::where('token', $auth->abilities)
                    ->where('channel', 'BACK')
                    ->where('name', 'act-login')->delete();
            }
            $auth->abilities = null;
            $auth->save();
        }
    }
}
