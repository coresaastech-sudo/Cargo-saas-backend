<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Re\Entities\ReInstReportTempParam;
use Modules\Re\Entities\ReInstReportTempParamIn;
use Modules\Re\Entities\ReInstReportTempParamInRel;
use Modules\Re\Entities\ReInstTableField;
use Modules\Re\Entities\Views\VwReInstReportTempParam;
use Modules\Re\Http\Requests\ReInstReportTempParamRequest;

class ReInstReportTempParamController extends Controller
{
    /**
     * re010002
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getGridData($request, ReInstReportTempParam::where('statusid', 1)
            ->where('instid', 1), [['field' => 'created_at', 'dir' => 'ASC']]);
        return $data;
    }
    /**
     * re010302
     * Show Report Template Parameter
     * @param ReInstReportTempParamRequest $request
     * @return Response
     */
    public function update(ReInstReportTempParamRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error('RC000011');
        }

        $user = auth()->user();

        $validated['updated_by'] = $user->id;

        $arrayofparams = null;
        if (!empty($validate['subparams'])) {
            $arrayofparams = $validate['subparams'];
        }
        unset($validate['subparams']);

        $arrayofinputs = null;
        if (!empty($validate['inputs'])) {
            $arrayofinputs = $validate['inputs'];
        }
        unset($validate['inputs']);

        ReInstReportTempParam::where('instid', 1)->where("statusid", 1)->where("id", $validate['id'])->update($validate);

        ReInstReportTempParam::where("instid", 1)->where("parentid", $validate['id'])->update(['statusid' => -1, 'updated_by' => $user->id]);

        ReInstReportTempParamInRel::where("instid", 1)->where("paramid", $validate['id'])->update(['statusid' => -1, 'updated_by' => $user->id]);

        Log::debug($arrayofinputs);

        if (!empty($arrayofinputs)) {
            foreach ($arrayofinputs as &$item) {
                if (!empty($item['id'])) {
                    ReInstReportTempParamInRel::where("inputid", $item['id'])->where("paramid", $validate['id'])->where("templateid", $item['templateid'])->where("instid", 1)->update(['statusid' => 1, 'updated_by' => $user->id]);
                } else {
                    unset($item['id']);
                    $item['paramid'] = $validate['id'];
                    $item['templateid'] = $validate['templateid'];
                    $item['statusid'] = 1;
                    $item['instid'] = 1;
                    $item['created_by'] = $user->id;
                    $item['updated_by'] = $user->id;
                    ReInstReportTempParamInRel::create($item);
                }
            }
        }

        if (!empty($arrayofparams)) {
            $foundInst = false;
            foreach ($arrayofparams as &$item) {
                if ($item['paramname'] === 'instid' || $item['paramname'] === 'institution') $foundInst = true;
                if (!empty($item['id'])) {
                    $id = $item['id'];
                    unset($item['id']);
                    $item['statusid'] = 1;
                    $item['updated_by'] = $user->id;
                    ReInstReportTempParam::where("id", $id)->update($item);
                } else {
                    unset($item['id']);
                    $item['parentid'] = $validate['id'];
                    $item['templateid'] = $validate['templateid'];
                    $item['statusid'] = 1;
                    $item['instid'] = 1;
                    $item['created_by'] = $user->id;
                    $item['updated_by'] = $user->id;
                    if (empty($item['evaluate'])) $item['evaluate'] = false;
                    if (empty($item['hasinput'])) $item['hasinput'] = false;
                    if (empty($item['hascondition'])) $item['hascondition'] = false;
                    if (empty($item['isnull'])) $item['isnull'] = false;
                    ReInstReportTempParam::create($item);
                }
            }
            if (!$foundInst) {
                $instParam = ReInstReportTempParam::where("instid", 1)->where("templateid", $validate['templateid'])->where("parentid", $validate['id'])->where("paramname", "instid")->first();
                if (empty($instParam)) {
                    ReInstReportTempParam::where("instid", 1)->where("templateid", $validate['templateid'])->where("parentid", $validate['id'])->where("paramname", "instid")->update(['statusid' => 1, 'updated_by' => $user->id]);
                } else {
                    $item['parentid'] = $validate['id'];
                    $item['templateid'] = $validate['templateid'];
                    $item['statusid'] = 1;
                    $item['instid'] = 1;
                    $item['created_by'] = $user->id;
                    $item['updated_by'] = $user->id;
                    $item['paramname'] = 'instid';
                    $item['isnull'] = false;
                    $item['type'] = 2;
                    $item['header'] = 'instid';
                    $item['evaluate'] = false;
                    $item['hasinput'] = false;
                    $item['hascondition'] = false;
                    ReInstReportTempParam::create($item);
                }
            }
        }
    }
    /**
     * re010202
     * Create Report Template Parameter
     * @param ReInstReportTempParamRequest $request
     * @return Response
     */
    public function store(ReInstReportTempParamRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();
        $validate['statusid'] = 1;
        $validate['instid'] = 1;
        $validate['created_by'] = $user->id;
        $validate['updated_by'] = $user->id;
        if (empty($validate['evaluate'])) $validate['evaluate'] = false;

        $arrayofparams = null;
        if (!empty($validate['subparams'])) {
            $arrayofparams = $validate['subparams'];
        }
        unset($validate['subparams']);

        $arrayofinputs = null;
        if (!empty($validate['inputs'])) {
            $arrayofinputs = $validate['inputs'];
        }
        unset($validate['inputs']);

        $param = ReInstReportTempParam::create($validate);

        if (!empty($arrayofinputs)) {
            foreach ($arrayofinputs as &$item) {
                $item['paramid'] = $param->id;
                $item['statusid'] = 1;
                $item['instid'] = 1;
                $item['created_by'] = $user->id;
                $item['updated_by'] = $user->id;
                ReInstReportTempParamInRel::create($item);
            }
        }

        if (!empty($arrayofparams)) {
            $foundInst = false;
            foreach ($arrayofparams as &$item) {
                unset($item['id']);
                if ($item['paramname'] === 'instid' || $item['paramname'] === 'institution') $foundInst = true;
                $item['parentid'] = $param->id;
                $item['templateid'] = $validate['templateid'];
                $item['statusid'] = 1;
                $item['instid'] = 1;
                $item['created_by'] = $user->id;
                $item['updated_by'] = $user->id;
                if (empty($item['evaluate'])) $item['evaluate'] = false;
                if (empty($item['hasinput'])) $item['hasinput'] = false;
                if (empty($item['hascondition'])) $item['hascondition'] = false;
                if (empty($item['isnull'])) $item['isnull'] = false;
                ReInstReportTempParam::create($item);
            }
            if (!$foundInst) {
                $field = false;
                if (!empty($validate['tableid'])) {
                    $field = ReInstTableField::where("instid", 1)->where("tableid", $validate['tableid'])->where("fieldname", "instid")->orWhere("fieldname", "institution")->first();
                }
                $item['parentid'] = $param->id;
                $item['templateid'] = $validate['templateid'];
                $item['statusid'] = 1;
                $item['instid'] = 1;
                $item['created_by'] = $user->id;
                $item['updated_by'] = $user->id;
                $item['paramname'] = 'instid';
                $item['isnull'] = false;
                $item['type'] = 2;
                $item['header'] = 'instid';
                $item['evaluate'] = false;
                $item['hasinput'] = false;
                $item['hascondition'] = false;
                if ($field) {
                    $item['fieldid'] = $field->id;
                }
                ReInstReportTempParam::create($item);
            }
        }

        return $param;
    }
    /**
     * re010402
     * Show Report Temp Param
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

        $user = auth()->user();

        $tempParam = ReInstReportTempParam::where("instid", 1)
            ->where("id", $validate['id'])->where('statusid', 1)->first();

        ReInstReportTempParam::where("instid", 1)->where("parentid", $validate['id'])->where("statusid", 1)->update([
            "statusid" => -1,
            "updated_by" => $user->id
        ]);

        ReInstReportTempParamInRel::where("instid", 1)->where("paramid", $validate['id'])->where("statusid", 1)->update([
            "statusid" => -1,
            "updated_by" => $user->id
        ]);

        $tempParam->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }
    /**
     * re010102
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

        $user = auth()->user();

        $tempParam = VwReInstReportTempParam::where("instid", 1)
            ->where("id", $validate["id"])->where("statusid", 1)->first();

        $tempSubParam = ReInstReportTempParam::where("instid", 1)
            ->where("parentid", $validate["id"])->where('statusid', 1)->get();

        $tempSubInput = ReInstReportTempParamInRel::where("instid", 1)
            ->where("paramid", $validate['id'])->where("statusid", 1)->get();

        if ($tempParam) {
            $tempParam['subparams'] = $tempSubParam;
            if ($tempSubInput) {
                if (count($tempSubInput)) {
                    $array = [];
                    foreach ($tempSubInput as $input) {
                        $inputData = ReInstReportTempParamIn::where("instid", 1)
                            ->where("id", $input->inputid)->where("statusid", 1)->first();
                        array_push($array, $inputData);
                    }
                    $tempParam['inputsdata'] = $array;
                }
            }
            return $tempParam;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
