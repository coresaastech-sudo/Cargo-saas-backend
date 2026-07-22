<?php

namespace Modules\Ad\Http\Controllers\Eod;

use App\Http\Controllers\Controller;
use Modules\Ad\Entities\AdEodLogDetail;

class CoreController extends Controller
{
    public function getLastEodStep($step)
    {
        $lastitem = AdEodLogDetail::where('eoddate', $step->eoddate)
            ->where('orderno', $step->orderno)
            ->where('instid', auth()->user()->instid)
            ->orderBy('orderno', 'desc')->first();
        return $lastitem;
    }
}
