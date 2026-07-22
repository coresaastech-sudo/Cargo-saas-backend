<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpConnConf;
use Modules\Gp\Entities\Views\VwGpConnConf;
use Modules\Gp\Http\Requests\GpConnConfigRequest;

class GpConnectionConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, VwGpConnConf::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'created_at', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpConnConfigRequest $request)
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
        return GpConnConf::create($validated);
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
        $GPinst = GpConnConf::where('id', $validate['id'])->where('instid', auth()->user()->instid)
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
    public function update(GpConnConfigRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpConnConf::where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->find($validated['id']);
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
        $dtl = GpConnConf::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
