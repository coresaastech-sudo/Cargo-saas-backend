<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Modules\Ad\Entities\AdEodLog;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Gp\Http\Services\CoreService;

class AdInstEodLogController extends Controller
{
    /**
     * Өдөр өндөрлөлтийн түүх
     *  @AC ad010000
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'eoddate' => 'nullable'
        ]);

        if (!isset($validated['eoddate'])) {
            $ctxndate = CoreService::getEodSysdate(auth()->user()->instid);
            $carbneoddate = Carbon::createFromFormat('Y-m-d', $ctxndate)->subDay();
            $txndate = $carbneoddate->format('Y-m-d');
        } else {
            $txndate = $validated['eoddate'];
        }
        $data = AdEodLog::where('instid', auth()->user()->instid)
            ->where('eoddate', $txndate)->orderBy('stepno', 'asc')->get();
        return $data;
    }

    /**
     * Өндөрлөлтийн логын жагсаалт
     *
     * @return array
     */
    public function show(Request $request)
    {
        $validated = $this->validate($request, [
            'eoddate' => 'required',
            'orderno' => 'required'
        ]);
        $data = AdEodLogDetail::where('instid', auth()->user()->instid)
            ->where('orderno', $validated['orderno'])
            ->where('eoddate', $validated['eoddate'])->orderBy('orderno', 'asc')->get();
        return $data;
    }
}
