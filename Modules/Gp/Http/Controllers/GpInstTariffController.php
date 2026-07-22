<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstTariff;
use Modules\Gp\Entities\Views\VwGpInstTariff;
use Modules\Gp\Http\Requests\GpInstTariffRequest;

class GpInstTariffController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gp010003(Request $request)
    {
        $validate = $this->validateMe($request, [
            'instid' => 'required'
        ], [
            'instid.required' => "RC000011"
        ]);
        return $this->getGridData($request, VwGpInstTariff::where('statusid', 1)
            ->where('instid', $validate['instid']), [['field' => 'depend', 'dir' => 'ASC'], ['field' => 'interval', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp010203(GpInstTariffRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        return GpInstTariff::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function gp010103(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpInstTariff::where('id', $validate['id'])
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
    public function gp010303(GpInstTariffRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $validated['updated_by'] = $user->id;
        $inst = GpInstTariff::where('statusid', 1)->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gp010403(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $dtl = GpInstTariff::where('id', $validate['id'])->where('statusid', 1)->first();
        $count = GpInstTariff::where('instid', $dtl->instid)
            ->where('depend', $dtl->depend)
            ->where('interval', $dtl->interval)
            ->where('statusid', '<>', 1)->count();

        $dtl->update([
            'statusid' =>  $count ? ($count + 1) * -1 : -1,
            'updated_by' => $user->id,
        ]);
    }
}
