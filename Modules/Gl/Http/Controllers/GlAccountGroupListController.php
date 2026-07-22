<?php

namespace Modules\Gl\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gl\Entities\GlAccountClass;
use Modules\Gl\Entities\Views\VwGlAccountClass;
use Modules\Gl\Http\Requests\GlAccountClassRequest;

class GlAccountGroupListController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gl011000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, VwGlAccountClass::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'listorder', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GlAccountClassRequest $request)
    {
        $userid = auth()->user()->id;
        $instid = auth()->user()->instid;
        $validated = $request->validated();
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = $instid;
        $validated['created_by'] = $userid;
        $validated['updated_by'] = $userid;
        return GlAccountClass::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'class' => 'required'
        ], [
            'class.required' => "RC000011"
        ]);

        $GPinst = VwGlAccountClass::where('instid', auth()->user()->instid)
            ->where('class', $validate['class'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", ['id' => $validate['class']]);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GlAccountClassRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['class'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $dtl = GlAccountClass::where('instid', auth()->user()->instid)
            ->where('statusid', 1)->find($validated['class']);
        $dtl->update($validated);
    }
    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'class' => 'required'
        ], [
            'class.required' => "RC000011"
        ]);
        $dtl = GlAccountClass::where('class', $validate['class'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
