<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Ad\Entities\AdEbarimt;
use Modules\Ad\Entities\GPInstUser;
use Modules\Ad\Http\Requests\AdEbarimtConfigRequest;
use Modules\Ad\Entities\AdEbarimtConfig;

class AdEbarimtConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     * ad050000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            AdEbarimtConfig::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)
        );
    }

    /**
     * Store a newly created resource in storage.
     * ad050200
     * @param AdEbarimtConfigRequest $request
     * @return Response
     */
    public function store(AdEbarimtConfigRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $validated['instid'] = $user->instid;
        $validated['created_by'] = $user->id;
        $validated['statusid'] = 1;
        $insterted = AdEbarimtConfig::create($validated);
        return $insterted;
    }

    /**
     * Show the specified resource.
     * ad050100
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $instid = auth()->user()->instid;

        $config = AdEbarimtConfig::where('instid', $instid)->first();

        if ($config) {
            return $config;
        }
    }

    /**
     * Update the specified resource in storage.
     * ad050300
     * @param AdEbarimtConfigRequest $request
     * @param int $id
     * @return Response
     */
    public function update(AdEbarimtConfigRequest $request)
    {
        $validated = $request->validated();
        $validated['statusid'] = 1;
        $validated['updated_by'] = auth()->user()->id;

        AdEbarimtConfig::where('instid', auth()->user()->instid)->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * ad050400
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $instid = auth()->user()->instid;
        $validated = $request->validated();

        return AdEbarimtConfig::where("instid", $instid)->where("id", $validated["id"])->update(["statusid" => -1]);
    }
}
