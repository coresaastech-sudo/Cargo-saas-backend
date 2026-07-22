<?php

namespace Modules\Gl\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Http\Services\GlProcessService;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Http\Services\CoreService;
use Ramsey\Uuid\Type\Decimal;

class GlReportController extends Controller
{


    /**
     * Гүйлгээ тэнцэл.
     * @AC gl040001
     * @return Response
     */
    public function gl040001(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'brchno'           => 'nullable|array',
            'brchno.*.value'   => 'required|string',
            'curcode' => 'nullable',
            'freq' => 'nullable',
            'period' => 'nullable',
            'day' => 'nullable',
            'shownonebal' => 'nullable'
        ], [
            'year.required' => "VC000008"
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $gldate = CoreService::getGlDate($instid);
        $year = $validate['year'] ?? Carbon::parse($gldate)->year;
        $branchCodes = collect($validate['brchno'] ?? [])
            ->pluck('value')
            ->filter()     // хоосон утгуудыг аврах
            ->unique()     // давхардлыг зайлуулах
            ->values()
            ->all();       // эцсийн array

        // Хуучин код нэг утга өгсөн тохиолдолд нийцтэй байдлыг хадгалах
        $brchno = count($branchCodes) ? $branchCodes : null;
        $curcode = $validate['curcode'] ?? null;
        $freq = $validate['freq'] ?? null;
        $period = $validate['period'] ?? 0;
        $day = $validate['day'] ?? 0;
        if ($freq !== 'M') {
            $day = 0;
        }
        if ($period == 0) {
            if ($day == 0) {
                $lastDayOfMonth = Carbon::create($year, Carbon::parse($gldate)->month)->endOfMonth()->day;
                if ($lastDayOfMonth > Carbon::parse($gldate)->day) {
                    $lastDayOfMonth = Carbon::parse($gldate)->day;
                }
                $gldate = $year . '-' . Carbon::parse($gldate)->month . '-' . $lastDayOfMonth;
            }
        } else {
            $strperiod = $period;
            if ($period < 10) {
                $strperiod = '0' . $period;
            }
            if ($day == 0) {
                $lastDayOfMonth = Carbon::create($year, $strperiod)->endOfMonth()->day;
                $tmpgl = Carbon::parse($gldate);
                if ($tmpgl->month == $strperiod) {
                    if ($lastDayOfMonth > Carbon::parse($gldate)->day) {
                        $lastDayOfMonth = Carbon::parse($gldate)->day;
                    }
                }

                $gldate = $year . '-' . $strperiod . '-' . $lastDayOfMonth;
            } else {
                $lastDayOfMonth = Carbon::create($year, $strperiod)->endOfMonth()->day;
                if ($lastDayOfMonth > $day) {
                    $lastDayOfMonth = $day;
                }
                $gldate = $year . '-' . $strperiod . '-' . $lastDayOfMonth;
            }
        }
        $shownonebal = $validate['shownonebal'] ?? 0;
        $data = $service->SelectTransactionBalance($gldate, $year, $curcode, $instid, $brchno, $freq, $period, $day, $shownonebal);
        $tmpdata = [];
        foreach ($data as $key => $item) {
            $item->obal = customTruncate($item->obal, 2);
            $item->debit = customTruncate($item->debit, 2);
            $item->credit = customTruncate($item->credit, 2);
            $item->net = customTruncate($item->net, 2);
            $item->cbal = customTruncate($item->cbal, 2);
            $sum = customTruncate(($item->obal + $item->debit +  $item->credit), 2);
            if ($sum < customTruncate($item->cbal, 2)) {
                $item->debit = $item->debit + ($item->cbal - $sum);
            } else {
                $item->credit = $item->credit + ($item->cbal - $sum);
            }
            $item->net = $item->debit + $item->credit;

            // Log::debug([
            //     'sum' => $item->obal + $item->debit +  $item->credit,
            //     'cbal' => $item->cbal,
            //     'item' => json_decode(json_encode($item))
            // ]);
            $tmpdata[] = $item;
        }
        $data = $tmpdata;
        return $data;
    }

    /**
     * Үлдэгдэл тэнцэл.
     * @AC gl040002
     * @return Response
     */
    public function gl040002(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'brchno'           => 'nullable|array',
            'brchno.*.value'   => 'required|string',
            'period' => 'nullable',
            'day' => 'nullable',
            'shownonebal' => 'nullable'
        ], [
            'year.required' => "VC000008"
        ]);
        $instid = auth()->user()->instid;
        $sumactive = 0;
        $sumpassive = 0;
        $service = new GlProcessService();

        $branchCodes = collect($validate['brchno'] ?? [])
            ->pluck('value')
            ->filter()     // хоосон утгуудыг аврах
            ->unique()     // давхардлыг зайлуулах
            ->values()
            ->all();       // эцсийн array

        // Хуучин код нэг утга өгсөн тохиолдолд нийцтэй байдлыг хадгалах
        $brchnos = count($branchCodes) ? $branchCodes : null;
        $gldate = CoreService::getGlDate($instid);
        $year = $validate['year'] ?? Carbon::parse($gldate)->year;
        $period = $validate['period'] ?? 0;
        $day = $validate['day'] ?? 0;
        if ($period == 0) {
            if ($day == 0) {
                $lastDayOfMonth = Carbon::create($year, Carbon::parse($gldate)->month)->endOfMonth()->day;
                if ($lastDayOfMonth > Carbon::parse($gldate)->day) {
                    $lastDayOfMonth = Carbon::parse($gldate)->day;
                }
                $gldate = $year . '-' . Carbon::parse($gldate)->month . '-' . $lastDayOfMonth;
            }
        } else {
            $strperiod = $period;
            if ($period < 10) {
                $strperiod = '0' . $period;
            }
            if ($day == 0) {
                $lastDayOfMonth = Carbon::create($year, $strperiod)->endOfMonth()->day;
                $tmpgl = Carbon::parse($gldate);
                if ($tmpgl->month == $strperiod) {
                    if ($lastDayOfMonth > Carbon::parse($gldate)->day) {
                        $lastDayOfMonth = Carbon::parse($gldate)->day;
                    }
                }

                $gldate = $year . '-' . $strperiod . '-' . $lastDayOfMonth;
            } else {
                $lastDayOfMonth = Carbon::create($year, $strperiod)->endOfMonth()->day;
                if ($lastDayOfMonth > $day) {
                    $lastDayOfMonth = $day;
                }
                $gldate = $year . '-' . $strperiod . '-' . $lastDayOfMonth;
            }
        }
        $shownonebal = $validate['shownonebal'] ?? 0;
        $isall = false;
        if (empty($brchnos)) {
            $isall = true;
            $brchnos = GPInstBrch::select('brchno')->where('instid', $instid)->where('statusid', 1)->get()->pluck('brchno');
        }
        $tmpdatas = [];
        $allsum = 0;
        foreach ($brchnos as $brchno) {
            $data = $service->SelectBalance($gldate, $year, $instid, $brchno, $period, $day, $shownonebal);
            foreach ($data as $key => $item) {
                $item->currrate = customTruncate($item->currrate, 2);
                $item->cbal = customTruncate($item->cbal, 2);
                $item->value = customTruncate($item->value, 2);
                $item->bvalue = $item->value;
                if ($isall) {
                    $item->branchname = 'ALL';
                    $item->branch = 'ALL';
                }
                if ($item->type == '1' || $item->type == '5') {
                    $sumactive =  $sumactive  + $item->value;
                } else if (
                    $item->type == '2' || $item->type == '3' || $item->type == '4'
                ) {
                    $sumpassive =  $sumpassive  + $item->value;
                }
                $isfound = false;
                foreach ($tmpdatas as $keytmp => $tmpdata) {
                    if (
                        $tmpdata->account == $item->account && $tmpdata->class == $item->class
                        && $tmpdata->currency == $item->currency && $tmpdata->type == $item->type
                    ) {
                        $isfound = true;
                        $tmpdatas[$keytmp]->bvalue = $tmpdatas[$keytmp]->bvalue + $item->bvalue;
                        $tmpdatas[$keytmp]->cbal = $tmpdatas[$keytmp]->cbal + $item->cbal;
                        $tmpdatas[$keytmp]->value = $tmpdatas[$keytmp]->value + $item->value;
                        break;
                    }
                }
                if (!$isfound) {
                    $tmpdatas[] = $item;
                }
                $allsum = $allsum + $item->bvalue;
            }
        }

        $sumactive = round($sumactive, 2);
        $sumpassive = round($sumpassive, 2);

        return ['data' => $tmpdatas, 'active' => $sumactive, 'passive' => $sumpassive];
    }

    /**
     * Intraday readonly balance.
     * @AC gl040003
     * @return Response
     */
    public function gl040003(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'brchno'           => 'nullable|array',
            'brchno.*.value'   => 'required|string',
            'period' => 'nullable',
            'day' => 'nullable',
            'shownonebal' => 'nullable'
        ], [
            'year.required' => "VC000008"
        ]);

        $instid = auth()->user()->instid;
        $service = new GlProcessService();
        $sysdate = CoreService::getTxnDate($instid);
        $year = $validate['year'] ?? Carbon::parse($sysdate)->year;
        $period = $validate['period'] ?? Carbon::parse($sysdate)->month;
        $day = $validate['day'] ?? Carbon::parse($sysdate)->day;

        if (empty($period)) {
            $period = Carbon::parse($sysdate)->month;
        }
        if (empty($day)) {
            $day = Carbon::parse($sysdate)->day;
        }

        $strperiod = $period < 10 ? '0' . $period : $period;
        $lastDayOfMonth = Carbon::create($year, $strperiod)->endOfMonth()->day;
        if ($lastDayOfMonth > $day) {
            $lastDayOfMonth = $day;
        }
        $reportdate = $year . '-' . $strperiod . '-' . $lastDayOfMonth;

        $branchCodes = collect($validate['brchno'] ?? [])
            ->pluck('value')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $brchnos = count($branchCodes) ? $branchCodes : null;

        $basecur = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$basecur || empty($basecur->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' base currency '
            ]);
        }

        $spotacnt = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$spotacnt || empty($spotacnt->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' spot account '
            ]);
        }

        $suspacnt = GPInstGp::where('instid', $instid)->where('itemname', 'SUSPAccount')->first();
        if (!$suspacnt || empty($suspacnt->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' suspense account '
            ]);
        }

        $shownonebal = $validate['shownonebal'] ?? 0;
        $data = $service->SelectIntradayBalance(
            $reportdate,
            $year,
            $instid,
            $brchnos,
            $period,
            $day,
            $shownonebal,
            $basecur->itemvalue,
            $spotacnt->itemvalue,
            $suspacnt->itemvalue
        );

        $sumactive = 0;
        $sumpassive = 0;
        $tmpdatas = [];
        foreach ($data as $item) {
            $item->currrate = customTruncate($item->currrate, 2);
            $item->cbal = customTruncate($item->cbal, 2);
            $item->value = customTruncate($item->value, 2);
            $item->bvalue = $item->value;

            if ($item->type == '1' || $item->type == '5') {
                $sumactive = $sumactive + $item->value;
            } else if ($item->type == '2' || $item->type == '3' || $item->type == '4') {
                $sumpassive = $sumpassive + $item->value;
            }

            $tmpdatas[] = $item;
        }

        return [
            'data' => $tmpdatas,
            'active' => round($sumactive, 2),
            'passive' => round($sumpassive, 2)
        ];
    }
}
