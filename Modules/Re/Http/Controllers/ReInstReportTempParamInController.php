<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPInstFormula;
use Modules\Re\Entities\ReInstReportTempParamIn;
use Modules\Re\Entities\Views\VwReInstReportTempParamIn;
use Modules\Re\Http\Requests\ReInstReportTempParamInRequest;

class ReInstReportTempParamInController extends Controller
{
    /**
     * re010003
     * Display a listing of the Report Temp Param Input.
     * @return Response
     */
    public function index(Request $request)
    {
        $filters = $request->input('filters', []);
        $formulaId = null;
        $templateId = null;

        foreach ($filters as $filter) {
            if ($filter['field'] === 'id') {
                $formulaId = $filter['value'];
            }
            if ($filter['field'] === 'templateid') {
                $templateId = $filter['value'];
            }
        }

        $query = ReInstReportTempParamIn::where('statusid', 1)
            ->where('instid', 1)->where('templateid', $templateId);

        $formula = GPInstFormula::where('id', $formulaId)->first();

        if ($formula && !empty($formula->formula)) {
            preg_match_all('/:(\w+)/', $formula->formula, $matches);
            $params = $matches[1];

            if (!empty($params)) {
                $query->whereIn('input', $params);
            }
        }
        $results = $query->get();

        return [
            'data' => $results->toArray(),
        ];
    }

    /**
     * re010303
     * Update Report Temp Param Input
     * @param ReInstReportTempParamInRequest $request
     * @return Response
     */
    public function update(ReInstReportTempParamInRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error('RC000011');
        }
        $validated['updated_by'] = auth()->user()->id;
        $update = ReInstReportTempParamIn::where('instid', 1)
            ->where("statusid", 1)->find($validate['id']);
        $update->update($validate);
    }

    /**
     * re010203
     * Store Report Temp Param Input
     * @param ReInstReportTempParamInRequest $request
     * @return Response
     */
    public function store(ReInstReportTempParamInRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();
        $validate['statusid'] = 1;
        $validate['instid'] = 1;
        $validate['created_by'] = $user->id;
        $validate['updated_by'] = $user->id;
        return ReInstReportTempParamIn::create($validate);
    }

    /**
     * re010403
     * Destroy Report Temp Param Input
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
        $tempParam = ReInstReportTempParamIn::where("instid", 1)
            ->where("id", $validate['id'])->where('statusid', 1)->first();

        $tempParam->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * re010103
     * Show Report Temp Param Input
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
        $tempParam = VwReInstReportTempParamIn::where("instid", 1)
            ->where("id", $validate["id"])->where("statusid", 1)->first();

        if ($tempParam) {
            return $tempParam;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
