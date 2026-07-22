<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\Gp\Entities\GpInstFreqFeeJob;
use Modules\Gp\Enums\CacheGroupEnum;
use Illuminate\Support\Str;
use Modules\Gp\Http\Requests\GpInstFreqFeeJobRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstFreqFeeJobController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp015000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            GpInstFreqFeeJob::where('instid', auth()->user()->instid)->where('statusid', 1)
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstFreqFeeJobRequest $request)
    {
        $validated = $request->validated();
        $isLast = GpInstFreqFeeJob::where('instid', auth()->user()->instid)
            ->orderBy('created_at', 'desc')->first();
        $seq = '001';
        if ($isLast) {
            $seq = fillZeroString(substr($isLast->jobcode, -3) * 1 + 1, 3);
        }
        $validated['jobcode'] = Str::upper('j' . $seq);
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $user = auth()->user();
        $validated['instid'] = $user->instid;
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        GpInstFreqFeeJob::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $instid =  auth()->user()->instid;
        $GPinstqual = GpInstFreqFeeJob::where('id', $validated['id'])
            ->where('statusid', 1)
            ->where('instid', $instid)
            ->first();
        if ($GPinstqual) {
            return $GPinstqual;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(GpInstFreqFeeJobRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $instid =  auth()->user()->instid;
        $gpsusp = GpInstFreqFeeJob::where('id', $validated['id'])
            ->where('statusid',  1)->where('instid', $instid)
            ->first();

        if (!$gpsusp) {
            $this->error('RC000027');
        }

        $validated['updated_by'] = auth()->user()->id;
        GpInstFreqFeeJob::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $suspcode = GpInstFreqFeeJob::where('instid', auth()->user()->instid)->where('statusid', 1)->find($validated['id']);
        $suspcount = GpInstFreqFeeJob::where('instid', auth()->user()->instid)->where('statusid', '<>', 1)
            ->where('jobcode', $suspcode->jobcode)->count();

        GpInstFreqFeeJob::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->update([
                'statusid' => $suspcount ? ($suspcount + 1) * -1 : -1,
                'updated_by' => auth()->user()->id
            ]);
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_susp
        );
    }
}
