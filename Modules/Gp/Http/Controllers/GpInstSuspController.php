<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\Gp\Entities\GpInstSusp;
use Modules\Gp\Http\Requests\GpInstSuspRequest;
use Modules\Gp\Entities\Views\VwGpInstSusp;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Services\CoreService;
use Illuminate\Support\Str;

class GpInstSuspController extends Controller
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
            VwGpInstSusp::where('instid', auth()->user()->instid)->where('statusid', 1)
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstSuspRequest $request)
    {
        $validated = $request->validated();
        $isdupl = GpInstSusp :: where('instid', auth()->user()->instid)->where('statusid', 1)
        ->where('acntcode', $validated['acntcode'])
        ->where('brchno', $validated['brchno'] ?? null)
        ->where('curcode', $validated['curcode'] ?? null)->first();

        if($isdupl){
            $this->error('RC000028');
        }
        $validated['acnttype'] = Str::upper($validated['acnttype']);
        $user = auth()->user();
        $validated['instid'] = $user->instid;
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        GpInstSusp::create($validated);
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
        $GPinstqual = VwGpInstSusp::
            where('id', $validated['id'])
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
    public function update(GpInstSuspRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $instid =  auth()->user()->instid;
        $gpsusp = GpInstSusp::where('id', $validated['id'])
            ->where('statusid',  1)->where('instid', $instid)
            ->first();

        if (!$gpsusp) {
            $this->error('RC000027');
        }
        $validated['acnttype'] = Str::upper($validated['acnttype']);
        $validated['updated_by'] = auth()->user()->id;
        GpInstSusp::where('id', $validated['id'])->where('statusid', 1)->update($validated);
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
        $suspcode = GpInstSusp::where('instid', auth()->user()->instid)->where('statusid', 1)->find($validated['id']);
        $suspcount = GpInstSusp::where('instid', auth()->user()->instid)->where('statusid', '<>', 1)
            ->where('acntcode', $suspcode->acntcode)
            ->where('brchno', $suspcode->brchno ?? null)
            ->where('curcode', $suspcode->curcode ?? null)->count();

        GpInstSusp::where('id', $validated['id'])->where('statusid', 1)->update([
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
