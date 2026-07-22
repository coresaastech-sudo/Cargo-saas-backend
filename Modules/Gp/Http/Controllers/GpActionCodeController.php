<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstPerm;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Http\Requests\GpActionCodeRequest;
use Illuminate\Support\Str;
use Modules\Gp\Entities\Views\VwGpActionCodeWithReport;

class GpActionCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp050000
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validateMe($request, [
            'notinstid' => 'nullable'
        ]);

        if (empty($validate['notinstid'])) {
            $sql = VwGpActionCodeWithReport::where('statusid', '<>', -1);
        } else {
            $sql = VwGpActionCodeWithReport::select('ACTION_CODE', 'name', 'name2')
                ->whereNotIn('ACTION_CODE', function ($query) use ($validate) {
                    $query->select('AC')
                        ->from(with(new GpInstPerm)->getTable())
                        ->where('instid', $validate['notinstid'])
                        ->where('statusid', '!=', -1);
                })
                ->where('statusid', '<>', -1);
        }

        if (auth()->user()->isadmin != 1) {
            $sql = $sql->whereIn('ACTION_CODE', function ($query) use ($validate) {
                $query->select('AC')
                    ->from(with(new GpInstPerm)->getTable())
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', '!=', -1);
            });
        }
        return $this->getGridData(
            $request,
            $sql,
            [['field' => 'ACTION_CODE', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpActionCodeRequest $request)
    {
        if (auth()->user()->isadmin != 1) {
            $this->error('RC000026');
        }
        $validated = $request->validated();
        $validated['statusid'] = 1;
        $validated['ACTION_CODE'] = Str::lower($validated['ACTION_CODE']);
        return GpActionCode::create($validated);
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
        $GPinst = GpActionCode::select('GP_ACTION_CODE.*', 'GP_ACTION_CODE.ACTION_CODE as id')->where('ACTION_CODE', $validate['id'])->where('statusid', '<>', -1)->first();
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
    public function update(GpActionCodeRequest $request)
    {
        if (auth()->user()->isadmin != 1) {
            $this->error('RC000026');
        }
        $validate = $request->validated();
        if (empty($validate['ACTION_CODE'])) {
            $this->error("RC000011");
        }
        GpActionCode::where('ACTION_CODE', $validate['ACTION_CODE'])->where('statusid', '<>', -1)->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        if (auth()->user()->isadmin != 1) {
            $this->error('RC000026');
        }
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        GpActionCode::where('ACTION_CODE', $validate['id'])->where('statusid', '<>', -1)->update([
            'statusid' => -1,
        ]);
    }
}
