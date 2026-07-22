<?php

namespace Modules\Gl\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Controllers\AdInstEodController;
use Modules\Gl\Entities\GlBalance;
use Modules\Gl\Entities\GlBalanceHist;
use Modules\Gl\Entities\GlDailyBal;
use Modules\Gl\Entities\GlDailyBalHist;
use Modules\Gl\Entities\GlTransaction;
use Modules\Gl\Http\Services\GlProcessService;
use Modules\Gl\Http\Services\GlTxnService;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Gp\Entities\GPInstCur;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstSeq;
use Modules\Gp\Entities\GPInstUserRole;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\FinTxnEntity;
use Modules\Tr\Entities\TrGlretailBal;
use Modules\Tr\Entities\TrGlretailEntry;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;

class GlProcessController extends Controller
{

    /**
     * Өдөр шилжүүлэхэд шалгах шалгалт.
     * @AC gl020000
     * @return array
     */
    public function gl020000(Request $request)
    {
        $validate = $this->validate($request, [
            'gldate' => 'required',
        ], [
            'gldate.required' => "VC000008",
        ]);

        $instid = auth()->user()->instid;
        $service = new GlProcessService();
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SUSPAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' балансжуулах дансны дугаар '
            ]);
        }

        $suspacnt = $gp->itemvalue;

        $checkSuspAcntData = $service->SelectSuspccount($gldate, $instid, $suspacnt);
        $checkSuspAcntData = array_map(function ($item) {
            $item->balance = (float) $item->balance;
            return $item;
        }, $checkSuspAcntData);
        return $checkSuspAcntData;
    }

    /**
     * Өдөр шилжүүлэх.
     * @AC gl020001
     */
    public function gl020001(Request $request)
    {
        $validate = $this->validate($request, [
            'gldate' => 'required',
            'nextgldate' => 'required',
        ], [
            'gldate.required' => "VC000008",
            'nextgldate.required' => "VC000008"
        ]);

        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $gldate = CoreService::getGlDate($instid);
        $nextdate = Carbon::parse(CoreService::getGlDate($instid))->addDay()->format('Y-m-d');

        if ($gldate == $validate['gldate'] && $nextdate == $validate['nextgldate']) {
            $seq = GPInstSeq::where('instid', $instid)->where('seqid', 'GLDATE')->first();
            try {
                if (!empty($seq)) {
                    DB::beginTransaction();
                    $year = Carbon::parse($gldate)->year;
                    $period = Carbon::parse($gldate)->month;
                    //GL Daily Bal Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах
                    GlDailyBalHist::where('instid', $instid)->where('year', $year)->where('period', $period)->delete();
                    //Gl Daily Bal-н мэдээлэл авах
                    $datas = GlDailyBal::where('instid', $instid)->where('year', $year)->where('period', $period)->get();
                    $dataArray = $datas->toArray();
                    $batchSize = 200;
                    // Split the data into smaller chunks and insert each batch
                    foreach (array_chunk($dataArray, $batchSize) as $chunk) {
                        GlDailyBalHist::insert($chunk);
                    }

                    //GL Balance Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах
                    GlBalanceHist::where('instid', $instid)->where('year', $year)->delete();
                    //Gl Balance-н мэдээлэл авах
                    $datas = GlBalance::where('instid', $instid)->where('year', $year)->get();
                    $dataArray = $datas->toArray();
                    $batchSize = 200;
                    // Split the data into smaller chunks and insert each batch
                    foreach (array_chunk($dataArray, $batchSize) as $chunk) {
                        GlBalanceHist::insert($chunk);
                    }

                    if ((new Carbon($nextdate))->year == $year + 1) {
                        $this->setFirstBalYear();
                    }

                    if ((new Carbon($nextdate))->month != $period) {
                        $this->setFirstBalMonth();
                    }

                    $seq->seqno = $nextdate;
                    $seq->updated_by = $userid;
                    $seq->save();
                    DB::commit();
                } else {
                    $this->error('RC000167');
                }

                try {
                    event(new \Modules\Gl\Events\GlDateEvent(CoreService::getGlDate($instid), auth()->user()));
                } catch (Exception $ex) {
                    Log::channel('eod_log')->debug($ex);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        } else {
            $this->error('RC000166');
        }
    }

    /**
     * Оны эхний үлдэгдэл суулгах
     *
     * @return void
     */
    public function setFirstBalYear()
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $gldate = CoreService::getGlDate($instid);
        $year = Carbon::parse($gldate)->year;
        $glacnts = GlBalance::select(
            DB::raw('trunc(COALESCE(obal, 0), 2) +
            trunc(COALESCE(dt01, 0), 2) + trunc(COALESCE(ct01, 0), 2) +
            trunc(COALESCE(dt02, 0), 2) + trunc(COALESCE(ct02, 0), 2) +
            trunc(COALESCE(dt03, 0), 2) + trunc(COALESCE(ct03, 0), 2) +
            trunc(COALESCE(dt04, 0), 2) + trunc(COALESCE(ct04, 0), 2) +
            trunc(COALESCE(dt05, 0), 2) + trunc(COALESCE(ct05, 0), 2) +
            trunc(COALESCE(dt06, 0), 2) + trunc(COALESCE(ct06, 0), 2) +
            trunc(COALESCE(dt07, 0), 2) + trunc(COALESCE(ct07, 0), 2) +
            trunc(COALESCE(dt08, 0), 2) + trunc(COALESCE(ct08, 0), 2) +
            trunc(COALESCE(dt09, 0), 2) + trunc(COALESCE(ct09, 0), 2) +
            trunc(COALESCE(dt10, 0), 2) + trunc(COALESCE(ct10, 0), 2) +
            trunc(COALESCE(dt11, 0), 2) + trunc(COALESCE(ct11, 0), 2) +
            trunc(COALESCE(dt12, 0), 2) + trunc(COALESCE(ct12, 0), 2) +
            trunc(COALESCE(dt13, 0), 2) + trunc(COALESCE(ct13, 0), 2) AS totalsum'),
            'account',
            'branch',
            'currency',
        )
            ->where('unit', '0000')
            ->where('year', $year)
            ->where('instid', $instid)
            ->whereRaw('(account, currency, branch, instid) NOT IN (
                SELECT account, currency, branch, instid
                FROM ' . with(new GlBalance)->getTable() . '
                WHERE year = ? AND instid = ?
            )', [$year + 1, $instid])->get();

        $jrno = "BB" . CoreService::getGlNextJrno();
        $jritem1 = new TxnItemEntity();
        $nextdate = Carbon::parse($gldate)->addDay()->format('Y-m-d');
        foreach ($glacnts as $key => $glacnt) {
            GlBalance::create([
                'account' => $glacnt->account,
                'branch' => $glacnt->branch,
                'unit' => '0000',
                'currency' => $glacnt->currency,
                'year' => $year + 1,
                'instid' => $instid,
                'obal' => empty($glacnt) ? 0 : customTruncate($glacnt->totalsum, 2),
                'updated_by' => $userid,
                'created_by' => $userid,
            ]);
            if (!empty($glacnt) && customTruncate($glacnt->totalsum, 2) != 0) {
                $p1 = new FinTxnEntity();
                $p1->setJrno($jrno);
                $p1->setTxndate($nextdate);
                $p1->setAcntbrchno($glacnt->branch);
                $p1->setCurCode($glacnt->currency);
                $p1->setTxnAcntCode($glacnt->account);
                $p1->setTxnAmount(customTruncate($glacnt->totalsum, 2));
                $p1->setTxnDesc('Оны эхлэл үлдэгдэл суулгав');
                $p1->setCorr(0);
                $p1->setPostdate(Carbon::now());
                $p1->setInstid($instid);
                $service = new GlTxnService();
                $service->insertGlTxnDb($p1, $jritem1);
            }
        }
    }

    /**
     * Сарын эхний үлдэгдэл суулгах
     *
     * @return void
     */
    public function setFirstBalMonth()
    {
        $sumfield = 'trunc(obal, 2) ';
        for ($i = 0; $i < 31; $i++) {
            $monthind = $i + 1;
            if ($monthind < 10) {
                $monthind = '0' . $monthind;
            }
            $sumfield = $sumfield . " + trunc(COALESCE (dt$monthind, 0), 2) + trunc(COALESCE (ct$monthind, 0), 2) ";
        }
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $gldate = CoreService::getGlDate($instid);
        $year = Carbon::parse($gldate)->year;
        $period = Carbon::parse($gldate)->month;

        $nextdate = Carbon::parse($gldate)->addDay();

        $glacnts = GlDailyBal::select(
            DB::raw($sumfield . ' AS totalsum'),
            'account',
            'branch',
            'currency',
        )
            ->where('year', $year)
            ->where('period', $period)
            ->where('instid', $instid)
            ->whereRaw('(account, currency, branch, instid) NOT IN (
                SELECT account, currency, branch, instid
                FROM ' . with(new GlDailyBal)->getTable() . '
                WHERE year = ? AND instid = ? AND period = ?
            )', [$nextdate->year, $instid, $nextdate->month])->get();

        foreach ($glacnts as $key => $glacnt) {
            GlDailyBal::create([
                'account' => $glacnt->account,
                'branch' => $glacnt->branch,
                'unit' => '0000',
                'currency' => $glacnt->currency,
                'year' => $nextdate->year,
                'period' => $nextdate->month,
                'instid' => $instid,
                'obal' => empty($glacnt->totalsum) ? 0 : customTruncate($glacnt->totalsum, 2),
                'updated_by' => $userid,
                'created_by' => $userid,
            ]);
        }
    }

    /**
     * Өдөр Ухраах.
     * @AC gl020002
     * @return Response
     */
    public function gl020002(Request $request)
    {
        $validate = $this->validate($request, [
            'gldate' => 'required',
            'nextgldate' => 'required',
        ], [
            'gldate.required' => "VC000008",
            'nextgldate.required' => "VC000008"
        ]);

        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $gldate = CoreService::getGlDate($instid);
        $nextdate = Carbon::parse(CoreService::getGlDate($instid))->subDay()->format('Y-m-d');

        if ($gldate == $validate['gldate'] && $nextdate == $validate['nextgldate']) {
            $seq = GPInstSeq::where('instid', $instid)->where('seqid', 'GLDATE')->first();
            try {
                DB::beginTransaction();
                if (!empty($seq)) {
                    $seq->seqno = $nextdate;
                    $seq->updated_by = $userid;
                    $seq->save();
                } else {
                    $this->error('RC000167');
                }
                DB::commit();

                try {
                    event(new \Modules\Gl\Events\GlDateEvent(CoreService::getGlDate($instid), auth()->user()));
                } catch (Exception $ex) {
                    Log::channel('eod_log')->debug($ex);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        } else {
            $this->error('RC000166');
        }
    }

    /**
     * Ханшын тэгшитгэл харах.
     * @AC gl021000
     * @return Response
     */
    public function gl021000(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable'
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'AutoProcPSToBranch')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот позиц хүлээн авах салбарын дугаар '
            ]);
        }
        $recbrchno = $gp->itemvalue;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;
        try {
            DB::beginTransaction();
            $newAcntData = $service->CreateCurRateNewAccount($gldate, $instid, $userid, $recbrchno, $basecur);
            $newAcntData = array_map(function ($item) {
                return (array) $item;
            }, $newAcntData->toArray());
            if (count($newAcntData) > 0) {
                GlDailyBal::insert($newAcntData);
                $tmpnewAcntData = array_map(function ($item) {
                    return [
                        'branch' => $item['branch'],
                        'unit' => $item['unit'],
                        'account' => $item['account'],
                        'currency' => $item['currency'],
                        'year' => $item['year'],
                        'obal' => customTruncate($item['obal'], 2),
                        'instid' => $item['instid'],
                        'created_by' => $item['created_by'],
                        'updated_by' => $item['updated_by'],
                        'created_at' => $item['created_at'],
                    ];
                }, $newAcntData);
                GlBalance::insert($tmpnewAcntData);
            }
            $data = $service->SelectRateEqualization($gldate, $brchno, $instid, $basecur, $spotacnt);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        $data = array_map(function ($item) {
            $item->currrate = (float) $item->currrate;
            $item->difference = (float) $item->difference;
            $item->equiv = (float) $item->equiv;
            $item->spot = (float) $item->spot;
            $item->value = (float) $item->value;
            return $item;
        }, $data);
        return $data;
    }

    /**
     * Гүйлгээ татахад харах.
     * @AC gl022000
     * @return Response
     */
    public function gl022000(Request $request)
    {
        $validate = $this->validate($request, [
            'gldate' => 'nullable',
            'suspacnt' => 'nullable',
            'again' => 'nullable'
        ]);
        $instid = auth()->user()->instid;
        $service = new GlProcessService();
        $brchno = $validate['again'] ?? 0;
        $gldate = $validate['gldate'] ?? CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }
        $basecur = $gp->itemvalue;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SUSPAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' түр дансны дугаар '
            ]);
        }
        $suspacnt = $gp->itemvalue;
        $isuspacnt = $validate['suspacnt'] ?? $suspacnt;

        $data = $service->SelectPullTxn($gldate, $brchno, $instid, $basecur, $spotacnt, $isuspacnt);
        $data = array_map(function ($item) {
            $item->amount = (float) $item->amount;
            return $item;
        }, $data);
        return $data;
    }

    /**
     * Үлдэгдэл тулгаж харах.
     * @AC gl023000
     * @return Response
     */
    public function gl023000(Request $request)
    {
        $validate = $this->validate($request, [
            'txndate' => 'required',
            'brchno' => 'nullable|array',
            'brchno.*.value' => 'required|string',
            'detail' => 'nullable',
            'shownonebal' => 'nullable'
        ], [
            'txndate.required' => "VC000008"
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();

        $branchCodes = collect($validate['brchno'] ?? [])
            ->pluck('value')
            ->filter()     // хоосон утгуудыг аврах
            ->unique()     // давхардлыг зайлуулах
            ->values()
            ->all();       // эцсийн array

        // Хуучин код нэг утга өгсөн тохиолдолд нийцтэй байдлыг хадгалах
        $brchno = count($branchCodes) ? $branchCodes : null;
        $detail = $validate['detail'] ?? 0;
        $shownonebal = $validate['shownonebal'] ?? 0;
        $gldate = $validate['txndate'] ?? CoreService::getGlDate($instid);
        if ($detail && $shownonebal) {
            $data = $service->SelectBalanceCompareDetail($gldate, $instid, $brchno);
            $data = array_map(function ($item) {
                $item->diffbal = subNumber($item->retailbal, $item->glbal);
                $item->glbal = (float) $item->glbal;
                $item->retailbal = (float) $item->retailbal;
                $item->calbal = (float) $item->calbal;
                return $item;
            }, $data);
        } else if ($detail && !$shownonebal) {
            $data = $service->SelectBalanceCompareDetail($gldate, $instid, $brchno);
            $tmpdata = [];
            foreach ($data as $key => $item) {
                $item->diffbal = subNumber($item->retailbal, $item->glbal);
                $item->glbal = (float) $item->glbal;
                $item->retailbal = (float) $item->retailbal;
                $item->calbal = (float) $item->calbal;
                if (round($item->glbal, 2) != 0 || round($item->retailbal, 2) != 0) {
                    $tmpdata[] = $item;
                }
            }
            $data = $tmpdata;
        } else if (!$detail && $shownonebal) {
            $data = $service->SelectBalanceCompareSum($gldate, $instid, $brchno);
            $data = array_map(function ($item) {
                $item->diffbal = subNumber($item->retailbal, $item->glbal);
                $item->glbal = (float) $item->glbal;
                $item->retailbal = (float) $item->retailbal;
                $item->calbal = (float) $item->calbal;
                return $item;
            }, $data);
        } else if (!$detail && !$shownonebal) {
            $data = $service->SelectBalanceCompareSum($gldate, $instid, $brchno);
            $tmpdata = [];
            foreach ($data as $key => $item) {
                $item->diffbal = subNumber($item->retailbal, $item->glbal);
                $item->glbal = (float) $item->glbal;
                $item->retailbal = (float) $item->retailbal;
                $item->calbal = (float) $item->calbal;
                if (round($item->glbal, 2) != 0 || round($item->retailbal, 2) != 0) {
                    $tmpdata[] = $item;
                }
            }
            $data = $tmpdata;
        }

        return $data;
    }

    /**
     *ЕД Орлого зарлага суурь валютруу хөрвүүлэх харах.
     * @AC gl024000
     * @return Response
     */
    public function gl024000(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable',
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }
        $basecur = $gp->itemvalue;
        $data = $service->SelectInExBalConvertBaseCur($gldate, $instid, $brchno, $basecur);
        $data = array_map(function ($item) {
            $item->balance = (float) $item->balance;
            $item->currrate = (float) $item->currrate;
            return $item;
        }, $data);
        return $data;
    }

    /**
     * Арилжаа хаах харах.
     * @AC gl025000
     * @return Response
     */
    public function gl025000(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable'
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;
        $data = $service->SelectRateEqualization($gldate, $brchno, $instid, $basecur, $spotacnt);
        $data = array_map(function ($item) {
            $item->currrate = (float) $item->currrate;
            $item->difference = (float) $item->difference;
            $item->equiv = (float) $item->equiv;
            $item->spot = (float) $item->spot;
            $item->value = (float) $item->value;
            return $item;
        }, $data);
        return $data;
    }
    /**
     * Позиц хаах харах.
     * @AC gl026000
     * @return Response
     */
    public function gl026000(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable'
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;
        $data = $service->SelectRateEqualization($gldate, $brchno, $instid, $basecur, $spotacnt);
        $data = array_map(function ($item) {
            $item->currrate = (float) $item->currrate;
            $item->difference = (float) $item->difference;
            $item->equiv = (float) $item->equiv;
            $item->spot = (float) $item->spot;
            $item->value = (float) $item->value;
            return $item;
        }, $data);
        return $data;
    }

    /**
     * Ханш тэгшитгэх
     * gl021200
     */

    public function gl021200(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable',
            'recbrchno' => 'nullable',
        ]);

        $instid = auth()->user()->instid;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $recbrchno = $validate['recbrchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;

        if (!empty($recbrchno)) {
            $gp = GPInstGp::where('instid', $instid)->where('itemname', 'IBAccount')->first();
            if (!$gp || empty($gp->itemvalue)) {
                $this->error('RC000162', [
                    'field' => ' салбар хоорондын тооцооны дансны дугаар '
                ]);
            }
            $ibacnt = $gp->itemvalue;
        }

        $txnservice = new GlTxnService();
        $jrno = "";
        $jrItem = new TxnItemEntity();
        $txndesc = 'Ханшийн тэгшитгэл хийв.';

        if (empty($brchno)) {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('statusid', 1)
                ->get();
        } else {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('brchno', $brchno)
                ->where('statusid', 1)
                ->get();
        }

        $equivacct = GPInstCur::select('equivacct')
            ->where('curcode', $basecur)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->first();
        if (empty($equivacct) || empty($equivacct->equivacct)) {
            $this->error('RC000164');
        }
        $txncount = 0;
        foreach ($branches as $key => $branch) {
            $data = $service->getRateEqualization($gldate, $branch->brchno, $instid, $basecur, $spotacnt);
            // Log::debug($data);
            foreach ($data as $key => $value) {
                try {

                    $txnamout = $value->difference * (-1);
                    DB::beginTransaction();
                    if (round($txnamout, 2) != 0) {
                        if (empty($jrno)) {
                            $jrno = "RE" . CoreService::getGlNextJrno();
                        }
                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($spotacnt);
                        $p->setTxnAmount($txnamout);
                        $p->setRate(null);
                        $p->setCurCode($basecur);
                        $p->setContCurCode($basecur);
                        $p->setTxnDesc($txndesc);
                        $p->setAcntbrchno($value->brchno);
                        $p->setTxncode('gl021200');
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        $txnservice->doTxn($p, $jrItem);

                        if (empty($recbrchno) || $value->brchno == $recbrchno) {
                            $p1 = clone $p;
                            if (($value->difference * 1) > 0) {
                                $p1->setTxnAcntCode($value->loss);
                            } else {
                                $p1->setTxnAcntCode($value->prof);
                            }
                            $p1->setTxnAmount($txnamout * (-1));
                            $txnservice->doTxn($p1, $jrItem);
                        } else {
                            $p1 = clone $p;
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setTxnAmount($txnamout * (-1));
                            $txnservice->doTxn($p1, $jrItem);
                        }

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($equivacct->equivacct);
                        $p1->setTxnAmount($txnamout);
                        $txnservice->doTxn($p1, $jrItem);

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($value->equivacct);
                        $p1->setTxnAmount($txnamout * (-1));
                        $txnservice->doTxn($p1, $jrItem);

                        if (!empty($recbrchno) && $value->brchno != $recbrchno) {
                            $p1 = clone $p;
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAmount($txnamout);
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            if (($value->difference * 1) > 0) {
                                $p1->setTxnAcntCode($value->loss);
                            } else {
                                $p1->setTxnAcntCode($value->prof);
                            }
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAmount($txnamout * (-1));
                            $txnservice->doTxn($p1, $jrItem);
                        }

                        $txncount++;
                    }
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
        }

        return [
            'txnJrno' => $jrno,
            'txncount' => $txncount
        ];
    }

    /**
     * Гүйлгээ татах
     * gl022200
     */

    private function autoRateEqualizationAfterPull(GlProcessService $service, $gldate, array $brchnos, $instid, $basecur, $spotacnt)
    {
        $brchnos = array_values(array_unique(array_filter($brchnos)));
        if (count($brchnos) === 0) {
            return [
                'txnJrno' => '',
                'txncount' => 0
            ];
        }

        $txnservice = new GlTxnService();
        $jrno = "";
        $jrItem = new TxnItemEntity();
        $txndesc = 'Auto rate equalization after transaction pull.';

        $equivacct = GPInstCur::select('equivacct')
            ->where('curcode', $basecur)
            ->where('instid', $instid)
            ->where('statusid', 1)
            ->first();
        if (empty($equivacct) || empty($equivacct->equivacct)) {
            $this->error('RC000164');
        }

        $txncount = 0;
        foreach ($brchnos as $brchno) {
            $data = $service->getRateEqualization($gldate, $brchno, $instid, $basecur, $spotacnt);
            foreach ($data as $value) {
                try {
                    $txnamout = $value->difference * (-1);
                    DB::beginTransaction();
                    if (round($txnamout, 2) != 0) {
                        if (empty($jrno)) {
                            $jrno = "RE" . CoreService::getGlNextJrno();
                        }

                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($spotacnt);
                        $p->setTxnAmount($txnamout);
                        $p->setRate(null);
                        $p->setCurCode($basecur);
                        $p->setContCurCode($basecur);
                        $p->setTxnDesc($txndesc);
                        $p->setAcntbrchno($value->brchno);
                        $p->setTxncode('gl021200');
                        $p->setTxndate($gldate);
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        $txnservice->doTxn($p, $jrItem);

                        $p1 = clone $p;
                        if (($value->difference * 1) > 0) {
                            $p1->setTxnAcntCode($value->loss);
                        } else {
                            $p1->setTxnAcntCode($value->prof);
                        }
                        $p1->setTxnAmount($txnamout * (-1));
                        $txnservice->doTxn($p1, $jrItem);

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($equivacct->equivacct);
                        $p1->setTxnAmount($txnamout);
                        $txnservice->doTxn($p1, $jrItem);

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($value->equivacct);
                        $p1->setTxnAmount($txnamout * (-1));
                        $txnservice->doTxn($p1, $jrItem);

                        $txncount++;
                    }
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
        }

        return [
            'txnJrno' => $jrno,
            'txncount' => $txncount
        ];
    }

    public function gl022200(Request $request)
    {
        $validate = $this->validate($request, [
            'gldate' => 'nullable',
            'suspacnt' => 'nullable',
            'again' => 'nullable'
        ]);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        if ((new AdInstEodController())->isOnEodJob()) {
            $this->error('RC000108');
        }
        $service = new GlProcessService();
        $again = $validate['again'] ?? 0;
        $gldate = $validate['gldate'] ?? CoreService::getGlDate($instid);
        if ($gldate > CoreService::getGlDate($instid)) {
            $this->error('RC000219', [
                'gldate' => CoreService::getGlDate($instid),
                'inputdate' => $gldate
            ]);
        }
        //Гүйлгээ татаж дуусаагүй үед дахин татах хүсэлт орж ирж буйг шалгах
        $pullgltx = GPInstSeq::where('seqid', 'PULLGLTXISON')
            ->where('instid', $instid)->first();
        if (empty($pullgltx)) {
            $this->error('RC000162', [
                'field' => ' Гүйлгээ татаж буйг шалгах түлхүүр '
            ]);
        }
        if ($pullgltx->seqno == '1' || $pullgltx->seqno == 1) {
            $this->error('RC000196');
        }
        //Гүйлгээ татаж буй төлөвт шилжүүлэх
        GPInstSeq::where('seqid', 'PULLGLTXISON')
            ->where('instid', $instid)->update([
                'seqno' => 1,
                'updated_by' => $userid
            ]);

        try {
            //Өдөр алгасаж гүйлгээ татаж буйг шалгах
            $glyestodaydate = $validate['gldate'] ?? CoreService::getGlDate($instid);
            $glyestodaydate = Carbon::parse($glyestodaydate)->subDay()->format('Y-m-d');
            $checkyestoday = GlTransaction::where('instid', $instid)
                ->where('txndate', $glyestodaydate)
                ->where('journal', 'like', 'CS%')->first();
            $checkisnewinst = GlTransaction::where('instid', $instid)
                ->where('journal', 'like', 'CS%')->first();
            if (empty($checkyestoday) && !empty($checkisnewinst)) {
                $this->error('RC000112', [
                    'txndate' => 'өмнөх ' . $glyestodaydate . ' өдрийн ',
                    'jrno' => 'CS00.... '
                ]);
            }

            $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
            if (!$gp || empty($gp->itemvalue)) {
                $this->error('RC000162', [
                    'field' => ' суурь валют '
                ]);
            }
            $basecur = $gp->itemvalue;

            $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
            if (!$gp || empty($gp->itemvalue)) {
                $this->error('RC000162', [
                    'field' => ' спот хөтлөгдөх дансны дугаар '
                ]);
            }
            $spotacnt = $gp->itemvalue;
            $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SUSPAccount')->first();
            if (!$gp || empty($gp->itemvalue)) {
                $this->error('RC000162', [
                    'field' => ' түр дансны дугаар '
                ]);
            }
            $suspacnt = $gp->itemvalue;
            $isuspacnt = $validate['suspacnt'] ?? $suspacnt;

            $hasRateEqualization = GlTransaction::where('instid', $instid)
                ->where('txndate', $gldate)
                ->where('journal', 'like', 'RE%')
                ->where('statusid', 1)
                ->exists();

            $txnservice = new GlTxnService();
            $jrno = "";
            $jrItem = new TxnItemEntity();
            $txndesc = 'Суурь системээс гүйлгээ татаж хийв.';

            $txncount = 0;
            $pulledBrchnos = [];
            $data = $service->SelectPullTxn($gldate, $again, $instid, $basecur, $spotacnt, $isuspacnt);
            $pullGlTxnMod = GPInstGp::where('instid', $instid)->where('itemname', 'pullGlTxnMod')->first();
            if ($pullGlTxnMod && $pullGlTxnMod->itemvalue != 0) {
                foreach ($data as $key => $value) {
                    if ($pullGlTxnMod->itemvalue == 1) {
                        if ($value->error == 1) {
                            $this->error('RC000133', [
                                'acntno' => $value->gl
                            ]);
                        }
                    } else if ($pullGlTxnMod->itemvalue == 2) {
                        if ($value->error == 1) {
                            $this->error('RC000133', [
                                'acntno' => $value->gl
                            ]);
                        } else if ($value->error == 2) {
                            $this->error('Балансжуулах дансанд гүйлгээ хийхийг зөвшөөрөхгүй байна.');
                        }
                    }
                }
            }
            foreach ($data as $key => $value) {
                try {
                    $txnamout = $value->amount * 1;
                    DB::beginTransaction();
                    if (round($txnamout, 2) != 0) {
                        if (empty($jrno)) {
                            $jrno = "CS" . CoreService::getGlNextJrno();
                        }
                        $p = new TxnJrnlEntity();
                        if ($value->error == 1 || $value->error == '1') {
                            $p->setTxnAcntCode($isuspacnt);
                        } else {
                            $p->setTxnAcntCode($value->gl);
                        }

                        $p->setTxnAmount($txnamout);
                        $p->setRate(null);
                        $p->setCurCode($value->curcode);
                        $p->setContCurCode($value->curcode);
                        $p->setTxnDesc($txndesc);
                        $p->setAcntbrchno($value->acntbrchno);
                        $p->setTxncode('gl022200');
                        $p->setTxndate($gldate);
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        $txnservice->doTxn($p, $jrItem);
                        $txncount++;
                        $pulledBrchnos[] = $value->acntbrchno;
                    }
                    TrGlretailEntry::where('txndate', $gldate)
                        ->whereRaw("gl || TRIM(COALESCE(segcode, '00')) = '" . $value->gl . "'")
                        ->where('curcode', $value->curcode)
                        ->where('acntbrchno', $value->acntbrchno)
                        ->whereIn('corr', [0, 2])
                        ->where('instid', $instid)->update([
                            'flags' => 1
                        ]);
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
            $autoRateEqualization = [
                'txnJrno' => '',
                'txncount' => 0
            ];
            if ($hasRateEqualization && $txncount > 0) {
                $autoRateEqualization = $this->autoRateEqualizationAfterPull(
                    $service,
                    $gldate,
                    $pulledBrchnos,
                    $instid,
                    $basecur,
                    $spotacnt
                );
            }

            $balancefield = "";
            for ($i = 0; $i < 13; $i++) {
                $month = $i + 1;
                if ($month < 10) {
                    $month = '0' . $month;
                }
                if (empty($balancefield)) {
                    $balancefield = "coalesce (dt$month, 0) + coalesce (ct$month, 0)";
                } else {
                    $balancefield = $balancefield . " + coalesce (dt$month, 0) + coalesce (ct$month, 0)";
                }
            }

            $glbals = GlBalance::select(
                'account',
                'branch',
                'currency',
                DB::raw(
                    "sum(coalesce (obal, 0) + $balancefield) as sumbalance"
                )
            )
                ->where('year', (new Carbon($gldate))->year)
                ->where('instid', $instid)
                ->groupBy('branch')
                ->groupBy('account')
                ->groupBy('currency');
            // Log::debug($glbals->toSql());
            $glbals = $glbals->get();
            foreach ($glbals as $key => $glbal) {
                TrGlretailBal::where('date', $gldate)
                    ->where('glsegcode', $glbal->account)
                    ->where('curcode', $glbal->currency)
                    ->where('brchno', $glbal->branch)
                    ->where('statusid', 1)
                    ->where('instid', $instid)->update([
                        'glbal' => $glbal->sumbalance,
                        'updated_by' => $userid
                    ]);
            }
            return [
                'txnJrno' => $jrno,
                'txncount' => $txncount,
                'autoRateEqualizationJrno' => $autoRateEqualization['txnJrno'],
                'autoRateEqualizationTxncount' => $autoRateEqualization['txncount']
            ];
        } catch (\Throwable $th) {
            throw $th;
        } finally {
            //Гүйлгээ татаж дууссан төлөвт шилжүүлэх
            GPInstSeq::where('seqid', 'PULLGLTXISON')
                ->where('instid', $instid)->update([
                    'seqno' => 0,
                    'updated_by' => $userid
                ]);
        }
    }

    /**
     * ЕД Орлого зарлага суурь валютруу хөрвүүлэх
     * gl024200
     */

    public function gl024200(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable',
        ]);

        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;
        $txnservice = new GlTxnService();
        $jrno = "";
        $jrItem = new TxnItemEntity();
        $txndesc = 'Орлого зарлагыг суурь валют руу хөрвүүлэв.';

        if (empty($brchno)) {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('statusid', 1)
                ->get();
        } else {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('brchno', $brchno)
                ->where('statusid', 1)
                ->get();
        }


        $txncount = 0;
        foreach ($branches as $key => $branch) {
            $data = $service->SelectInExBalConvertBaseCur($gldate, $instid, $branch->brchno, $basecur);
            $tmpcurcode = "";
            $equowncuramt = 0;
            $equbasecuramt = 0;
            $equbrbasecuramt = 0;
            foreach ($data as $key => $value) {
                if (round($value->balance, 2) != 0) {
                    try {
                        DB::beginTransaction();
                        if (empty($jrno)) {
                            $jrno = "CB" . CoreService::getGlNextJrno();
                        }
                        $p = new TxnJrnlEntity();
                        $p->setAcntbrchno($value->branch);
                        $p->setCurCode($value->currency);
                        $p->setContCurCode($value->currency);
                        $p->setRate(null);
                        $p->setMainAcntPosition('PUSH');
                        $p->setTxncode('gl024200');
                        $p->setTxnDesc($txndesc);
                        $p->setJrno($jrno);

                        if ($tmpcurcode != $value->currency && round($equowncuramt, 2) != 0) {
                            $p1 = clone $p;
                            $p1->setTxnAcntCode($spotacnt);
                            $p1->setCurCode($tmpcurcode);
                            $p1->setTxnAmount($equowncuramt * -1);
                            $txnservice->doTxn($p1, $jrItem);

                            $equivacct = GPInstCur::where('instid', $instid)->where('statusid', 1)->where('curcode', $tmpcurcode)->first();
                            if (!$equivacct) {
                                $this->error('RC000164', ['curcode' => $tmpcurcode]);
                            }
                            $p1 = clone $p;
                            $p1->setTxnAcntCode($equivacct->equivacct);
                            $p1->setCurCode($basecur);
                            $p1->setTxnAmount($equbasecuramt);
                            $txnservice->doTxn($p1, $jrItem);

                            $txncount++;
                            $equbrbasecuramt = $equbrbasecuramt + $equbasecuramt;
                            $tmpcurcode = "";
                            $equowncuramt = 0;
                            $equbasecuramt = 0;
                        }

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($value->account);
                        $p1->setTxnAmount($value->balance * -1);
                        $txnservice->doTxn($p1, $jrItem);

                        $p1 = clone $p;
                        $p1->setCurCode($basecur);
                        $p1->setTxnAcntCode($value->account);
                        $p1->setTxnAmount($value->balance * $value->currrate);
                        $txnservice->doTxn($p1, $jrItem);

                        if ($tmpcurcode == "") {
                            $tmpcurcode = $value->currency;
                        }
                        $txncount++;
                        $equowncuramt = $equowncuramt + $value->balance * -1;
                        $equbasecuramt = $equbasecuramt + $value->balance * $value->currrate;

                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        throw $th;
                    }
                }
            }
            if (round($equowncuramt, 2) != 0) {
                try {
                    DB::beginTransaction();
                    $p1 = clone $p;
                    $p1->setTxnAcntCode($spotacnt);
                    $p1->setCurCode($tmpcurcode);
                    $p1->setTxnAmount($equowncuramt * -1);
                    $txnservice->doTxn($p1, $jrItem);

                    $equivacct = GPInstCur::where('instid', $instid)->where('statusid', 1)->where('curcode', $tmpcurcode)->first();
                    if (!$equivacct) {
                        $this->error('RC000164', ['curcode' => $tmpcurcode]);
                    }
                    $p1 = clone $p;
                    $p1->setTxnAcntCode($equivacct->equivacct);
                    $p1->setCurCode($basecur);
                    $p1->setTxnAmount($equbasecuramt);
                    $txnservice->doTxn($p1, $jrItem);

                    $txncount++;
                    $equbrbasecuramt = $equbrbasecuramt + $equbasecuramt;
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
            if (round($equbrbasecuramt, 2) != 0) {
                try {
                    DB::beginTransaction();
                    $p1 = clone $p;
                    $p1->setTxnAcntCode($spotacnt);
                    $p1->setCurCode($basecur);
                    $p1->setTxnAmount($equbrbasecuramt * -1);
                    $txnservice->doTxn($p1, $jrItem);

                    $equivacct = GPInstCur::where('instid', $instid)->where('statusid', 1)->where('curcode', $basecur)->first();
                    if (!$equivacct) {
                        $this->error('RC000164', ['curcode' => $basecur]);
                    }
                    $p1 = clone $p;
                    $p1->setTxnAcntCode($equivacct->equivacct);
                    $p1->setCurCode($basecur);
                    $p1->setTxnAmount($equbrbasecuramt * -1);
                    $txnservice->doTxn($p1, $jrItem);

                    $txncount++;
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
        }

        return [
            'txnJrno' => $jrno,
            'txncount' => $txncount
        ];
    }

    // /**
    //  * ЕД Орлого зарлага суурь валютруу хөрвүүлэх
    //  * Дахин судлах нэг өөр SQL орж ирсэн
    //  * gl024200
    //  */

    // public function gl024200(Request $request)
    // {
    //     $validate = $this->validate($request, [
    //         'brchno' => 'nullable',
    //     ]);

    //     $instid = auth()->user()->instid;
    //     $userid = auth()->user()->id;
    //     $service = new GlProcessService();
    //     $brchno = $validate['brchno'] ?? null;
    //     $gldate = CoreService::getGlDate($instid);

    //     $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
    //     if (!$gp || empty($gp->itemvalue)) {
    //         $this->error('RC000162', [
    //             'field' => ' суурь валют '
    //         ]);
    //     }

    //     $basecur = $gp->itemvalue;

    //     $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
    //     if (!$gp || empty($gp->itemvalue)) {
    //         $this->error('RC000162', [
    //             'field' => ' спот хөтлөгдөх дансны дугаар '
    //         ]);
    //     }
    //     $spotacnt = $gp->itemvalue;
    //     $txnservice = new GlTxnService();
    //     $jrno = "";
    //     $jrItem = new TxnItemEntity();
    //     $txndesc = 'Орлого зарлагыг суурь валют руу хөрвүүлэв.';

    //     if (empty($brchno)) {
    //         $branches = GPInstBrch::where('instid', $instid)
    //             ->where('statusid', 1)
    //             ->get();
    //     } else {
    //         $branches = GPInstBrch::where('instid', $instid)
    //             ->where('brchno', $brchno)
    //             ->where('statusid', 1)
    //             ->get();
    //     }


    //     $txncount = 0;
    //     foreach ($branches as $key => $branch) {
    //         $data = $service->getInExBalConvertBaseCur($gldate, $instid, $branch->brchno, $basecur);
    //         $tmpcurcode = "";
    //         $tmpequivacct = "";
    //         $equbal0 = 0;
    //         $equbal1 = 0;
    //         foreach ($data as $key => $value) {
    //             try {
    //                 DB::beginTransaction();
    //                 if (empty($jrno)) {
    //                     $jrno = "CB" . CoreService::getGlNextJrno();
    //                 }
    //                 $p = new TxnJrnlEntity();
    //                 $p->setAcntbrchno($value->branch);
    //                 $p->setCurCode($value->currency);
    //                 $p->setContCurCode($value->currency);
    //                 $p->setRate(null);
    //                 $p->setMainAcntPosition('PUSH');
    //                 $p->setTxncode('gl024200');
    //                 $p->setTxnDesc($txndesc);
    //                 $p->setJrno($jrno);

    //                 if ($tmpcurcode != $value->currency && $equbal0 != 0) {
    //                     $p1 = clone $p;
    //                     $p1->setTxnAcntCode($spotacnt);
    //                     $p1->setCurCode($tmpcurcode);
    //                     $p1->setTxnAmount($equbal0 * -1);
    //                     $txnservice->doTxn($p1, $jrItem);

    //                     $p1 = clone $p;
    //                     $p1->setTxnAcntCode($tmpequivacct);
    //                     $p1->setCurCode($basecur);
    //                     $p1->setTxnAmount($equbal1);
    //                     $txnservice->doTxn($p1, $jrItem);

    //                     $tmpcurcode = "";
    //                     $txncount++;
    //                     $equbal0 = 0;
    //                     $equbal1 = 0;
    //                 }

    //                 $p1 = clone $p;
    //                 $p1->setTxnAcntCode($value->account);
    //                 $p1->setTxnAmount($value->bal0);
    //                 $txnservice->doTxn($p1, $jrItem);

    //                 $p1 = clone $p;
    //                 $p1->setCurCode($basecur);
    //                 $p1->setTxnAcntCode($value->account);
    //                 $p1->setTxnAmount($value->bal1 * -1);
    //                 $txnservice->doTxn($p1, $jrItem);

    //                 if ($tmpcurcode == "") {
    //                     $tmpcurcode = $value->currency;
    //                     $tmpequivacct = $value->equivacct;
    //                 }
    //                 $txncount++;
    //                 $equbal0 = $equbal0 + $value->bal0;
    //                 $equbal1 = $equbal1 + $value->bal1;

    //                 DB::commit();
    //             } catch (\Throwable $th) {
    //                 DB::rollBack();
    //                 throw $th;
    //             }
    //         }
    //         if ($equbal0 != 0) {
    //             try {
    //                 DB::beginTransaction();
    //                 $p1 = clone $p;
    //                 $p1->setTxnAcntCode($spotacnt);
    //                 $p1->setCurCode($tmpcurcode);
    //                 $p1->setTxnAmount($equbal0 * -1);
    //                 $txnservice->doTxn($p1, $jrItem);

    //                 $p1 = clone $p;
    //                 $p1->setTxnAcntCode($tmpequivacct);
    //                 $p1->setCurCode($basecur);
    //                 $p1->setTxnAmount($equbal1);
    //                 $txnservice->doTxn($p1, $jrItem);

    //                 $tmpcurcode = "";
    //                 $txncount++;
    //                 $equbal0 = 0;
    //                 $equbal1 = 0;
    //                 DB::commit();
    //             } catch (\Throwable $th) {
    //                 DB::rollBack();
    //                 throw $th;
    //             }
    //         }
    //     }

    //     return [
    //         'txnJrno' => $jrno,
    //         'txncount' => $txncount
    //     ];
    // }

    /**
     * Арилжаа хаах
     * gl021200
     */

    public function gl025200(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable',
            'recbrchno' => 'nullable',
        ]);

        $instid = auth()->user()->instid;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $recbrchno = $validate['recbrchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;
        if (!empty($recbrchno)) {
            $gp = GPInstGp::where('instid', $instid)->where('itemname', 'IBAccount')->first();
            if (!$gp || empty($gp->itemvalue)) {
                $this->error('RC000162', [
                    'field' => ' салбар хоорондын тооцооны дансны дугаар '
                ]);
            }
            $ibacnt = $gp->itemvalue;
        }

        $txnservice = new GlTxnService();
        $jrno = "";
        $jrItem = new TxnItemEntity();
        $txndesc = 'ЕД арилжааны хаалт хийв.';

        if (empty($brchno)) {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('statusid', 1)
                ->get();
        } else {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('brchno', $brchno)
                ->where('statusid', 1)
                ->get();
        }

        $equivacct = GPInstCur::select('equivacct')
            ->where('curcode', $basecur)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->first();
        if (empty($equivacct) || empty($equivacct->equivacct)) {
            $this->error('RC000164');
        }
        $txncount = 0;
        foreach ($branches as $key => $branch) {
            $data = $service->getExchangeClose($gldate, $branch->brchno, $instid, $basecur, $spotacnt);
            foreach ($data as $key => $value) {
                try {

                    $txnamout = $value->difference * (-1);
                    DB::beginTransaction();
                    if (round($txnamout, 2) != 0) {
                        if (empty($jrno)) {
                            $jrno = "FX" . CoreService::getGlNextJrno();
                        }
                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($spotacnt);
                        $p->setTxnAmount($txnamout);
                        $p->setRate(null);
                        $p->setCurCode($basecur);
                        $p->setContCurCode($basecur);
                        $p->setTxnDesc($txndesc);
                        $p->setAcntbrchno($value->brchno);
                        $p->setTxncode('gl025200');
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        $txnservice->doTxn($p, $jrItem);

                        if (empty($recbrchno) || $value->brchno == $recbrchno) {
                            $p1 = clone $p;
                            if (($value->difference * 1) > 0) {
                                $p1->setTxnAcntCode($value->loss);
                            } else {
                                $p1->setTxnAcntCode($value->prof);
                            }
                            $p1->setTxnAmount($txnamout * (-1));
                            $txnservice->doTxn($p1, $jrItem);
                        } else {
                            $p1 = clone $p;
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setTxnAmount($txnamout * (-1));
                            $txnservice->doTxn($p1, $jrItem);
                        }

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($equivacct->equivacct);
                        $p1->setTxnAmount($txnamout);
                        $txnservice->doTxn($p1, $jrItem);

                        $p1 = clone $p;
                        $p1->setTxnAcntCode($value->equivacct);
                        $p1->setTxnAmount($txnamout * (-1));
                        $txnservice->doTxn($p1, $jrItem);

                        if (!empty($recbrchno) && $value->brchno != $recbrchno) {
                            $p1 = clone $p;
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAmount($txnamout);
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            if (($value->difference * 1) > 0) {
                                $p1->setTxnAcntCode($value->loss);
                            } else {
                                $p1->setTxnAcntCode($value->prof);
                            }
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAmount($txnamout * (-1));
                            $txnservice->doTxn($p1, $jrItem);
                        }

                        $txncount++;
                    }
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
        }

        return [
            'txnJrno' => $jrno,
            'txncount' => $txncount
        ];
    }

    /**
     * Позиц хаах
     * gl026200
     */

    public function gl026200(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable',
            'recbrchno' => 'nullable',
        ]);

        $instid = auth()->user()->instid;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $recbrchno = $validate['recbrchno'] ?? null;
        $gldate = CoreService::getGlDate($instid);

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;

        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'SpotAccount')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' спот хөтлөгдөх дансны дугаар '
            ]);
        }
        $spotacnt = $gp->itemvalue;

        if (!empty($recbrchno)) {
            $brch = GPInstBrch::where('instid', $instid)
                ->where('brchno', $recbrchno)
                ->where('statusid', 1)->first();
            if (!$brch) {
                $this->error('Позиц хаах салбарын дугаар буруу эсвэл Ерөнхий тохиргооны автопоциз хаах салбарын тохиргоо буруу байна.');
            }

            $gp = GPInstGp::where('instid', $instid)->where('itemname', 'IBAccount')->first();
            if (!$gp || empty($gp->itemvalue)) {
                $this->error('RC000162', [
                    'field' => ' салбар хоорондын тооцоог хөтлөх дансны дугаар '
                ]);
            }
            $ibacnt = $gp->itemvalue;
        }

        $txnservice = new GlTxnService();
        $jrno = "";
        $jrItem = new TxnItemEntity();
        $txndesc = 'Позиц хаалт хийв.';

        if (empty($brchno)) {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('statusid', 1)
                ->get();
        } else {
            $branches = GPInstBrch::where('instid', $instid)
                ->where('brchno', $brchno)
                ->where('statusid', 1)
                ->get();
        }

        $equivacct = GPInstCur::select('equivacct')
            ->where('curcode', $basecur)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->first();
        if (empty($equivacct) || empty($equivacct->equivacct)) {
            $this->error('RC000164');
        }
        $txncount = 0;
        if (!empty($recbrchno)) {
            foreach ($branches as $key => $branch) {
                $data = $service->getPositionClose($gldate, $branch->brchno, $instid, $basecur, $spotacnt, $recbrchno);
                // return;
                foreach ($data as $key => $value) {
                    try {

                        $fcamount = $value->fc * (-1);
                        $lcamount = $value->lc * (-1);
                        DB::beginTransaction();
                        if (round($fcamount, 2) != 0) {
                            if (empty($jrno)) {
                                $jrno = "AC" . CoreService::getGlNextJrno();
                            }
                            $p = new TxnJrnlEntity();
                            $p->setTxnAcntCode($spotacnt);
                            $p->setTxnAmount($fcamount);
                            $p->setRate(null);
                            $p->setCurCode($value->currency);
                            $p->setContCurCode($value->currency);
                            $p->setTxnDesc($txndesc);
                            $p->setAcntbrchno($value->branch);
                            $p->setTxncode('gl026200');
                            $p->setMainAcntPosition('PUSH');
                            $p->setJrno($jrno);
                            $txnservice->doTxn($p, $jrItem);

                            $p1 = clone $p;
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setTxnAmount($fcamount * (-1));
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            $p1->setTxnAcntCode($value->equivacct);
                            $p1->setCurCode($basecur);
                            $p1->setContCurCode($basecur);
                            $p1->setTxnAmount($lcamount);
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setCurCode($basecur);
                            $p1->setContCurCode($basecur);
                            $p1->setTxnAmount($lcamount * (-1));
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAcntCode($spotacnt);
                            $p1->setCurCode($basecur);
                            $p1->setContCurCode($basecur);
                            $p1->setTxnAmount($lcamount * (-1));
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setTxnAmount($fcamount);
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAcntCode($value->equivacct);
                            $p1->setCurCode($basecur);
                            $p1->setContCurCode($basecur);
                            $p1->setTxnAmount($lcamount * (-1));
                            $txnservice->doTxn($p1, $jrItem);

                            $p1 = clone $p;
                            $p1->setAcntbrchno($recbrchno);
                            $p1->setTxnAcntCode($ibacnt);
                            $p1->setCurCode($basecur);
                            $p1->setContCurCode($basecur);
                            $p1->setTxnAmount($lcamount);
                            $txnservice->doTxn($p1, $jrItem);

                            $txncount++;
                        }
                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        throw $th;
                    }
                }
            }
        }

        return [
            'txnJrno' => $jrno,
            'txncount' => $txncount
        ];
    }

    /**
     *ЕД Орлого зарлага хаалтын гүйлгээнд харах.
     * @AC gl027000
     * @return Response
     */
    public function gl027000(Request $request)
    {
        $validate = $this->validate($request, [
            'gldate' => 'required',
            'brchno' => 'nullable',
            'curcode' => 'nullable',
            'type' => 'nullable',
        ], [
            'gldate.required' => "RC000011"
        ]);

        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;
        $curcode = $validate['curcode'] ?? null;
        $type = $validate['type'] ?? null;
        $gldate = $validate['gldate'] ?? CoreService::getGlDate($instid);

        $data = $service->SelectInExBalSettle($gldate, $instid, $brchno, $curcode, $type);
        $data = array_map(function ($item) {
            $item->amount = (float) $item->amount;
            return $item;
        }, $data);
        return $data;
    }
}
