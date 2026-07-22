<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstCur;
use Modules\Gp\Entities\Views\VwGpInstCur;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Requests\GpInstCurRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstCurController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     * @AC gp013000
     */
    public function index(Request $request)
    {

        return $this->getGridData($request, VwGpInstCur::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'listorder', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstCurRequest $request)
    {
        $validated = $request->validated();
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        return GpInstCur::create($validated);
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
        $GPinst = VwGpInstCur::where('instid', auth()->user()->instid)
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
    public function update(GpInstCurRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstCur::where('instid', auth()->user()->instid)
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
            'id.required' => "RC000011"
        ]);
        $dtl = GpInstCur::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])->where('statusid', 1)->first();
        $count = GpInstCur::where('instid', auth()->user()->instid)
            ->where('curcode', $dtl->curcode)->where('statusid', '<>', 1)->count();

        $dtl->update([
            'statusid' =>  $count ? ($count + 1) * -1 : -1,
            'updated_by' => auth()->user()->id,
        ]);
    }

    /**
     * Cache цэвэрлэх
     *  @AC gp013900
     * @return void
     */
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_cur
        );
    }
}
