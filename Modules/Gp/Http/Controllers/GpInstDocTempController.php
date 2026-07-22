<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstDocTemp;
use Modules\Gp\Entities\GpInstDocTempVar;
use Modules\Gp\Entities\GpInstDocTempFormInput;
use Modules\Gp\Http\Requests\GpInstDocTempRequest;
use Modules\Gp\Entities\Views\VwGpInstDocTempDetail;
use Modules\Gp\Entities\Views\VwGpInstDocTempList;

class GpInstDocTempController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwGpInstDocTempList::where('instid', auth()->user()->instid)
                ->where('statusid', 1),
            [['field' => 'id', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstDocTempRequest $request)
    {
        $validated = $request->validated();
        $validated['statusid'] = 1;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        $validated['instid'] = auth()->user()->instid;
        if ($validated['doctype'] === 0) {
            return GpInstDocTemp::create($validated);
        } else if ($validated['doctype'] === 1 && array_key_exists('data', $validated)) {
            $docTemp = GpInstDocTemp::create($validated);
            foreach ($validated['data'] as $value) {
                if (empty($value['name'])) unset($value['name']);
                unset($value['id']);
                $value['statusid'] = 1;
                $value['instid'] = auth()->user()->instid;
                $value['created_by'] = auth()->user()->id;
                $value['updated_by'] = auth()->user()->id;
                $value['doctempid'] = $docTemp->id;
                GpInstDocTempVar::create($value);
            }
            return $docTemp;
        } else if ($validated['doctype'] === 2 && array_key_exists('data', $validated)) {
            $docTemp = GpInstDocTemp::create($validated);
            foreach ($validated['data'] as $value) {
                if (empty($value['name'])) unset($value['name']);
                unset($value['id']);
                $value['statusid'] = 1;
                $value['instid'] = auth()->user()->instid;
                $value['created_by'] = auth()->user()->id;
                $value['updated_by'] = auth()->user()->id;
                $value['doctempid'] = $docTemp->id;
                GpInstDocTempFormInput::create($value);
            }
            return $docTemp;
        } else {
            $this->error("RC000027");
        }
    }

    public function update(GpInstDocTempRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['updated_by'] = auth()->user()->id;

        $user = auth()->user();

        $docTemp = GpInstDocTemp::where('id', $validated['id'])->first();
        GpInstDocTempVar::where("instid", auth()->user()->instid)->where("doctempid", $docTemp->id)->update(["statusid" => -1, 'updated_by' => $user->id]);
        GpInstDocTempFormInput::where("instid", auth()->user()->instid)->where("doctempid", $docTemp->id)->update(["statusid" => -1, 'updated_by' => $user->id]);

        $data = array_key_exists('data', $validated) ? $validated['data'] : null;
        unset($validated['data']);

        GpInstDocTemp::where('id', $docTemp->id)->update($validated);

        if ($data) $validated['data'] = $data;

        if ($validated['doctype'] === 0) {
            GpInstDocTemp::where('id', $docTemp->id)->update($validated);
        } else if ($validated['doctype'] === 1 && array_key_exists('data', $validated)) {
            foreach ($data as $value) {
                if (empty($value['name'])) unset($value['name']);
                if (empty($value['id'])) unset($value['id']);
                $value['statusid'] = 1;
                $value['instid'] = auth()->user()->instid;
                $value['updated_by'] = auth()->user()->id;
                $value['doctempid'] = $docTemp->id;
                $search = GpInstDocTempVar::where("instid", $validated["instid"])->where("doctempid", $docTemp->id)->where("variable", $value["variable"])->first();
                if ($search) {
                    GpInstDocTempVar::where("instid", $validated["instid"])->where("doctempid", $docTemp->id)->where("variable", $value["variable"])->update($value);
                } else {
                    $value["created_by"] = auth()->user()->id;
                    GpInstDocTempVar::create($value);
                }
            }
        } else if ($validated['doctype'] === 2 && array_key_exists('data', $validated)) {
            foreach ($data as $value) {
                if (empty($value['id'])) unset($value['id']);
                if (empty($value['name'])) unset($value['name']);
                $value['statusid'] = 1;
                $value['instid'] = auth()->user()->instid;
                $value['updated_by'] = auth()->user()->id;
                $value['doctempid'] = $docTemp->id;
                $search = GpInstDocTempFormInput::where("instid", auth()->user()->instid)->where("doctempid", $docTemp->id)->where("input", $value["input"])->first();
                if ($search) {
                    GpInstDocTempFormInput::where("instid", auth()->user()->instid)->where("doctempid", $docTemp->id)->where("input", $value["input"])->update($value);
                } else {
                    $value['created_by'] = auth()->user()->id;
                    GpInstDocTempFormInput::create($value);
                }
            }
        }
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
        $docTemp = VwGpInstDocTempDetail::with(['docTempFormInput', 'docTempVar'])->where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if ($docTemp) {
            if (!empty($docTemp->docTempFormInput) && count($docTemp->docTempFormInput) > 0) $docTemp->data = $docTemp->docTempFormInput;
            if (!empty($docTemp->docTempVar) && count($docTemp->docTempVar) > 0) $docTemp->data = $docTemp->docTempVar;
            return $docTemp;
        } else {
            $this->error("RC000021");
        }
    }

    /**
     * Delete the specified resource.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011"
        ]);

        $docTemp = GpInstDocTemp::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)->where('statusid', 1)->first();
        $docTemp->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }
}
