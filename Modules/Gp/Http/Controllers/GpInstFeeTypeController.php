<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstFeeType;
use Modules\Gp\Entities\GpInstFeeTypeSource;
use Modules\Gp\Http\Requests\GpInstFeeTypeRequest;
use Modules\Gp\Entities\Views\VwGpInstFeeList;
use Modules\Gp\Entities\Views\VwGpInstFeeTypeSource;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Services\CoreService;

class GpInstFeeTypeController extends Controller
{
    /**
     * @AC gp014000
     */
    public function index(Request $request)
    {

        return $this->getGridData($request, VwGpInstFeeList::where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid), [['field' => 'listorder', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @AC gp014200
     * @param Request $request
     * @return Response
     */
    public function store(GpInstFeeTypeRequest $request)
    {
        $validated = $request->validated();
        $iaLast = GpInstFeeType::where('instid', auth()->user()->instid)
            ->orderBy('id', 'desc')->first();
        $seq = '001';
        if ($iaLast) {
            $seq = fillZeroString(substr($iaLast->feecode, -3) * 1 + 1, 3);
        }
        $validated['feecode'] = Str::upper('f' . $seq);
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        DB::beginTransaction();
        try {
            foreach ($validated['sources'] as $key => $value) {
                GpInstFeeTypeSource::create([
                    'feecode' => $validated['feecode'],
                    'sourcecode' => $value['value'],
                    'statusid' => 1,
                    'instid' => auth()->user()->instid,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ]);
            }
            $instFee = GpInstFeeType::create($validated);
            DB::commit();
            return $instFee->id;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Show the specified resource.
     * @AC gp014100
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $dtl = VwGpInstFeeList::
            // where('instid', auth()->user()->instid)
            where('id', $validate['id'])
            ->where('statusid', '<>', -1)->first();
        if ($dtl) {
            $dtl->sources = VwGpInstFeeTypeSource::where('feecode', $dtl->feecode)
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid)->get();

            return $dtl;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpInstFeeTypeRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstFeeType::where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->find($validated['id']);

        DB::beginTransaction();
        try {
            $sources = [];
            foreach ($validated['sources'] as $key => $value) {
                $sources[] = $value['value'];
                $checkcreate = GpInstFeeTypeSource::where('feecode', $inst->feecode)
                    ->where('statusid', 1)
                    ->where('instid', auth()->user()->instid)
                    ->where('sourcecode', $value['value'])->first();
                if (empty($checkcreate)) {
                    GpInstFeeTypeSource::create([
                        'feecode' => $inst->feecode,
                        'sourcecode' => $value['value'],
                        'statusid' => 1,
                        'instid' => auth()->user()->instid,
                        'created_by' => auth()->user()->id,
                        'updated_by' => auth()->user()->id,
                    ]);
                }
            }
            $count = GpInstFeeTypeSource::where('feecode', $inst->feecode)
                ->where('statusid', '<>', 1)
                ->where('instid', auth()->user()->instid)->count();
            GpInstFeeTypeSource::where('feecode', $inst->feecode)
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid)
                ->whereNotIn('sourcecode', $sources)
                ->update([
                        'statusid' => '-' . ($count + 1),
                        'updated_by' => auth()->user()->id,
                    ]);
            $inst->update($validated);
            DB::commit();
            return $inst->id;
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
        $dtl = GpInstFeeType::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        if (empty($dtl)) {
            $this->error('RC000027');
        }
        $feecount = GpInstFeeType::where('instid', auth()->user()->instid)
            ->where('feecode', $dtl->feecode)->where('statusid', '<>', 1)->count();

        $count = GpInstFeeTypeSource::where('feecode', $dtl->feecode)
            ->where('instid', auth()->user()->instid)->count();
        GpInstFeeTypeSource::where('feecode', $dtl->feecode)
            ->where('statusid', 1)
            ->where('instid', auth()->user()->instid)->update([
                    'statusid' => '-' . ($count + 1),
                    'updated_by' => auth()->user()->id,
                ]);
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
            CacheGroupEnum::GP_inst_fee
        );
    }
}
