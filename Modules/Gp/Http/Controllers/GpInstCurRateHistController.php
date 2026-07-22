<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstCurRateHist;
use Modules\Gp\Http\Requests\GpInstCurRateHistRequest;

class GpInstCurRateHistController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validate($request, [
            'instid' => 'nullable'
        ]);

        return $this->getGridData($request, GpInstCurRateHist::select([
            'typecode',
            'curcode',
            'salerate',
            'buyrate',
            'date',
            'listorder',
            'instid',
        ])->where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid), [['field' => 'listorder', 'dir' => 'ASC']]);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'typecode' => 'required',
            'instid' => 'nullable'
        ], [
            'typecode.required' => "RC000020",
        ]);

        $user = auth()->user();
        if (empty($validated['instid'])) {
            $validated['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validated['instid'] = auth()->user()->instid;
            }
        }

        $GPinst = GpInstCurRateHist::where('instid', auth()->user()->instid)
            ->where('typecode', $validated['typecode'])
            ->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000027");
        }
    }
}
