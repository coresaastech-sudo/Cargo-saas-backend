<?php

namespace Modules\Gl\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlChart;
use Modules\Gl\Http\Requests\GlChartRequest;
use Modules\Gp\Entities\GPInstGp;

class GlChartListController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gl012000
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'filters' => 'nullable|array',
            'filters.*.field' => 'nullable|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
            'orders' => 'nullable|array',
            'orders.*.field' => 'nullable|max:60',
            'orders.*.dir' => 'nullable|max:5',
            'perPage' => 'nullable|numeric',
            'page' => 'nullable|numeric'
        ], [
            'filters.array' => 'VC000010',
            'filters.*.field.required' => 'VC000010',
            'filters.*.value.max' => 'VC000010',
            'filters.*.cond.max' => 'VC000010',
            'orders.array' => 'VC000011',
            'orders.*.field.required' => 'VC000011',
            'orders.*.field.max' => 'VC000011',
            'orders.*.dir.max' => 'VC000011',
            'perPage.numeric' => 'VC000012',
            'page.numeric' => 'VC000012',
        ]);

        if (isset($validated['filters'])) {
            $data = $this->getGridData($request, GlChart::where('instid', auth()->user()->instid)
                ->where('statusid', 1)->orderBy('listorder')->orderBy('acntno'));
        } else {
            $data = GlChart::where('instid', auth()->user()->instid)
                ->where('statusid', 1)->orderBy('listorder')->orderBy('acntno')->get();
        }

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GlChartRequest $request)
    {
        $validated = $request->validated();
        $createdata = [];
        // return $validated['data'];
        DB::beginTransaction();
        try {
            foreach ($validated['data'] as $key => $value) {
                $gpgl = GPInstGp::where('instid', auth()->user()->instid)->where('itemname', 'GLLen')->first();
                if (strlen($value['acntno']) > (int)$gpgl->itemvalue) {
                    $this->error('RC000161');
                }
                $value['name'] = Str::upper($value['name'] ?? '');
                $value['name2'] = Str::upper($value['name2'] ?? '');
                $value['updated_by'] = auth()->user()->id;
                $dtl = GlChart::where('acntno', $value['acntno'])
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', 1)->first();
                if (empty($dtl)) {
                    $value['statusid'] = 1;
                    $value['instid'] = auth()->user()->instid;
                    $value['created_by'] = auth()->user()->id;
                    $createdata[] = $value;
                } else {
                    GlChart::where('acntno', $value['acntno'])
                        ->where('instid', auth()->user()->instid)
                        ->where('statusid', 1)->update($value);
                }
            }
            if (count($createdata) > 0) {
                GlChart::insert($createdata);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'acntno' => 'required'
        ], [
            'acntno.required' => "RC000011"
        ]);

        $glcount = GlChart::where('acntno', $validate['acntno'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->count();

        GlChart::where('acntno', $validate['acntno'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->update([
                'statusid' => ($glcount + 1) * -1,
                'updated_by' => auth()->user()->id,
            ]);
    }
}
