<?php

namespace Modules\Gl\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlBalance;
use Modules\Gl\Entities\GlTransaction;
use Modules\Gl\Http\Services\GlProcessService;
use Modules\Gp\Entities\GPInstCur;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Http\Services\CoreService;

class GlInquiryController extends Controller
{

    /**
     * Гүйлгээний лавлагаа.
     * @AC gl030000
     * @return Response
     */
    public function gl030000(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'period' => 'required',
        ], [
            'year.required' => "VC000008",
            'period.required' => "VC000008",
        ]);

        $instid = auth()->user()->instid;
        return $this->getGridData(
            $request,
            GlTransaction::where('instid', $instid)
                ->where('year', $validate['year'])
                ->where('period', $validate['period']),
            [
                ['field' => 'period', 'dir' => 'DESC'],
                ['field' => 'day', 'dir' => 'DESC'],
                ['field' => DB::raw('SUBSTR(journal, 3)'), 'dir' => 'DESC'],
                ['field' => 'entry', 'dir' => 'ASC'],
            ]
        );
    }

    /**
     * Журналын лавлагаа.
     * @AC gl030000
     * @return Response
     */
    public function gl030001(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'period' => 'required',
        ], [
            'year.required' => "VC000008",
            'period.required' => "VC000008",
        ]);

        $instid = auth()->user()->instid;
        return $this->getGridData(
            $request,
            GlTransaction::distinct()
                ->select('year', 'period', 'branch', 'journal', 'correctoin', 'day', 'description', 'txndate', 'postdate', 'tellerno')
                ->selectRaw('SUBSTR(journal, 3)')
                ->where('instid', $instid)
                ->where('year', $validate['year'])
                ->where('period', $validate['period']),
            [
                // ['field' => 'branch', 'dir' => 'ASC'],
                ['field' => DB::raw('SUBSTR(journal, 3)'), 'dir' => 'DESC'],
            ]
        );
    }

    /**
     * Журналын лавлагаа дэлгэрэнгүй.
     * @AC gl030002
     * @return Response
     */
    public function gl030002(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'period' => 'required',
            'journal' => 'required',
        ], [
            'year.required' => "VC000008",
            'period.required' => "VC000008",
            'journal.required' => "VC000008",
        ]);

        $instid = auth()->user()->instid;
        return $this->getGridData(
            $request,
            GlTransaction::where('instid', $instid)
                ->where('year', $validate['year'])
                ->where('period', $validate['period'])
                ->where('journal', $validate['journal']),
            [
                ['field' => 'branch', 'dir' => 'ASC'],
                ['field' => DB::raw('SUBSTR(journal, 3)'), 'dir' => 'DESC'],
                ['field' => 'entry', 'dir' => 'ASC'],
            ]
        );
    }

    /**
     * Дансны нэгдсэн лавлагаа
     * @AC gl030003
     * @return Response
     */
    public function gl030003(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'account' => 'required',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
        ], [
            'filters.array' => 'VC000010',
            'filters.*.field.required' => 'VC000010',
            'filters.*.value.max' => 'VC000010',
            'filters.*.cond.max' => 'VC000010',
            'year.required' => "VC000008",
            'account.required' => "VC000008",
        ]);
        $account = $validate['account'];
        $dailysumsql = "";

        for ($i = 0; $i < 13; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . ", ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . "(SUM (COALESCE (dt$tmpd, 0) * COALESCE (r.avgrateend, 1)))  dt$tmpd,
                                            (SUM (COALESCE (ct$tmpd, 0) * COALESCE (r.avgrateend, 1)))  ct$tmpd";
        }

        $instid = auth()->user()->instid;
        $gldate = CoreService::getGlDate($instid);

        $sql = GlBalance::selectRaw("(SUM (COALESCE (obal, 0) * COALESCE (r.avgrateend, 1))) obal, $dailysumsql")
            ->leftJoin(DB::raw("(SELECT h.*, EXTRACT (YEAR FROM h.date) AS year
            FROM (
            SELECT curcode AS currency,
                   MAX (date) ratedate,
                   instid
                   FROM tr_cur_rate_hist
                   WHERE date <= '$gldate'
                        AND curcode IS NOT NULL
                        AND instid = $instid
                   GROUP BY curcode, instid
                   ) t
               INNER JOIN tr_cur_rate_hist h
               ON  h.instid = t.instid
               AND h.curcode = t.currency
               AND h.date = t.ratedate) r"), function ($join) {
                $join->on('gl_balance.instid', '=', 'r.instid')
                    ->where('currency', '=', 'r.curcode');
            })
            ->where('gl_balance.instid', $instid)
            ->where('gl_balance.year', $validate['year'])
            ->whereRaw("gl_balance.account = '$account'");

        $currency = 'ALL';
        $branch = 'ALL';

        if (isset($validate['filters'])) {
            $tmpfilters = [];
            foreach ($validate['filters'] as $key => $value) {
                if ($value['field'] == 'currency') {
                    $currency = $value['value'];
                }
                if ($value['field'] == 'branch') {
                    $branch = $value['value'];
                }
                $value['field'] = 'gl_balance.' . $value['field'];
                $tmpfilters[] = $value;
            }
            $sql = $this->applyFilters($sql, $tmpfilters);
        }

        $data = $sql->get();

        $tmpdata = [];
        foreach ($data as $key => $item) {
            $obal = $item->obal;
            for ($i = 1; $i <= 13; $i++) {
                $index = $i;
                if ($index < 10) {
                    $index = '0' . $index;
                }
                $ct = 'ct' . $index;
                $dt = 'dt' . $index;
                $tmpobal = $obal;
                $obal = $obal + $item->$dt + $item->$ct;
                $tmpdata[] = [
                    'ct' => $item->$ct,
                    'dt' => $item->$dt,
                    'obal' => $tmpobal,
                    'ctdt' => $item->$dt + $item->$ct,
                    'lbal' => $obal,
                    'currency' => $currency,
                    'branch' => $branch,
                    'year' => $validate['year'],
                    'account' => $account,
                    'period' => $i
                ];
            }
        }
        return $tmpdata;
    }
    /**
     * Дундаж үлдэгдлийн лавлагаа
     * @AC gl030004
     * @return Response
     */
    public function gl030004(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'branch' => 'nullable',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
        ], [
            'filters.array' => 'VC000010',
            'filters.*.field.required' => 'VC000010',
            'filters.*.value.max' => 'VC000010',
            'filters.*.cond.max' => 'VC000010',
            'year.required' => "VC000008",
        ]);

        $instid = auth()->user()->instid;
        $gldate = CoreService::getGlDate($instid);


        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $basecur = $gp->itemvalue;

        $dailysumsql1 = "";

        for ($i = 0; $i < 31; $i++) {
            $tmpd = $i + 1;
            $t = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql1 = $dailysumsql1 . "+ (mday.days - $t) * (COALESCE (dt$tmpd, 0) + COALESCE (ct$tmpd, 0))";
        }

        $mdaySubquery = "(SELECT * FROM unnest(array[
                                            (1, 32),
                                            (2, 29),
                                            (3, 32),
                                            (4, 31),
                                            (5, 32),
                                            (6, 31),
                                            (7, 32),
                                            (8, 32),
                                            (9, 31),
                                            (10, 32),
                                            (11, 31),
                                            (12, 32)
                                        ]) AS x(month int, days int)) as mday";
        $ydaySubquery = "(SELECT * FROM unnest(array[
                                            (1, 366),
                                            (2, 335),
                                            (3, 307),
                                            (4, 276),
                                            (5, 246),
                                            (6, 215),
                                            (7, 185),
                                            (8, 154),
                                            (9, 123),
                                            (10, 93),
                                            (11, 62),
                                            (12, 32)
                                        ]) AS x(month int, days int)) as yday";

        $t1Subquery = DB::table('gl_daily_bal as b')
            ->select('b.currency', 'account', 'period', DB::raw("
                                            SUM(
                                                TRUNC(((mday.days - 1) * COALESCE (obal, 0)
                                                $dailysumsql1)/mday.days*COALESCE(r.avgrateend, 1),2)
                                            ) AS balance
                                        "))
            ->leftJoin(DB::raw($mdaySubquery), function ($join) {
                $join->on('mday.month', '=', 'b.period');
            })
            ->leftJoin(DB::raw("(SELECT h.*, EXTRACT (YEAR FROM h.date) AS year
                                FROM (
                                SELECT curcode AS currency,
                                    MAX (date) ratedate,
                                    instid
                                    FROM tr_cur_rate_hist
                                    WHERE date <= '$gldate'
                                            AND curcode IS NOT NULL
                                            AND instid = $instid
                                    GROUP BY curcode, instid
                                    ) t
                                INNER JOIN tr_cur_rate_hist h
                                ON  h.instid = t.instid
                                AND h.curcode = t.currency
                                AND h.date = t.ratedate) r"), function ($join) {
                $join->on('b.instid', '=', 'r.instid')
                    ->where('b.currency', '=', 'r.curcode');
            })
            ->where('b.instid', '=', $instid)
            ->where('b.year', '=', $validate['year'])
            ->whereRaw("b.currency = '$basecur'")
            ->when(isset($validate['branch']), function ($query) use ($validate) {
                return $query->where('b.branch', '=', $validate['branch']);
            })
            ->groupBy('b.currency', 'account', 'period');

        $dailysumsql2 = "";

        for ($i = 0; $i < 31; $i++) {
            $tmpd = $i + 1;
            $t = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql2 = $dailysumsql2 . "+ (yday.days - $t) * (COALESCE (dt$tmpd, 0) + COALESCE (ct$tmpd, 0))";
        }

        $t2Subquery = DB::table('gl_daily_bal as b')
            ->select('b.currency', 'account', DB::raw("
            SUM (
                TRUNC (
                      (  CASE
                             WHEN yday.month = 1
                             THEN
                                 (yday.days - 1) * COALESCE (obal, 0)
                             ELSE
                                 0
                         END
                $dailysumsql2)/ 365
                * COALESCE (r.avgrateend, 1),
                2)
            )    AS balance
        "))
            ->leftJoin(DB::raw($ydaySubquery), function ($join) {
                $join->on('yday.month', '=', 'b.period');
            })
            ->leftJoin(DB::raw("(SELECT h.*, EXTRACT (YEAR FROM h.date) AS year
                                    FROM (
                                    SELECT curcode AS currency,
                                        MAX (date) ratedate,
                                        instid
                                        FROM tr_cur_rate_hist
                                        WHERE date <= '$gldate'
                                                AND curcode IS NOT NULL
                                                AND instid = $instid
                                        GROUP BY curcode, instid
                                        ) t
                                    INNER JOIN tr_cur_rate_hist h
                                    ON  h.instid = t.instid
                                    AND h.curcode = t.currency
                                    AND h.date = t.ratedate) r"), function ($join) {
                $join->on('b.instid', '=', 'r.instid')
                    ->where('b.currency', '=', 'r.curcode');
            })
            ->where('b.instid', '=', $instid)
            ->where('b.year', '=', $validate['year'])
            ->whereRaw("b.currency = '$basecur'")
            ->when(isset($validate['branch']), function ($query) use ($validate) {
                return $query->where('b.branch', '=', $validate['branch']);
            })
            ->groupBy('b.currency', 'account');

        $periodcaseql = "";

        for ($i = 1; $i < 13; $i++) {
            $periodcaseql = $periodcaseql . "SUM (CASE t1.period WHEN $i THEN t1.balance ELSE 0 END) m$i,";
        }

        $result = DB::query()
            ->select(
                't1.currency',
                't1.account',
                DB::raw("$periodcaseql
                        SUM (t2.balance) lbal")
            )
            ->fromSub($t1Subquery, 't1')
            ->leftJoinSub($t2Subquery, 't2', function ($join) {
                $join->on('t2.currency', '=', 't1.currency')
                    ->on('t2.account', '=', 't1.account');
            })
            ->groupBy('t1.currency', 't1.account');

        $currency = 'ALL';
        $branch = 'ALL';

        if (isset($validate['filters'])) {
            $tmpfilters = [];
            foreach ($validate['filters'] as $key => $value) {
                if ($value['field'] == 'currency') {
                    $currency = $value['value'];
                }
                if ($value['field'] == 'branch') {
                    $branch = $value['value'];
                }
                if ($value['field'] == 'account') {
                    $account = $value['value'];
                }

                $isinclude = false;
                for ($i = 1; $i < 13; $i++) {
                    if ($value['field'] == "m" . $i) {
                        $isinclude = true;
                        $result->havingRaw("SUM (CASE t1.period WHEN $i THEN t1.balance ELSE 0 END) " . $value['cond'] . " " . $value['value']);
                        break;
                    }
                }

                if ($value['field'] == "lbal") {
                    $isinclude = true;
                    $result->havingRaw("SUM (t2.balance)" . $value['cond'] . " " . $value['value']);
                }

                if (!$isinclude) {
                    $value['field'] = 't1.' . $value['field'];
                    $tmpfilters[] = $value;
                }
            }
            $sql = $this->applyFilters($result, $tmpfilters);
        }

        $data = $sql->get();
        $tmpdata = [];
        foreach ($data as $key => $items) {
            for ($i = 1; $i < 13; $i++) {
                $field = "m$i";
                $items->$field = $items->$field * 1;
            }
            $items->lbal = $items->lbal * 1;
            $tmpdata[] = $items;
        }
        return $tmpdata;
    }

    /**
     * ЕД нэгдсэн лавлагаа.
     * @AC gl030005
     * @return Response
     */
    public function gl030005(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'period' => 'required',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
        ], [
            'year.required' => "VC000008",
            'period.required' => "VC000008",
        ]);

        $instid = auth()->user()->instid;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'BaseCurrency')->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => ' суурь валют '
            ]);
        }

        $cur = $validate['currency'] ?? $gp->itemvalue;

        $dailysumsql = "";
        $period = 0;
        $period = $validate['period'];

        for ($i = 0; $i < $period; $i++) {
            $tmpd = $i + 1;
            $t = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " + (COALESCE (a.dt$tmpd, 0) + COALESCE (a.ct$tmpd, 0))";
        }
        $tmpperiod = $period . '';
        if ($period < 10) {
            $tmpperiod = "0" . $period;
        }

        $isbranchfilter = false;
        if (isset($validate['filters'])) {
            foreach ($validate['filters'] as $key => $value) {
                if ($value['field'] == 'branch') {
                    $isbranchfilter = true;
                }
            }
        }

        $sql = DB::table('gl_balance as a')
            ->leftJoin('gl_account as b', function ($join) {
                $join->on('b.acntno', '=', 'a.account')
                    ->on('b.instid', '=', 'a.instid');
            })
            ->select(
                DB::raw($isbranchfilter ? "branch" : "'ALL' as branch"),
                'a.account',
                'a.currency',
                'b.name',
                DB::raw("SUM(COALESCE(a.dt$tmpperiod, 0)) as dt"),
                DB::raw("SUM(COALESCE(a.ct$tmpperiod, 0)) as ct"),
                DB::raw("SUM(COALESCE(a.dt$tmpperiod, 0) + COALESCE(a.ct$tmpperiod, 0)) as net"),
                DB::raw("SUM(COALESCE(a.obal, 0) $dailysumsql) as cbal")
            )
            ->where('a.instid', $instid)
            ->where('a.year', $validate['year'])
            ->whereRaw("a.currency = '$cur'")
            ->groupBy('a.currency', 'a.account', 'b.name');

        if ($isbranchfilter) {
            $sql = $sql->groupBy('branch');
        }

        $data = $this->getGridData(
            $request,
            $sql,
        );

        $data = array_map(function ($item) {
            $item->cbal = $item->cbal * 1;
            $item->dt = (float) $item->dt;
            $item->ct = (float) $item->ct;
            $item->net = (float) $item->net;
            return $item;
        }, $data->items());
        return $data;
    }

    /**
     * Позицийн лавлагаа.
     * @AC gl030006
     * @return Response
     */
    public function gl030006(Request $request)
    {
        $validate = $this->validate($request, [
            'brchno' => 'nullable',
        ]);

        $instid = auth()->user()->instid;
        $gldate = CoreService::getGlDate($instid);
        $service = new GlProcessService();
        $brchno = $validate['brchno'] ?? null;

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
     * Ханшын лавлагаа.
     * @AC gl030007
     * @return Response
     */
    public function gl030007(Request $request)
{
    $instid = auth()->user()->instid;
    $txnDate = new Carbon(CoreService::getTxnDate($instid));

    return $this->getGridData(
        $request,
        GPInstCur::selectRaw('?::date AS date, GP_inst_cur.*', [$txnDate])
            ->where('instid', $instid)
            ->where('statusid', 1)
            ->orderBy('listorder')
    );
}


    /**
     * Ханшын түүхийн лавлагаа.
     * @AC gl030008
     * @return Response
     */
    public function gl030008(Request $request)
    {
        $validate = $this->validate($request, [
            'year' => 'required',
            'period' => 'required',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
        ], [
            'year.required' => "VC000008",
            'period.required' => "VC000008",
        ]);


        $instid = auth()->user()->instid;
        $date1 = $validate['year'] . '-' . str_pad($validate['period'], 2, '0', STR_PAD_LEFT) . '-01';
        $date2 = $validate['year'] . '-' . str_pad($validate['period'], 2, '0', STR_PAD_LEFT) . '-' . $this->lastDay($validate['year'], $validate['period']);

        $subquery = DB::table('tr_cur_rate_hist as h')
            ->leftJoin('GP_inst_cur as c', function ($join) {
                $join->on('c.curcode', '=', 'h.curcode')
                    ->on('c.instid', '=', 'h.instid')
                    ->where('c.statusid', 1);
            })
            ->select(
                'h.curcode',
                'c.name',
                'h.date',
                'h.avgrateend'
            )
            ->where('h.instid', $instid)
            ->whereRaw("h.date between '$date1' and '$date2'")
            ->orderBy('h.date', 'DESC')
            ->orderBy('c.listorder');

        $sql = DB::query()->select("*")->fromSub($subquery, 'subquery');

        $data = $this->getGridData(
            $request,
            $sql,
        );
        $data = array_map(function ($item) {
            $item->currate = (float) $item->avgrateend;
            return $item;
        }, $data->items());

        return $data;
    }

    function lastDay($year, $month)
    {
        $date = new DateTime("{$year}-{$month}-01");
        $date->modify('last day of this month');
        return $date->format('d');
    }
}
