<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpInstUserRole;
use Modules\Gp\Entities\Views\VwGpInstUserRole;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Requests\GpInstUserRoleRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstUserRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validate($request, [
            'userid' => 'required'
        ]);
        return $this->getGridData(
            $request,
            VwGpInstUserRole::where('userid', $validate['userid'])->where('statusid', '<>', -1)
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @AC gp021200
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

        $sql = GpInstUser::where('id', $validate['userid'])->where('statusid', '<>', -1);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }

        $user = $sql->first();
        if ($user) {
            $startdate = CoreService::getTxnDate($user->instid);
            $endDate = (new Carbon($startdate))->addYears(10);
            $validate['instid'] = $user->instid;
            foreach ($validate['ids'] as $key => $value) {
                GpInstUserRole::create([
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
        $GPinst = VwGpInstUserRole::where('id', $validate['id'])->where('statusid', '<>', -1)->first();
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
    public function update(GpInstUserRoleRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        $record = GpInstUserRole::where('id', $validate['id'])
            ->where('statusid', '<>', -1)
            ->first();
            if ($record) {
                $record->update($validate);
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
        $record = GpInstUserRole::where('id', $validate['id'])
            ->where('statusid', '<>', -1)
            ->update([
                    'statusid' => -1,
                    'updated_by' => auth()->user()->id]);
    }

    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::gp_user_role
        );
    }
}
