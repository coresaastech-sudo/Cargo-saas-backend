<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstDocTempActionCode;
use Modules\Gp\Http\Requests\GpInstDocTempActionCodeRequest;

use Modules\Gp\Entities\GpInstDocTemp;
use Modules\Gp\Entities\Views\VwGpInstDocTempActionCode;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Services\CoreService;

class GpInstDocTempActionCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwGpInstDocTempActionCode::where('instid', auth()->user()->instid)
                ->where('statusid', 1),
            [['field' => 'doctempid', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstDocTempActionCodeRequest $request)
    {
        $validated = $request->validated();

        $validated['statusid'] = 1;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;

        $search = GpInstDocTemp::where("id", $validated["doctempid"])->where("instid", auth()->user()->instid)->where("statusid", 1)->first();

        if ($search) {
            $docTempActionCode = GpInstDocTempActionCode::create($validated);
            return $docTempActionCode;
        } else {
            $this->error("RC000020");
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpInstDocTempActionCodeRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['statusid'] = 1;
        $validated['updated_by'] = auth()->user()->id;

        GpInstDocTempActionCode::where("instid", auth()->user()->instid)->where("id", $validated["id"])->update($validated);
    }
    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011"
        ]);
        $docTempActionCode = VwGpInstDocTempActionCode::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if ($docTempActionCode) {
            return $docTempActionCode;
        } else {
            $this->error("RC000020");
        }
    }

    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011"
        ]);

        GpInstDocTempActionCode::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->update([
                "statusid" => -1,
                "updated_by" => auth()->user()->id
            ]);
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_doc_temp
        );
    }
}
