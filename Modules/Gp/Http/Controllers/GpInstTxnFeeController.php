<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstTxnFee;
use Modules\Gp\Entities\Views\VwGpInstTxnFeeDetail;
use Modules\Gp\Entities\Views\VwGpInstTxnFeeList;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Requests\GpInstTxnFeeRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstTxnFeeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @AC gp015004
     * @return Response
     */
    public function index(Request $request)
    {

        $validate = $this->validate($request, [
            'ACTION_CODE' => 'required'
        ], [
            'ACTION_CODE.required' => ResponseCodeEnum::required
        ]);
        return $this->getGridData(
            $request,
            VwGpInstTxnFeeList::where('ACTION_CODE', $validate['ACTION_CODE'])
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid),
            [['field' => 'feecode', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstTxnFeeRequest $request)
    {
        $validated = $request->validated();
        $fee = GpInstTxnFee::where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->where('ACTION_CODE', $validated['ACTION_CODE'])
            ->where('feecode', $validated['feecode'])->first();
        if ($fee) {
            $this->error("RC000028");
        }
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        return GpInstTxnFee::create($validated);
    }

    /**
     * Show the specified resource.
     * @AC gp015104
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = VwGpInstTxnFeeDetail::where('instid', auth()->user()->instid)
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
    public function update(GpInstTxnFeeRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstTxnFee::where('instid', auth()->user()->instid)
            ->where('statusid', 1)->find($validated['id']);
        $inst->update($validated);
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
            'id' => "RC000011"
        ]);
        $feecode = GpInstTxnFee::where('instid', auth()->user()->instid)
            ->where('statusid', 1)->find($validate['id']);
        $feecount = GpInstTxnFee::where('instid', auth()->user()->instid)
            ->where('statusid', '<>', 1)->where('feecode', $feecode->feecode)->count();
        $dtl = GpInstTxnFee::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => $feecount ? ($feecount + 1) * -1 : -1,
            'updated_by' => auth()->user()->id,
        ]);
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_txn_fee
        );
    }
}
