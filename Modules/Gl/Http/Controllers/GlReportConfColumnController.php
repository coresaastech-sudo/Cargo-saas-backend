<?php

namespace Modules\Gl\Http\Controllers;

use App\Http\Controllers\Controller;
use Google\Service\Docs\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlReportConfColumn;
use Modules\Gl\Entities\GlReportConfColumnContTxn;
use Modules\Gl\Http\Requests\GlReportConfColumnRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlReportConfColumnController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gl013002(Request $request)
    {
        $validate = $this->validate($request, [
            'conf_detail_id' => 'required'
        ], [
            'conf_detail_id.required' => ResponseCodeEnum::required
        ]);

        return $this->getGridData(
            $request,
            GlReportConfColumn::with('conttxns')
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid)
                ->where('conf_detail_id', $validate['conf_detail_id']),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gl013202(GlReportConfColumnRequest $request)
    {
        $userid = auth()->user()->id;
        $instid = auth()->user()->instid;
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            if (isset($validated['acntnos']) && !empty($validated['acntnos'])) {
                foreach ($validated['acntnos'] as $data) {
                    $isdupl = GlReportConfColumn::where('instid', $instid)
                        ->where('statusid', 1)
                        ->where('type', $data['type'] ?? 0)
                        ->where('istranbal', $data['istranbal'] ?? null)
                        ->where('isbegbal', $data['isbegbal'] ?? 0)
                        ->where('multiply', $data['multiply'] ?? 1)
                        ->where('columnidx', $data['columnidx'])
                        ->where('acntno', $data['acntno'])
                        ->where('conf_detail_id', $validated['conf_detail_id'])
                        ->first();
                    if (empty($isdupl)) {
                        $column = GlReportConfColumn::create([
                            'conf_detail_id' => $validated['conf_detail_id'],
                            'acntno' => $data['acntno'],
                            'columnidx' => $data['columnidx'],
                            'type' => $data['type'] ?? 0,
                            'multiply' => $data['multiply'] ?? 1,
                            'isbegbal' => $data['isbegbal'] ?? 0,
                            'istranbal' => $data['istranbal'] ?? null,
                            'statusid' => 1,
                            'instid' => $instid,
                            'created_by' => $userid,
                            'updated_by' => $userid,
                        ]);
                    } else {
                        $column = $isdupl;
                    }

                    $this->saveContTxns($column, $data['conttxns'] ?? [], $instid, $userid);
                }
            } else {
                $column = GlReportConfColumn::where('instid', $instid)
                    ->where('statusid', 1)
                    // ->where('acntno', $validated['acntno'])
                    ->where('id', $validated['id'])
                    ->where('conf_detail_id', $validated['conf_detail_id'])
                    ->where('columnidx', $validated['columnidx'])
                    ->first();

                if ($column) {
                    $column->update([
                        'multiply' => $validated['multiply'],
                        'isbegbal' => $validated['isbegbal'],
                        'istranbal' => $validated['istranbal'],
                        'acntno' => $validated['acntno'],
                    ]);

                    $this->saveContTxns($column, $validated['conttxns'] ?? [], $instid, $userid);
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gl013402(Request $request)
    {
        $validated = $this->validate($request, [
            'conf_detail_id' => 'required',
            'ids' => 'required'
        ], [
            'conf_detail_id.required' => "RC000011",
            'ids.required' => "RC000011"
        ]);
        function statId($data)
        {
            $count = GlReportConfColumn::where('acntno', $data->acntno)
                ->where('conf_detail_id', $data->conf_detail_id)
                ->where('instid', $data->instid)
                ->count() + 1;

            return $count * (-1);
        }
        try {
            DB::beginTransaction();
            foreach ($validated['ids'] as $data) {
                $glReportConfColumn = GlReportConfColumn::where('conf_detail_id', $validated['conf_detail_id'])
                    ->where('id', $data)
                    ->first();

                if ($glReportConfColumn) {
                    $newStatusId = statId($glReportConfColumn);
                    $glReportConfColumn->update([
                        'statusid' => $newStatusId,
                        'updated_by' => auth()->user()->id,
                    ]);

                    GlReportConfColumnContTxn::where('conf_column_id', $glReportConfColumn->id)
                        ->where('statusid', 1)
                        ->update([
                            'statusid' => -1,
                            'updated_by' => auth()->user()->id,
                        ]);
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private function saveContTxns($column, $conttxns, $instid, $userid)
    {
        GlReportConfColumnContTxn::where('conf_column_id', $column->id)
            ->where('statusid', 1)
            ->update([
                'statusid' => -1,
                'updated_by' => $userid,
            ]);

        if (($column->istranbal ?? null) !== 'cont' || empty($conttxns)) {
            return;
        }

        $seen = [];
        foreach ($conttxns as $contTxn) {
            $key = ($contTxn['contacntno'] ?? '') . '|' . ($contTxn['conttrantype'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            GlReportConfColumnContTxn::create([
                'conf_column_id' => $column->id,
                'contacntno' => $contTxn['contacntno'],
                'conttrantype' => $contTxn['conttrantype'],
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => $userid,
                'updated_by' => $userid,
            ]);
        }
    }
}
