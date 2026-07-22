<?php

namespace Modules\Gl\Http\Controllers;

use App\Http\Controllers\Controller;
use Google\Service\Docs\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlReportConfColumn;
use Modules\Gl\Entities\GlReportConfRowList;
use Modules\Gl\Entities\Views\VwGlReportRowList;
use Modules\Gl\Http\Requests\GlReportConfRowListRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlReportConfDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gl013001(Request $request)
    {
        $validate = $this->validate($request, [
            'report_conf_id' => 'required'
        ], [
            'report_conf_id.required' => ResponseCodeEnum::required
        ]);

        $data = VwGlReportRowList::where('statusid', 1)
            ->where('instid', auth()->user()->instid)
            ->where('report_conf_id', $validate['report_conf_id'])
            ->orderBy('listorder', 'ASC')->get();
        return [
            'data' => $data
        ];
    }
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    //register,update reportconfrow
    public function gl013301(GlReportConfRowListRequest $request)
    {
        $userid = auth()->user()->id;
        $instid = auth()->user()->instid;
        $validated = $request->validated();
        // Log::debug($validated);
        try {
            DB::beginTransaction();

            foreach ($validated['dataArray'] as $data) {
                if ($data['new'] == 1) {
                    GlReportConfRowList::create([
                        'report_conf_id' => $data['report_conf_id'],
                        'num' => $data['num'] ?? null,
                        'name' => $data['name'],
                        'name2' => $data['name2'] ?? null,
                        'isbegbal' => $data['isbegbal'] ?? 0,
                        'isbold' => $data['isbold'] ?? 0,
                        'listorder' => $data['listorder'] ?? 0,
                        'statusid' => 1,
                        'instid' => $instid,
                        'created_by' => $userid,
                        'updated_by' => $userid,
                    ]);
                } else {
                    GlReportConfRowList::where('instid', $instid)
                        ->where('report_conf_id', $data['report_conf_id'])
                        ->where('id', $data['id'])
                        ->update([
                            'num' => $data['num'] ?? null,
                            'name' => $data['name'],
                            'name2' => $data['name2'] ?? null,
                            'isbegbal' => $data['isbegbal'] ?? 0,
                            'isbold' => $data['isbold'] ?? 0,
                            'listorder' => $data['listorder'] ?? 0,
                            'updated_by' => $userid,
                        ]);
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
    public function gl013401(Request $request)
    {
        $userid = auth()->user()->id;
        $instid = auth()->user()->instid;
        $validated = $this->validate($request, [
            'report_conf_id' => 'required',
            'id' => 'required'
        ], [
            'report_conf_id.required' => ResponseCodeEnum::required,
            'id.required' => ResponseCodeEnum::required
        ]);

        $dtl = GlReportConfRowList::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('report_conf_id', $validated['report_conf_id'])
            ->where('statusid', 1)->first();
        if (!empty($dtl)) {
            $checkcol = GlReportConfColumn::where('instid', $dtl->instid)
                ->where('conf_detail_id', $dtl->id)
                ->where('statusid', 1)
                ->first();
            if (!empty($checkcol)) {
                $this->error('RC000213');
            }
        }
        if ($dtl) {
            $dtl->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id,
            ]);
        }
    }
}
