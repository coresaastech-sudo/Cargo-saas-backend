<?php

namespace Modules\Gl\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlAccount;
use Modules\Gl\Entities\GlReportConfColumn;
use Modules\Gl\Entities\Views\VwGlAccount;
use Modules\Gl\Http\Requests\GlAccountRequest;
use Modules\Gp\Entities\GPInstGp;

class GlAccountListController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gl010000
     * @return Response
     */
    public function index(Request $request)
    {
        $v = $this->validate($request, [
            'conf_detail_id' => 'nullable',
            'colidx' => 'nullable',
        ]);
        $sql = VwGlAccount::where('statusid', 1)
            ->where('instid', auth()->user()->instid);
        $validated = $this->validate($request, [
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
            'orders' => 'nullable|array',
            'orders.*.field' => 'required|max:60',
            'orders.*.dir' => 'nullable|max:5',
        ], [
            'filters.array' => 'VC000010',
            'filters.*.field.required' => 'VC000010',
            'filters.*.value.max' => 'VC000010',
            'filters.*.cond.max' => 'VC000010',
            'orders.array' => 'VC000011',
            'orders.*.field.required' => 'VC000011',
            'orders.*.field.max' => 'VC000011',
            'orders.*.dir.max' => 'VC000011',
        ]);
        if (empty($validated['orders'])) {
            $validated['orders'] = [['field' => 'listorder', 'dir' => 'ASC'], ['field' => 'acntno', 'dir' => 'ASC']];
        }

        if (isset($v['conf_detail_id']) && !empty($v['conf_detail_id'])) {
            $sql = $sql->whereNotIn('acntno', function ($query) use ($v) {
                $query->select('acntno')
                    ->from(with(new GlReportConfColumn)->getTable())
                    ->where('conf_detail_id', $v['conf_detail_id'])
                    ->where('columnidx', $v['colidx'])
                    ->where('statusid', 1);
            });
        }

        $sql = $this->applyFilters($sql, @$validated['filters']);
        $sql = $this->applyOrders($sql, @$validated['orders']);
        // Log::debug($sql);
        return $sql->get();
    }

    /**
     * Store a newly created resource in storage.
     * AC gl010200
     * @param Request $request
     * @return Response
     */
    public function store(GlAccountRequest $request)
    {
        $validated = $request->validated();
        $gpgl = GPInstGp::where('instid', auth()->user()->instid)->where('itemname', 'GLLen')->first();
        $gpseg = GPInstGp::where('instid', auth()->user()->instid)->where('itemname', 'SegmentLen')->first();
        if (strlen($validated['acntno']) != $gpgl->itemvalue + $gpseg->itemvalue) {
            $this->error('RC000161');
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        return GlAccount::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'acntno' => 'required'
        ], [
            'acntno.required' => "RC000011"
        ]);

        $GPinst = VwGlAccount::where('instid', auth()->user()->instid)
            ->where('acntno', $validate['acntno'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", ['id' => $validate['acntno']]);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GlAccountRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['acntno'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $dtl = GlAccount::where('instid', auth()->user()->instid)
            ->where('statusid', 1)->find($validated['acntno']);
        $dtl->update($validated);
    }
    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'acntno' => 'required'
        ], [
            'acntno.required' => "RC000011"
        ]);
        try {
            DB::beginTransaction();
            $dtl = GlAccount::where('acntno', $validate['acntno'])
                ->where('instid', auth()->user()->instid)
                ->where('statusid', 1)->first();
            if ($dtl) {
                $count = GlAccount::where('acntno', $validate['acntno'])
                    ->where('instid', auth()->user()->instid)
                    ->count();

                $newStatusId = - ($count + 1);

                GlAccount::where('acntno', $validate['acntno'])
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', 1)->update([
                        'statusid' => $newStatusId,
                        'updated_by' => auth()->user()->id,
                    ]);

                DB::commit();
            } else {
                $this->error('RC000027');
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
