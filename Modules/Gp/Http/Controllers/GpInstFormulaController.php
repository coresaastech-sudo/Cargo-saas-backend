<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Gp\Http\Requests\GpInstFormulaRequest;
use Modules\Gp\Entities\GpInstFormula;
use Modules\Gp\Entities\Views\VwGpInstFormulaDetail;
use Illuminate\Support\Facades\Log;

class GpInstFormulaController extends Controller
{
    /**
     * Display a listing of the resource.
     * gp019001
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, GpInstFormula::where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid),
            [['field' => 'id', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * gp019201
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'nullable',
            'name' => 'required',
            'name2' => 'required',
            'formula' => 'required',
            'type' => 'required'
        ], [
            'formula.required' => "RC000011",
            'type.required' => "RC000011",
        ]);
        $user = auth()->user();
        if ($user->instid != 1) {
            $validated['instid'] = $user->instid;
        }
        $validated['instid'] = $user->instid;
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;

        GpInstFormula::create($validated);
    }

    /**
     * Show the specified resource.
     * gp019101
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $user = auth()->user();
        return VwGpInstFormulaDetail::where("instid", $user->instid)->where("statusid", 1)->where("id", $validated['id'])->first();
    }

    /**
     * Update the specified resource in storage.
     * gp019301
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
            'name' => 'required',
            'name2' => 'required',
            'formula' => 'required',
            'type' => 'required'
        ], [
            'formula.required' => "RC000011",
            'type.required' => "RC000011",
        ]);
        $user = auth()->user();

        if (isset($validated['id'])) {
            $id_tmp = $validated['id'];
            unset($validated['id']);
            $validated['updated_by'] = $user->id;
            GpInstFormula::where("instid", $user->instid)->where("id", $id_tmp)->update($validated);
        } else {
            $this->error("RC000011");
        }
    }

    /**
     * Remove the specified resource from storage.
     * gp019401
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $user = auth()->user();

        GpInstFormula::where("instid", $user->instid)->where("id", $validated['id'])->update(["statusid" => -1, "updated_by" => $user->id]);
    }
}
