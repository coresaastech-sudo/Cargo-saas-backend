<?php

namespace Modules\Ap\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Ap\Entities\ApInstStopService;
use Illuminate\Support\Str;
use Modules\Ap\Entities\Views\VwApInstStopService;
use Modules\Ap\Http\Requests\ApInstStopServiceRequest;

class ApStopServiceController extends Controller
{
     /**
     * Display a listing of the resource.
     * @return Response
     */
    public function ap020000(Request $request)
    {

        return $this->getGridData($request, VwApInstStopService::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'created_at', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function ap020200(ApInstStopServiceRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = $user->instid;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        return ApInstStopService::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function ap020100(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = VwApInstStopService::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function ap020300(ApInstStopServiceRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['updated_by'] = $user->id;
        $inst = ApInstStopService::where('instid', $user->instid)
            ->where('statusid', 1)->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function ap020400(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $dtl = ApInstStopService::where('instid', $user->instid)
            ->where('id', $validate['id'])->where('statusid', 1)->first();
        $dtl->update([
            'statusid' =>  -1,
            'updated_by' => $user->id,
        ]);
    }
}
