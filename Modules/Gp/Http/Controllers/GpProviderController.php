<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpProviderConf;
use Modules\Gp\Entities\Views\VwGpProviderConf;
use Modules\Gp\Http\Requests\GpProviderRequest;

class GpProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, VwGpProviderConf::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'created_at', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpProviderRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if (isset($validated['instid'])) {
            if ($user->isadmin != 1) {
                $validated['instid'] = $user->instid;
            }
        } else {
            $validated['instid'] = $user->instid;
        }

        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;

        if (isset($validated['sec1'])) {
            $validated['sec1'] = safeEncrypt($validated['sec1']);
        }
        if (isset($validated['sec2'])) {
            $validated['sec2'] = safeEncrypt($validated['sec2']);
        }
        return GpProviderConf::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $GPinst = VwGpProviderConf::where('id', $validate['id'])->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if ($GPinst) {

            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Show the specified Provder example format.
     * @param int $id
     * @return Response
     */
    public function gp091500(Request $request)
    {
        $example = VwGpProviderConf::where('statusid', 1)
            ->where('code','provider_example')->first();
        if ($example) {
            return $example;
        } else {
            $this->error("RC000010");
        }
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpProviderRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpProviderConf::where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->find($validated['id']);
        if (isset($validated['sec1'])) {
            if($validated['sec1'] != $inst->sec1){
                $validated['sec1'] = safeEncrypt($validated['sec1']);
            }
        }
        if (isset($validated['sec2'])) {
            if($validated['sec2'] != $inst->sec2){
                $validated['sec2'] = safeEncrypt($validated['sec2']);
            }
        }
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $dtl = GpProviderConf::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
