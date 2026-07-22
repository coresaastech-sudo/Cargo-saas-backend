<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Re\Entities\ReInstReportTempDim;
use Modules\Re\Entities\Views\VwReInstReportTempDim;
use Modules\Re\Http\Requests\ReInstReportTempDimRequest;

class ReInstReportTempDimController extends Controller
{
    /**
     * re010005
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, ReInstReportTempDim::
            where('statusid', 1)
            ->where('instid', 1), [['field' => 'created_at', 'dir' => 'ASC']]);
    }

    /**
     * re010305
     * Update Report Template Dimension
     * @param ReInstReportTempDimRequest $request
     * @return Response
     */
    public function update(ReInstReportTempDimRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error('RC000011');
        }

        if(array_key_exists('name', $validate) && !empty($validate['name'])){
            $validate['name'] = mb_strtoupper($validate['name']);
        }
        if(array_key_exists('name2', $validate) && !empty($validate['name2'])){
            $validate['name2'] = mb_strtoupper($validate['name2']);
        }

        $validated['updated_by'] = auth()->user()->id;
        ReInstReportTempDim::where('instid', 1)->where("statusid", 1)->where('id', $validate['id'])->update($validate);
    }

    /**
     * re010205
     * Store Report Template Dimension
     * @param ReInstReportTempDimRequest $request
     * @return Response
     */
    public function store(ReInstReportTempDimRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();
        $validate['statusid'] = 1;
        $validate['instid'] = 1;
        $validate['created_by'] = $user->id;
        $validate['updated_by'] = $user->id;
        $validate['name'] = mb_strtoupper($validate['name']);
        $validate['name2'] = mb_strtoupper($validate['name2']);
        return ReInstReportTempDim::create($validate);
    }

    /**
     * re010405
     * Update Report Template Dimension
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $tempParam = ReInstReportTempDim::where("instid", 1)
        ->where("id", $validate['id'])->where('statusid', 1)->first();
        if(!empty($tempParam)){
            $tempParam->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
        }
    }

    /**
     * re010105
     * Show Report Temp Param
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $tempParam = VwReInstReportTempDim::where("instid", 1)
        ->where("id", $validate["id"])->where("statusid", 1)->first();

        if($tempParam){
            return $tempParam;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
