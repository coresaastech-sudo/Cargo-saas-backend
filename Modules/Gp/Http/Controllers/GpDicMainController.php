<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpDicMain;
use Modules\Gp\Entities\GpInstConst;
use Modules\Gp\Enums\ResponseCodeEnum;
use Illuminate\Support\Facades\Schema;

class GpDicMainController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp040001
     * @return Response
     */
    public function index(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $query = GpDicMain::select('GP_dic_mains.*', 'GP_dic_mains.dic_code as id');
        return $this->getGridData(
            $request,
            $query,
            [['field' => 'dic_code', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }

        $validated = $this->validateMe($request, [
            'dic_code' => 'nullable',
            'vw_name' => 'required',
            'description' => 'required',
        ], [
            'vw_name.required' => ResponseCodeEnum::required,
            'description.required' => ResponseCodeEnum::required
        ]);
        if (empty($validated['dic_code'])) {
            $dicmain = GpDicMain::orderBy('dic_code', 'desc')->first();
            if ($dicmain) {
                $diccode = 'DIC_' . $this->fillDicCodeValue(substr($dicmain->dic_code, 4));
            } else {
                $diccode = '000';
            }
            $validated['dic_code'] = $diccode;
        }

        GpDicMain::create([
            'dic_code' => $validated['dic_code'],
            'vw_name' => $validated['vw_name'],
            'description' => $validated['description'],
        ]);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        return $GPdicmain = GpDicMain::select('GP_dic_mains.*', 'GP_dic_mains.dic_code as id')->where('dic_code', $validate['id'])->first();
        if ($GPdicmain) {
            return $GPdicmain;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    public function update(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validated = $this->validateMe($request, [
            'dic_code' => 'nullable',
            'vw_name' => 'required',
            'description' => 'required',
        ], [
            'vw_name.required' => ResponseCodeEnum::required,
            'description.required' => ResponseCodeEnum::required
        ]);
        // $validate = $request->validated();
        if (empty($validated['dic_code'])) {
            $this->error("RC000011");
        }
        GpDicMain::where('dic_code', $validated['dic_code'])->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
    }

    /**
     * @AC gp040099
     */
    public function getDictionary(Request $request)
    {
        $validated = $this->validateMe($request, [
            'dic_code' => 'required',
            'parentValue' => 'nullable',
            'parentDicCode' => 'nullable',
        ], [
            'dic_code.required' => ResponseCodeEnum::required
        ]);

        $dicmain = GpDicMain::where('dic_code', $validated['dic_code'])->first();
        $admininsid = 1;
        if ($dicmain) {
            // VIEW дээрх багана авах
            $columns = Schema::getColumnListing(mb_strtolower($dicmain->vw_name));

            // ID, LISTORDER -аас бусад field авав.
            $columns = array_diff($columns, ['id', 'listorder']);

            $selects = [
                'ID::BIGINT as ID',
                'LISTORDER::BIGINT as LISTORDER',
            ];

            // Багана нэмэв.
            foreach ($columns as $col) {
                $selects[] = $col;
            }

            if ($validated['dic_code'] == 'DIC_002' || !empty($validated['parentValue'])) {
                if (!empty($validated['parentValue']) && !empty($validated['parentDicCode'])) {
                    $dicmaintmp = GpDicMain::where('dic_code', $validated['parentDicCode'])->first();

                    // VIEW дээрх багана авах
                    $columnsTmp = Schema::getColumnListing(mb_strtolower($dicmaintmp->vw_name));

                    // ID, LISTORDER -аас бусад field авав.
                    $columnsTmp = array_diff($columnsTmp, ['id', 'listorder']);

                    $selectsTmp = [
                        'ID::BIGINT as ID',
                        'LISTORDER::BIGINT as LISTORDER',
                    ];

                    // Багана нэмэв.
                    foreach ($columnsTmp as $col) {
                        $selectsTmp[] = $col;
                    }

                    if ($dicmaintmp) {
                        if ($validated['dic_code'] == 'DIC_002') {
                            if (auth()->user()->isadmin == 1) {
                                $rawdata = DB::select(
                                    'select ' . implode(', ', $selectsTmp) . ' from ' . $dicmaintmp->vw_name . ' where value = ?',
                                    [$validated['parentValue']]
                                );
                            } else {
                                $rawdata = DB::select(
                                    'select ' . implode(', ', $selectsTmp) . ' from ' . $dicmaintmp->vw_name . ' where instid in (?, ?) and value = ?',
                                    [$admininsid, auth()->user()->instid, $validated['parentValue']]
                                );
                            }
                            if (count($rawdata) > 0) {
                                $const = GpInstConst::where('id', $rawdata[0]->id)
                                    ->where('statusid', '<>', -1)->first();
                                if ($const) {
                                    $validated['parent_code'] = $const->code;
                                } else {
                                    return [];
                                }
                            }
                        } else {
                            $validated['parent_code'] = $validated['parentValue'];
                        }

                        // Багана нэмэв.
                        foreach ($columnsTmp as $col) {
                            $selectsTmp[] = $col;
                        }

                        if (auth()->user()->isadmin == 1) {
                            return DB::select(
                                'select ' . implode(', ', $selects) . ' from ' . $dicmain->vw_name . ' where parent_code = ?',
                                [$validated['parent_code'] ?? '']
                            );
                        } else {
                            return DB::select(
                                'select ' . implode(', ', $selects) . ' from ' . $dicmain->vw_name . ' where parent_code = ? and instid in (?, ?)',
                                [$validated['parent_code'] ?? '', $admininsid, auth()->user()->instid]
                            );
                        }
                    } else {
                        $this->error("RC000009", ['dic_code' => $validated['parentDicCode']]);
                    }
                } else {
                    $this->error("RC000009", $validated);
                }
            }
            if ($validated['dic_code'] == 'DIC_023') {
                $admininsid = auth()->user()->instid;
            }

            return DB::select('select ' . implode(', ', $selects) . ' from ' . $dicmain->vw_name . ' where instid in (?, ?)', [$admininsid, auth()->user()->instid]);
        } else {
            $this->error("RC000009", $validated);
        }
    }

    public function fillDicCodeValue($dic_code)
    {
        $dic_code = (intval($dic_code) + 1) . "";
        switch (strlen($dic_code)) {
            case 1:
                $dic_code = '00' . $dic_code;
                break;
            case 2:
                $dic_code = '0' . $dic_code;
                break;
            default:
                # code...
                break;
        }
        return $dic_code;
    }
}
