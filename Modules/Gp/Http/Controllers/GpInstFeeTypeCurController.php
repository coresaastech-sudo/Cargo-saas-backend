<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstFeeTypeCur;
use Modules\Gp\Entities\GpInstFeeTypeRate;
use Modules\Gp\Entities\Views\VwGpInstFeeCurList;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Requests\GpInstFeeTypeCurRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstFeeTypeCurController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validateMe($request, [
            'feecode' => 'required'
        ], [
            'feecode.required' => "RC000011"
        ]);
        return $this->getGridData(
            $request,
            VwGpInstFeeCurList::where('instid', auth()->user()->instid)
                ->where('feecode', $validate['feecode'])
                ->where('statusid', 1),

            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstFeeTypeCurRequest $request)
    {
        $validated = $request->validated();
        $fee = GpInstFeeTypeCur::where('instid', auth()->user()->instid)
            ->where('feecode', $validated['feecode'])
            ->where('curcode', $validated['curcode'] ?? null)
            ->where('statusid', 1)->first();
        if ($fee) {
            $this->error("RC000028");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;

        try {
            DB::beginTransaction();
            if (isset($validated['ratetierdatas'])) {
                foreach ($validated['ratetierdatas'] as $key => $value) {
                    $this->createFeeTypeRate([
                        'feecode' => $validated['feecode'],
                        'curcode' => $validated['curcode'],
                        'calcmeth' => $value['calcmeth'],
                        'flatrate' => $value['flatrate'] ?? 0,
                        'perrate' => $value['perrate'] ?? 0,
                        'intervalno' => $value['intervalno'],
                        'minamount' => $value['minamount'],
                        'maxamount' => $value['maxamount'],
                        'uselncount' => $validated['calcmeth'] == 5 ? 1 : 0,
                        'loancount' => $value['loancount'] ?? 0,
                    ]);
                }
            }

            $feecode = GpInstFeeTypeCur::create($validated);
            DB::commit();
            return $feecode->feecode;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = VwGpInstFeeCurList::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();

        if ($GPinst) {
            $GPinst->ratetierdatas = GpInstFeeTypeRate::where('instid', auth()->user()->instid)
                ->where('feecode', $GPinst->feecode)
                ->where('curcode', $GPinst->curcode)
                ->where('statusid', 1)
                ->orderBy('intervalno', 'DESC')
                ->get();
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
    public function update(GpInstFeeTypeCurRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstFeeTypeCur::where('instid', auth()->user()->instid)
            ->where('statusid', 1)->where('id', $validated['id'])->first();
        if (empty($inst)) {
            $this->error('RC000027');
        }
        if ($inst->curcode != $validated['curcode'] || $inst->feecode != $validated['feecode']) {
            $fee = GpInstFeeTypeCur::where('instid', auth()->user()->instid)
                ->where('feecode', $validated['feecode'])
                ->where('curcode', $validated['curcode'] ?? null)
                ->where('statusid', 1)->first();
            if ($fee) {
                $this->error("RC000028");
            }
        }
        DB::beginTransaction();
        try {
            $includeData = [];
            if (isset($validated['ratetierdatas'])) {
                foreach ($validated['ratetierdatas'] as $key => $value) {
                    $feerate = GpInstFeeTypeRate::where('instid', auth()->user()->instid)
                        ->where('feecode', $inst->feecode)
                        ->where('curcode', $validated['curcode'])
                        ->where('intervalno', $value['intervalno'])
                        ->where('statusid', 1)->first();
                    $includeData[] = $value['intervalno'];
                    if (empty($feerate)) {
                        $this->createFeeTypeRate([
                            'feecode' => $validated['feecode'],
                            'curcode' => $validated['curcode'],
                            'calcmeth' => $value['calcmeth'],
                            'flatrate' => $value['calcmeth'] == 2 ? $value['flatrate'] : 0,
                            'perrate' => $value['calcmeth'] == 1 ? $value['perrate'] : 0,
                            'intervalno' => $value['intervalno'],
                            'minamount' => $value['minamount'],
                            'maxamount' => $value['maxamount'],
                            'uselncount' => $validated['calcmeth'] == 5 ? 1 : 0,
                            'loancount' => $value['loancount'] ?? 0,
                        ]);
                    } else {
                        $feerate->update($value);
                    }
                }
                GpInstFeeTypeRate::where('instid', auth()->user()->instid)
                    ->where('feecode', $inst->feecode)
                    ->where('curcode', $validated['curcode'])
                    ->where('statusid', 1)
                    ->whereNotIn('intervalno', $includeData)
                    ->update([
                        'statusid' => -1,
                        'updated_by' => auth()->user()->id,
                    ]);
            }
            $inst->update($validated);
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
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $dtl = GpInstFeeTypeCur::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        DB::beginTransaction();
        try {
            GpInstFeeTypeRate::where('instid', auth()->user()->instid)
                ->where('feecode', $dtl->feecode)
                ->where('curcode', $dtl->curcode)
                ->where('statusid', 1)->update([
                    'statusid' => -1,
                    'updated_by' => auth()->user()->id,
                ]);
            $dtl->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id,
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function createFeeTypeRate($data = [])
    {
        if ($data['minamount'] > $data['maxamount']) {
            $this->error('VC000020');
        }
        GpInstFeeTypeRate::create([
            'feecode' => $data['feecode'],
            'curcode' => $data['curcode'],
            'calcmeth' => $data['calcmeth'],
            'intervalno' => $data['intervalno'],
            'minamount' => $data['minamount'],
            'maxamount' => $data['maxamount'],
            'perrate' => $data['perrate'],
            'flatrate' => $data['flatrate'],
            'uselncount' => $data['uselncount'],
            'loancount' => $data['loancount'],
            'statusid' => 1,
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ]);
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_fee_cur
        );
    }
}
