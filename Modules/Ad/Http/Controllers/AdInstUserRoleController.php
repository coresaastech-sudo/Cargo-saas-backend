<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GPInstUserRole;
use Modules\Gp\Entities\Views\VwGPInstUserRole;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Requests\GPInstUserRoleRequest;
use Modules\Gp\Http\Services\CoreService;

class AdInstUserRoleController extends Controller
{
    /**
     * Хэрэглэгчийн эрхийн бүлэг жагсаалт
     * @AC ad021000
     * @return array
     */
    public function index(Request $request)
    {
        $validate = $this->validate($request, [
            'userid' => 'required'
        ]);
        return $this->getGridData(
            $request,
            VwGPInstUserRole::where('userid', $validate['userid'])
                ->where('instid', auth()->user()->instid)
                ->where('statusid', '<>', -1)
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validate = $this->validate($request, [
            'userid' => 'required',
            'ids' => 'required|array',
        ], [
            'ids.required' => ResponseCodeEnum::required,
            'userid.required' => ResponseCodeEnum::required
        ]);

        $user = GPInstUser::where('id', $validate['userid'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->first();
        $startdate = getNow();
        $endDate = Carbon::now()->addYears(10);
        if ($user) {
            $validate['instid'] = $user->instid;
            foreach ($validate['ids'] as $key => $value) {
                GPInstUserRole::create([
                    'instid' => $validate['instid'],
                    'userid' => $validate['userid'],
                    'roleid' => $value,
                    'startdate' => $startdate,
                    'enddate' => $endDate,
                    'statusid' => 1,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ]);
            }
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = VwGPInstUserRole::where('id', $validate['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(GPInstUserRoleRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        $validate['startdate'] = Carbon::parse($validate['startdate'])->format('Y-m-d');
        $validate['enddate'] = Carbon::parse($validate['enddate'])->format('Y-m-d');
        $role = GPInstUserRole::where('id', $validate['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->first();
        if ($role) {
            $role->update($validate);
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $record = GPInstUserRole::where('id', $validate['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)
            ->first();
        if ($record) {
            $record->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
        }
    }

    /**
     * Cache clear
     * @AC ad021900
     *
     * @return void
     */
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::user_role
        );
    }
}
