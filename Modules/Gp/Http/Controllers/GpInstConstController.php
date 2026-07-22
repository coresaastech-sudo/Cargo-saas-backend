<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstConst;
use Modules\Gp\Http\Requests\GpInstConstRequest;
use Illuminate\Support\Str;

class GpInstConstController extends Controller
{
    /**
     * Display a listing of the resource.
     * AC gp040000
     * @return Response
     */
    public function index(Request $request)
    {
        $query = GpInstConst::where('statusid', '<>', -1)
            ->when(request('parent_code', '') != '', function ($q) {
                $q->where('parent_code', request('parent_code'));
            })
            ->when(request('parent_code', '') == '', function ($q) {
                $q->whereNull('parent_code');
            });
        // ->orderBy('listorder', 'ASC');
        if (auth()->user()->isadmin != 1) {
            $query = $query->whereIn('instid', [1, auth()->user()->instid]);
        }
        return $this->getGridData($request, $query, [['field' => 'id', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstConstRequest $request)
    {
        $validated = $request->validated();
        $validated['statusid'] = 1;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        $validated['instid'] = auth()->user()->instid;
        if (isset($validated['parent_id'])) {
            $parent = GpInstConst::where('id', $validated['parent_id'])
                ->where('statusid', '<>', -1)->first();
            if ($parent) {
                $validated['parent_code'] = $parent->code;
                $temp = $validated['code'] ?? "";
                $validated['code'] = $parent->code . '_' . $validated['value'];
                if (Str::length($validated['code']) > 30) {
                    $validated['code'] = $temp;
                }
            }
        }
        if (!isset($validated['is_show_front'])) {
            $validated['is_show_front'] = 0;
        }
        return GpInstConst::create($validated);
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
        $GPinst = GpInstConst::where('id', $validate['id'])->where('statusid', '<>', -1)->first();
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
    public function update(GpInstConstRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        if (!isset($validated['is_show_front'])) {
            $validated['is_show_front'] = 0;
        }
        $data = GpInstConst::where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        if ($data) {
            if (auth()->user()->isadmin != 1) {
                if (auth()->user()->instid != $data->instid) {
                    $this->error('RC000026');
                }
            }
            $data->update($validate);
        } else {
            $this->error('RC000010', ['id' => $validate['id']]);
        }
    }

    /**
     * Remove the specified resource from storage.
     * @AC gp040400
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $data = GpInstConst::where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        if ($data) {
            if (auth()->user()->isadmin != 1) {
                if (auth()->user()->instid != $data->instid) {
                    $this->error('RC000026');
                }
            }
            $data->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
        } else {
            $this->error('RC000010', ['id' => $validate['id']]);
        }
    }
}
