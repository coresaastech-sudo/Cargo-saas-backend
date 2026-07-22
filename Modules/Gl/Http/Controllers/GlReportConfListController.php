<?php

namespace Modules\Gl\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlReportConfColumn;
use Modules\Gl\Http\Requests\GlReportConfListRequest;
use Modules\Gl\Entities\GlReportConfList;
use Modules\Gl\Entities\GlReportConfRowList;
use Modules\Gl\Entities\Views\VwGlReportConfList;

class GlReportConfListController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gl013000(Request $request)
    {
        $user = auth()->user();
        if (isset($request['instid']) && $user->isadmin == 1) {
            $query = GlReportConfList::where('instid', $request['instid'])->where('statusid', '>', 0);
        } else {
            $query = GlReportConfList::where('instid', $user->instid)->where('statusid', '>', 0);
        }
        return $this->getGridData($request, $query, [['field' => 'listorder', 'dir' => 'ASC']]);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gl013200(GlReportConfListRequest $request)
    {
        $userid = auth()->user()->id;
        $instid = auth()->user()->instid;
        $validated = $request->validated();
        $check = GlReportConfList::where('instid', $instid)->where('statusid', 1)->where('AC', $validated['AC'])->first();
        if (!empty($check)) {
            $this->error('RC000086', [
                'field' => $validated['AC']
            ]);
        }
        $validated['statusid'] = 1;
        $validated['instid'] = $instid;
        $validated['created_by'] = $userid;
        $validated['updated_by'] = $userid;
        return GlReportConfList::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function gl013100(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $GPinst = VwGlReportConfList::where('instid', auth()->user()->instid)
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
    public function gl013300(GlReportConfListRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $check = GlReportConfList::where('instid', $user->instid)
            ->where('id', '!=', $validated['id'])
            ->where('statusid', 1)
            ->where('AC', $validated['AC'])
            ->first();
        if (!empty($check)) {
            $this->error('RC000086', [
                'field' => $validated['AC']
            ]);
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = $user->id;
        $dtl = GlReportConfList::where('instid', $user->instid)
            ->where('statusid', 1)->find($validated['id']);
        $dtl->update($validated);
    }
    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gl013400(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $dtl = GlReportConfList::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if (!empty($dtl)) {
            $checkrow = GlReportConfRowList::where('instid', $dtl->instid)
                ->where('report_conf_id', $dtl->id)
                ->where('statusid', 1)
                ->first();
            if (!empty($checkrow)) {
                $this->error('RC000213');
            }
        }
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }

    /**
     * Copy a resource in storage from another inst.
     * @param Request $request
     * @AC
     */

    public function gl013201(Request $request)
    {
        if (auth()->user()->isadmin != 1) $this->error('RC000014');
        $validate = $this->validateMe($request, [
            'copyfrom' => 'required',
            'copyto' => 'required',
            'typecodes' => 'required|array'

        ], [
            'copyfrom.required' => 'VC000008',
            'copyto.required' => 'VC000008',
            'typecodes.required' => 'VC000008'

        ]);

        DB::beginTransaction();
        try {
            $ConfDetailId = [];
            $glconf = GlReportConfList::where('statusid', ">", 0)
                ->where('instid', $validate['copyfrom'])
                ->where('AC', $validate['typecodes'])
                ->get()->toArray();

            foreach ($glconf as $key => $conf) {
                $tempid = $conf['id'];
                $exists = GlReportConfList::where('instid', $validate['copyto'])
                    ->where('AC', $conf['AC'])
                    ->where('statusid', '>', 0)
                    ->first();
                if (!$exists) {
                    unset($conf['id']);
                    unset($conf['created_at']);
                    unset($conf['updated_at']);
                    $conf['instid'] = $validate['copyto'];
                    $conf['created_by'] = auth()->user()->id;
                    $conf['updated_by'] = auth()->user()->id;
                    $conflist = GlReportConfList::create($conf);

                    // Huulbarlaj baigaa baiguullagiin Eronhii devteriin tailangiin tohirgoonuudiin delgerengui
                    $glconfdetail = GlReportConfRowList::where('statusid', '>', 0)
                        ->where('instid', $validate['copyfrom'])
                        ->where('report_conf_id', $tempid)
                        ->get();

                    foreach ($glconfdetail as $confdetail) {
                        $dtltempid = $confdetail['id'];

                        $target = GlReportConfRowList::where('instid', $validate['copyto'])
                            ->where('report_conf_id', $confdetail['report_conf_id'])
                            ->where('listorder', $confdetail->listorder)
                            ->where('num', $confdetail->num)
                            ->where('name', $confdetail->name)
                            ->where('statusid', '>', 0)
                            ->first();

                        if (!$target) {
                            $data = $confdetail->toArray();
                            unset($data['id']);
                            unset($data['created_at']);
                            unset($data['updated_at']);
                            $data['instid'] = $validate['copyto'];
                            $data['report_conf_id'] = $conflist['id'];
                            $data['created_by'] = auth()->user()->id;
                            $data['updated_by'] = auth()->user()->id;
                            $conf_dtl_list = GlReportConfRowList::create($data);

                            // Huulbarlaj baigaa baiguullagiin Eronhii devteriin tailangiin tohirgoonuudiin moriin jagsaalt
                            $glconfcol = GlReportConfColumn::where('statusid', ">", 0)
                                ->where('conf_detail_id', $dtltempid)
                                ->where('instid', $validate['copyfrom'])->get()->toArray();
                            foreach ($glconfcol as $key => $confcol) {
                                $exist = GlReportConfColumn::where('instid', $validate['copyto'])
                                    ->where('conf_detail_id', $confcol['conf_detail_id'])->first();
                                if (!$exist) {
                                    unset($confcol['id']);
                                    unset($confcol['created_at']);
                                    unset($confcol['updated_at']);
                                    $confcol['instid'] = $validate['copyto'];
                                    $confcol['conf_detail_id'] = $conf_dtl_list['id'];
                                    $confcol['created_by'] = auth()->user()->id;
                                    $confcol['updated_by'] = auth()->user()->id;
                                    GlReportConfColumn::create($confcol);
                                }
                            }
                            $ConfColId[$dtltempid] = GlReportConfRowList::where('statusid', '>', '0')
                                ->where('instid', $validate['copyto'])
                                ->orderBy('id', 'desc')->first()->id;
                        }
                    }
                }

                $ConfDetailId[$tempid] = GlReportConfList::where('statusid', '>', '0')
                    ->where('instid', $validate['copyto'])
                    ->orderBy('id', 'desc')->first()->id;
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }
}
