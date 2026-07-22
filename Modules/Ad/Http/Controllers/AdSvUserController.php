<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Ad\Entities\AdSvUser;
use Modules\Ad\Entities\Views\VwAdSvUser;
use Modules\Ad\Http\Requests\AdSvUserRequest;
use Modules\Gp\Entities\GPInstUser;

class AdSvUserController extends Controller
{
    /**
     * Display a listing of the resource.
     * index
     * @return Response
     */
    public function ad020004(Request $request)
    {
        $validated = $this->validateMe($request, [
            'userid' => 'nullable',
            'svuserid' => 'nullable',
        ]);

        if (empty($request->userid) && empty($request->svuserid)) {
            throw ValidationException::withMessages([
                'userid' => ['RC000011'],
                'svuserid' => ['RC000011'],
            ]);
        }
        $field = '';
        $value = '';
        if (!empty($request->userid)) {
            $field = 'userid';
            $value = $request->userid;
        } else if (!empty($request->svuserid)) {
            $field = 'svuserid';
            $value = $request->svuserid;
        }
        return $this->getGridData($request, VwAdSvUser::where('statusid', 1)->where($field, $value)
            ->where('instid', auth()->user()->instid), [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * store
     * @param Request $request
     * @return Response
     */
    public function ad020204(AdSvUserRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if ($validated['userid'] == $validated['svuserid']) {
            $this->error('RC000262');
        }
        $svuser = GPInstUser::where('instid', $user->instid)
            ->where('statusid', 1)
            ->where('id', $validated['svuserid'])
            ->first();
        if (empty($svuser)) {
            $this->error('RC000010', ['id' => $validated['svuserid']]);
        }
        $cl_user = GPInstUser::where('instid', $user->instid)
            ->where('statusid', 1)
            ->where('id', $validated['userid'])
            ->first();
        if (empty($cl_user)) {
            $this->error('RC000010', ['id' => $validated['userid']]);
        }
        $validated['statusid'] = 1;
        $validated['instid'] = $user->instid;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        AdSvUser::create($validated);
    }

    /**
     * Show the specified resource.
     * show
     * @param int $id
     * @return Response
     */
    public function ad020104(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $GPinst = VwAdSvUser::where('id', $validate['id'])->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * update
     * @param Request $request
     * @return Response
     */
    public function ad020304(AdSvUserRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        if ($validated['userid'] == $validated['svuserid']) {
            $this->error('RC000262');
        }
        $user = auth()->user();
        $validated['updated_by'] = $user->id;
        $inst = AdSvUser::where('instid', $user->instid)
            ->where('statusid', 1)
            ->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * destroy
     * @return Response
     */
    public function ad020404(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $user = auth()->user();
        $dtl = AdSvUser::where('instid', $user->instid)->where('id', $validated['id'])->where('statusid', 1)->first();

        $count = AdSvUser::where('instid', $user->instid)
            ->where('userid', $dtl->userid)
            ->where('svuserid', $dtl->svuserid)
            ->where('svtype', $dtl->svtype)
            ->where('statusid', '<>', 1)
            ->where('id', '!=', $dtl->id)
            ->count();

        // Статус шинэчлэх (1 байвал -1 болгох, эсвэл -2, -3 гэх мэт)
        $newStatus = $count ? ($count + 1) * -1 : -1;
        $dtl->update([
            'statusid' => $newStatus,
            'updated_by' => $user->id,
        ]);
    }
}
