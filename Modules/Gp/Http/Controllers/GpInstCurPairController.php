<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstCurPair;
use Modules\Gp\Http\Requests\GpInstCurPairRequest;

class GpInstCurPairController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, GpInstCurPair::select([
            'id',
            'curcode',
            'curcode2',
            'instid',
            'statusid',
        ])->where('statusid', 1)->where('instid', auth()->user()->instid));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstCurPairRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $validated['statusid'] = 1;

        if ($validated['curcode'] == $validated['curcode2']) {
            $this->error("RC000023", $validated);
        }

        $GPinstcurpair = GpInstCurPair::where('instid', auth()->user()->instid)
            ->where('curcode', $validated['curcode'])
            ->where('curcode2', $validated['curcode2'])
            ->where('statusid', 1)->first();

        if ($GPinstcurpair) {
            $this->error("RC000024", $validated);
        }

        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        GpInstCurPair::create($validated);
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
            'instid' => 'nullable'
        ], [
            'id.required' => "RC000021",
        ]);

        $GPinstcurpair = GpInstCurPair::where('instid', auth()->user()->instid)
            ->where('id', $validated['id'])
            ->where('statusid', 1)->first();
        if ($GPinstcurpair) {
            return $GPinstcurpair;
        } else {
            $this->error("RC000021", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpInstCurPairRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000021");
        }

        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstCurPair::where('instid', auth()->user()->instid)->where('statusid', 1)->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000017",
        ]);
        $dtl = GpInstCurPair::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        $count = GpInstCurPair::where('instid', auth()->user()->instid)
            ->where('curcode', $dtl->curcode)->where('statusid', '<>', 1)->count();

        $dtl->update([
            'statusid' =>  $count ? ($count + 1) * -1 : -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
