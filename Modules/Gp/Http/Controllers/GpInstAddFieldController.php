<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstAddField;
use Modules\Gp\Entities\Views\VwGpInstAddField;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Requests\GpInstAddFieldRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstAddFieldController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp015002
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validate($request, [
            'isdefault' => 'nullable',
        ]);
        $sqlown = VwGpInstAddField::where('statusid', 1)
            ->where('instid', auth()->user()->instid);

        if (isset($validate['isdefault']) && !$validate['isdefault']) {
            $sqlfiba = VwGpInstAddField::where('statusid', 1)
                ->where('instid', 1)
                ->whereNotIn('code', function ($query) {
                $query->select('code')
                    ->from(with(new VwGpInstAddField)->getTable())
                    ->where('statusid', 1)
                    ->where('instid', auth()->user()->instid)
                    ->whereNotNull('code');
            });
            $filteredResults = $sqlfiba;
        } else {
            $filteredResults = $sqlown;
        }

        return $this->getGridData(
            $request,
            $filteredResults,
            [
                ['field' => 'listorder', 'dir' => 'ASC'],
                ['field' => 'id', 'dir' => 'ASC']
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstAddFieldRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();

        if (!isset($validated['data'])) {
            $check = GpInstAddField::where('instid', $user->instid)
                ->where('statusid', 1)
                ->where('typecode', $validated['typecode'])
                ->where('code', $validated['code'])
                ->first();
            if (!empty($check)) {
                $this->error('RC000086', [
                    'field' => ' code: ' . $validated['code']
                ]);
            }

            $validated['name'] = Str::upper($validated['name'] ?? '');
            $validated['name2'] = Str::upper($validated['name2'] ?? '');
            $validated['statusid'] = 1;
            $validated['instid'] = $user->instid;
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;
            GpInstAddField::create($validated);
        } else {
            foreach ($validated['data'] as $item) {
                $fibacopy = GpInstAddField::where('instid', 1)
                    ->where('statusid', 1)
                    ->where('id', $item)
                    ->first();

                $check = GpInstAddField::where('instid', $user->instid)
                    ->where('statusid', 1)
                    ->where('typecode', $fibacopy['typecode'])
                    ->where('code', $fibacopy['code'])
                    ->first();
                if (empty($check)) {
                    $fibacopy = json_decode(json_encode($fibacopy), true);
                    unset($fibacopy['id']);
                    $fibacopy['instid'] = $user->instid;
                    GpInstAddField::create($fibacopy);
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
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $GPinst = VwGpInstAddField::where('id', $validate['id'])
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
     * @AC gp015302
     * @return Response
     */
    public function update(GpInstAddFieldRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $check = GpInstAddField::where('instid', $user->instid)
            ->where('id', '!=', $validated['id'])
            ->where('code', $validated['code'])
            ->first();

        if (!empty($check)) {
            $this->error('RC000086', [
                'field' => ' code: ' . $validated['code']
            ]);
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = $user->id;
        $inst = GpInstAddField::where('instid', $user->instid)
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
        $dtl = GpInstAddField::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_add_field
        );
    }
}
