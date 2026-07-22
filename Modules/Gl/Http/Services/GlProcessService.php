<?php

namespace Modules\Gl\Http\Services;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GlProcessService extends Controller
{

    /**
     * Баталжуулах түр дансанд үлдэгдэл буйг шалгаж харах.
     */
    public function SelectSuspccount($sysdate, $instid, $suspacnt)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');
        $year = Carbon::parse($sysdate)->year;
        $day = 13;
        $dailysumsql = "";

        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " coalesce (ct$tmpd, 0) + coalesce (dt$tmpd, 0) ";
        }

        $sql = "SELECT
                        branch,
                        unit,
                        currency,
                        (coalesce (obal, 0)
                        + $dailysumsql) as balance
                FROM gl_balance
                WHERE instid = $instid
                    AND account = '$suspacnt'
                    AND year = $year
                    AND ((coalesce (obal, 0) + $dailysumsql) <> 0
                        AND ((coalesce (obal, 0) + $dailysumsql) >= 0.01
                            OR (coalesce (obal, 0) + $dailysumsql) <= -0.01))
                ORDER BY branch, unit, currency
                ";
        return DB::select(DB::raw($sql));
    }
    /**
     * daily_bal and balance дээр шинээр ханшын equivacct, fxprof, fxloss, rvprof, rvloss дансууд ороход
     */

    public function CreateCurRateNewAccount($sysdate, $instid, $userid, $recbrchno, $basecur)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');
        $year = Carbon::parse($csysdate)->year;
        $month = Carbon::parse($csysdate)->month;
        $created_at = getNow();

        $subQuery1 = DB::table('gl_daily_bal')
            ->selectRaw('distinct(account)')
            ->where('instid', DB::raw('d.instid'));

        $subQuery2 = DB::table('gl_balance')
            ->selectRaw('distinct(account)')
            ->where('instid', DB::raw('d.instid'));

        $result = DB::table('gl_account as d')
            ->join('GP_inst_branch as b', function ($join) use ($recbrchno) {
                $join->on('d.instid', '=', 'b.instid')
                    ->where('b.brchno', '=', $recbrchno)
                    ->where('b.statusid', '=', 1);
            })
            ->join('GP_inst_cur as c', function ($join) {
                $join->on('d.instid', '=', 'c.instid')
                    ->where('c.statusid', '=', 1);
            })
            ->where('d.instid', $instid)
            ->where('d.statusid', 1)
            ->whereNotIn('d.acntno', $subQuery1)
            ->whereNotIn('d.acntno', $subQuery2)
            ->where(function ($query) {
                $query->whereColumn('d.acntno', 'c.equivacct')
                    ->orWhereColumn('d.acntno', 'c.fxprof')
                    ->orWhereColumn('d.acntno', 'c.fxloss')
                    ->orWhereColumn('d.acntno', 'c.rvprof')
                    ->orWhereColumn('d.acntno', 'c.rvloss');
            })
            ->select([
                'b.brchno as branch',
                DB::raw("'0000' as unit"),
                'd.acntno as account',
                DB::raw("'$basecur' as currency"),
                DB::raw("$year as year"),
                DB::raw("$month as period"),
                DB::raw('0 as obal'),
                'd.instid',
                DB::raw("$userid as created_by"),
                DB::raw("$userid as updated_by"),
                DB::raw("'$created_at' as created_at"),
            ])
            ->distinct()
            ->orderByRaw('d.acntno, b.brchno');
        //  Log::debug($result->toSql());
        return $result->get();
    }

    /**
     * Ханш тэгшитгэх жагсаалт харах
     * Арилжаа хаах жагсаалт харах
     * Позиц хаах жагсаалт харах.
     */
    public function SelectRateEqualization($sysdate, $brchno, $instid, $basecur, $spotacnt)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');
        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;
        $isall = false;
        if (empty($brchno)) {
            $isall = true;
        }

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            if ($i != 1) {
                $monthlysumsql = $monthlysumsql . " + ";
            }
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . " trunc(coalesce(a.ct$tmpd, 0), 2) + trunc(coalesce(a.dt$tmpd, 0), 2) ";
        }
        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce(b.ct$tmpd, 0), 2) + trunc(coalesce(b.dt$tmpd, 0), 2) ";
        }

        $sql = "SELECT
        " . ($isall ? " 'ALL' brchno,
                        'ALL' brchname,
                       -- 'ALL' unit,"
            : "
                         t1.brchno,
                         t1.brchname,
                       -- t1.unit,
                       ") . "
                 t1.currency,
                 coalesce (t3.avgrateend, 0) AS currrate,
                 SUM (coalesce (t2.balance, 0)) AS spot,
                 SUM (trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)) AS value,
                 SUM (coalesce (t1.balance, 0)) AS equiv,
                 SUM (trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)
                       - coalesce (t1.balance, 0)) AS difference
            FROM (SELECT d.brchno,
                         d.brchname,
                         -- '0000' AS unit,
                         d.listorder,
                         d.currency,
                         d.equivacct,
                         d.instid,
                           trunc(coalesce(a.obal, 0), 2)
                        " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                         + $dailysumsql AS balance
                    FROM (SELECT b.brchno,
                                 b.name AS brchname,
                                 c.listorder,
                                 c.curcode AS currency,
                                 c.equivacct,
                                 b.instid
                                -- '0000' AS unit
                            FROM GP_inst_cur c, GP_inst_branch b
                           WHERE
                           " . ($isall ? "" : "b.brchno IN ('$brchno') AND ") . "
                           c.instid = b.instid AND c.statusid = 1
                            AND c.instid = $instid) d
                         LEFT JOIN gl_balance a
                            ON     d.instid = a.instid
                               AND d.brchno = a.branch
                              -- AND d.unit = a.unit
                               AND d.equivacct = a.account
                               AND a.currency = '$basecur'
                               AND a.year = $year
                         LEFT JOIN gl_daily_bal b
                            ON     d.instid = b.instid
                               AND d.brchno = b.branch
                             --  AND d.unit = b.unit
                               AND d.equivacct = b.account
                               AND b.currency = '$basecur'
                               AND b.year = $year
                               AND b.period = $month
                   WHERE 1 = 1
                    " . ($isall ? "" : "AND a.branch IN ('$brchno')") . "
                   ) t1
                 LEFT JOIN
                 (SELECT c.curcode AS currency,
                         a.branch,
                        -- a.unit,
                         a.account,
                         a.instid,
                        trunc(coalesce(a.obal, 0), 2)
                        " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                        + $dailysumsql
                         AS balance
                    FROM GP_inst_cur c
                         LEFT JOIN gl_balance a
                            ON     c.instid = a.instid
                               AND c.curcode = a.currency
                               AND a.account = '$spotacnt'
                         LEFT JOIN gl_daily_bal b
                            ON     c.instid = b.instid
                               AND a.branch = b.branch
                              -- AND a.unit = b.unit
                               AND b.account = '$spotacnt'
                               AND b.currency = c.curcode
                               AND b.year = $year
                               AND b.period = $month
                   WHERE a.year = $year and c.instid = $instid and c.statusid = 1
                   " . ($isall ? "" : "AND a.branch IN ('$brchno')") .
            ") t2
                    ON     t1.currency = t2.currency
                       AND t1.brchno = t2.branch
                     --  AND t1.unit = t2.unit
                     AND t1.instid = t2.instid
                 LEFT JOIN (SELECT h.*, EXTRACT (YEAR FROM h.date) AS year
                 FROM (
                 SELECT curcode AS currency,
                        MAX (date) ratedate,
                        instid
                        FROM tr_cur_rate_hist
                        WHERE date <= '$sysdate'
                             AND curcode IS NOT NULL
                             AND instid = $instid
                        GROUP BY curcode, instid
                        ) t
                    INNER JOIN tr_cur_rate_hist h
                    ON  h.instid = t.instid
                    AND h.curcode = t.currency
                    AND h.date = t.ratedate) t3
                    ON t1.currency = t3.curcode
                    AND t1.instid = t3.instid
        GROUP BY

        " . ($isall ? "" : "t1.brchno,
                            t1.brchname,
                            -- t1.unit,
                            ") .
            "
                 t1.currency,
                 t3.avgrateend,
                 t1.listorder
        ORDER BY brchno,
                 -- unit,
                 t1.listorder
        ";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }

    /**
     * Ханш тэгшитгэх жагсаалт
     * gl021200
     */
    public function getRateEqualization($sysdate, $brchno, $instid, $basecur, $spotacnt)
    {

        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            if ($i != 1) {
                $monthlysumsql = $monthlysumsql . " + ";
            }
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . " trunc(coalesce(a.ct$tmpd, 0), 2) + trunc(coalesce(a.dt$tmpd, 0), 2) ";
        }
        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce(b.ct$tmpd, 0), 2) + trunc(coalesce(b.dt$tmpd, 0), 2) ";
        }

        $sql = "SELECT t1.brchno,
                    t1.currency,
                    coalesce (t3.avgrateend, 0) AS currrate,
                    SUM (coalesce (t2.balance, 0)) AS spot,
                    SUM (trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)) AS value,
                    SUM (coalesce (t1.balance, 0)) AS equiv,
                    SUM (trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)
                        - coalesce (t1.balance, 0)) AS difference,
                    t1.prof,
                    t1.loss,
                    t1.equivacct
            from (select d.currency,
                            d.brchno,
                           -- d.unitno,
                            d.equivacct,
                            d.prof,
                            d.loss,
                            d.instid,
                            trunc(coalesce(a.obal, 0), 2)
                            " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                            + $dailysumsql AS balance
                    from (select c.curcode currency,
                                    b.brchno,
                                  -- '0000' AS unitno,
                                    c.equivacct,
                                    c.rvprof     prof,
                                    c.rvloss     loss,
                                    b.instid
                            from GP_inst_cur c, GP_inst_branch b
                            where b.brchno = '$brchno' and c.instid = b.instid and c.statusid = 1
                                    AND c.instid = $instid) d
                            left join gl_balance a
                                on  d.instid = a.instid
                                and d.brchno = a.branch
                                and d.equivacct = a.account
                                and a.currency = '$basecur'
                                and a.branch = '$brchno'
                                and a.year = $year
                            left join gl_daily_bal b
                                on  d.instid = b.instid
                                and b.branch = '$brchno'
                                and d.equivacct = b.account
                                and b.currency = '$basecur'
                                and b.year = $year
                                and b.period = $month) t1
                    left join
                    (select c.curcode currency,
                            a.branch,
                           -- a.unit,
                            c.instid,
                            a.account,
                            trunc(coalesce(a.obal, 0), 2)
                            " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                            + $dailysumsql    as balance
                    from GP_inst_cur c
                            left join gl_balance a
                                on  c.instid = a.instid
                                AND c.curcode = a.currency
                                and a.account = '$spotacnt'
                                and a.year = $year
                            left join gl_daily_bal b
                                on  c.instid = b.instid
                                AND a.branch = b.branch
                               -- and a.unit = b.unit
                                and b.account = '$spotacnt'
                                and b.currency = c.curcode
                                and b.year = $year
                                and b.period = $month
                            where c.instid = $instid and c.statusid = 1) t2
                        on     t1.currency = t2.currency
                        and t1.brchno = t2.branch
                        AND t1.instid = t2.instid
                        -- and t1.unitno = t2.unit
                    left join
                    (SELECT h.*, EXTRACT (YEAR FROM h.date) AS year
                                FROM (
                                SELECT curcode AS currency,
                                    MAX (date) ratedate,
                                    instid
                                    FROM tr_cur_rate_hist
                                    WHERE date <= '$sysdate'
                                            AND curcode IS NOT NULL
                                            AND instid = $instid
                                    GROUP BY curcode, instid
                                    ) t
                                INNER JOIN tr_cur_rate_hist h
                                ON  h.instid = t.instid
                                AND h.curcode = t.currency
                                AND h.date = t.ratedate) t3
                            ON t1.currency = t3.curcode AND t1.instid = t3.instid
            where   trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 5)
                    - coalesce (t1.balance, 0) <>
                    0
            group by t1.brchno,
                   -- t1.unitno,
                    t1.currency,
                    t3.avgrateend,
                    t1.prof,
                    t1.loss,
                    t1.equivacct
            order by t1.brchno,
                  -- t1.unitno,
                     t1.currency";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }

    /**
     * Гүйлгээ татах харах
     */
    public function SelectPullTxn($sysdate, $again, $instid, $basecur, $spotacnt, $isuspacnt)
    {

        $sql = "WITH FirstUnion AS (
            SELECT
                a.acntbrchno,
                -- COALESCE(a.unitcode, '0000') AS unit,
                a.curcode,
                a.gl || TRIM(COALESCE(a.segcode, '00')) AS gl,
                TRUNC(SUM(TRUNC(a.txnamount, 2)), 2) AS amount,
                -- SUM(a.txnamount) AS amount,
                MAX(CASE WHEN an.acntno IS NULL THEN 1 ELSE 0 END) AS error
            FROM tr_glretail_entry a
            LEFT JOIN gl_account an ON an.instid = a.instid
                and an.acntno = a.gl || TRIM(COALESCE(a.segcode, '00'))
                and an.statusid = 1
            WHERE
                a.instid = $instid
                AND a.corr IN (0, 2)
                AND a.txndate = '$sysdate'
                AND COALESCE(a.flags, 0) = $again
                AND COALESCE(a.mark, 0) <> 1
            GROUP BY
                a.acntbrchno,
               -- COALESCE(a.unitcode, '0000'),
                a.curcode,
                a.gl || TRIM(COALESCE(a.segcode, '00')),
                CASE WHEN TRUNC(a.txnamount, 2) > 0 THEN 1 ELSE 0 END
        ),

        SecondUnion AS (
            SELECT
                acntbrchno,
                --'0000' AS unit,
                curcode,
                '$isuspacnt',
                - TRUNC(SUM(TRUNC(txnamount, 2)), 2),
                -- - SUM(txnamount),
                2
            FROM tr_glretail_entry
            WHERE
                instid = $instid
                AND corr IN (0, 2)
                AND txndate = '$sysdate'
                AND COALESCE(flags, 0) = $again
                AND COALESCE(mark, 0) <> 1
            GROUP BY acntbrchno, curcode
            HAVING TRUNC(SUM(txnamount), 2) <> 0
        )

        SELECT * FROM FirstUnion
        UNION ALL
        SELECT * FROM SecondUnion
        UNION ALL
        SELECT
            a.acntbrchno,
            --COALESCE(a.unitcode, '0000') AS unit,
            '$basecur' AS curcode,
            c.equivacct AS gl,
            TRUNC(SUM(TRUNC(COALESCE(a.baseamount, 0), 2)), 2) AS amount,
            -- SUM(COALESCE(a.baseamount, 0)) AS amount,
            0
        FROM tr_glretail_entry a
        LEFT JOIN GP_inst_cur c ON a.instid = c.instid AND a.curcode = c.curcode AND c.statusid = 1
        WHERE
            a.instid = $instid
            AND a.corr IN (0, 2)
            AND a.txndate = '$sysdate'
            AND COALESCE(a.flags, 0) = $again
            AND a.gl || TRIM(COALESCE(a.segcode, '00')) = '$spotacnt'
            AND COALESCE(a.mark, 0) <> 1
        GROUP BY a.acntbrchno,
                 --COALESCE(a.unitcode, '0000'),
                 a.curcode, c.equivacct
        ORDER BY 1, 2
        ";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }

    /**
     * Гүйлгээ татах жагсаалт
     */
    public function getPullTxn($sysdate, $again, $instid, $basecur, $spotacnt, $isuspacnt)
    {

        $sql = "WITH FirstUnion AS (
            SELECT
                a.acntbrchno,
                -- COALESCE(a.unitcode, '0000') AS unit,
                a.curcode,
                a.gl || TRIM(COALESCE(a.segcode, '00')) AS gl,
                TRUNC(SUM(a.txnamount), 2) AS amount,
                --SUM(a.txnamount) AS amount,
                MAX(CASE WHEN an.acntno IS NULL THEN 1 ELSE 0 END) AS error
            FROM tr_glretail_entry a
            LEFT JOIN gl_account an ON an.instid = a.instid and an.acntno = a.gl || TRIM(COALESCE(a.segcode, '00')) and an.statusid = 1
            WHERE
                a.instid = $instid
                AND a.corr IN (0, 2)
                AND a.txndate = '$sysdate'
                AND COALESCE(a.flags, 0) = $again
                AND COALESCE(a.mark, 0) <> 1
            GROUP BY
                a.acntbrchno,
               -- COALESCE(a.unitcode, '0000'),
                a.curcode,
                a.gl || TRIM(COALESCE(a.segcode, '00')),
                CASE WHEN TRUNC(a.txnamount, 2) > 0 THEN 1 ELSE 0 END
        ),

        SecondUnion AS (
            SELECT
                acntbrchno,
                --'0000' AS unit,
                curcode,
                '$isuspacnt',
                - TRUNC(SUM(txnamount), 2),
                -- - SUM(txnamount),
                2
            FROM tr_glretail_entry
            WHERE
                instid = $instid
                AND corr IN (0, 2)
                AND txndate = '$sysdate'
                AND COALESCE(flags, 0) = $again
                AND COALESCE(mark, 0) <> 1
            GROUP BY acntbrchno, curcode
            HAVING TRUNC(SUM(txnamount), 2) <> 0
        )

        SELECT * FROM FirstUnion
        UNION ALL
        SELECT * FROM SecondUnion
        UNION ALL
        SELECT
            a.acntbrchno,
            --COALESCE(a.unitcode, '0000') AS unit,
            '$basecur' AS curcode,
            c.equivacct AS gl,
            TRUNC(SUM(COALESCE(a.baseamount, 0)), 2) AS amount,
            -- SUM(COALESCE(a.baseamount, 0)) AS amount,
            0
        FROM tr_glretail_entry a
        LEFT JOIN GP_inst_cur c ON a.instid = c.instid AND a.curcode = c.curcode AND c.statusid = 1
        WHERE
            a.instid = $instid
            AND a.corr IN (0, 2)
            AND a.txndate = '$sysdate'
            AND COALESCE(a.flags, 0) = $again
            AND a.gl || TRIM(COALESCE(a.segcode, '00')) = '$spotacnt'
            AND COALESCE(a.mark, 0) <> 1
        GROUP BY a.acntbrchno,
                 --COALESCE(a.unitcode, '0000'),
                 a.curcode, c.equivacct
        ORDER BY 1, 2
        ";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }

    /**
     * Үлдэгдэл тулгах харах Дэлгэрэнгүй
     */
    public function SelectBalanceCompareDetail($sysdate, $instid, $brchno)
    {
        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;

        $isall = empty($brchno);
        $brchListSql = '';

        if (!$isall) {
            if (is_array($brchno)) {
                $brchListSql = collect($brchno)
                    ->filter()                  // хоосон элементүүдийг хасна
                    ->unique()                  // давхардал арилгана
                    ->map(fn($b) => "'" . addslashes($b) . "'")
                    ->implode(',');             //  '001','005','010'
            } else {
                $brchListSql = "'" . addslashes($brchno) . "'";
            }
        }

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . "+ trunc(coalesce (ct$tmpd, 0), 2) + trunc(coalesce (dt$tmpd, 0), 2) ";
        }
        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce (c.ct$tmpd, 0), 2) + trunc(coalesce (c.dt$tmpd, 0), 2) ";
        }


        $sql = "WITH
        x
        AS
           (SELECT branch,
                --    unit,
                   account,
                   currency,
                   year,
                   trunc(coalesce (obal, 0), 2)
                   " . ($month != 1 ? "$monthlysumsql" : "") . "
                   AS obal,
                   instid
              FROM gl_balance
             WHERE instid = $instid AND year = $year),
        combined
        AS
           (SELECT a.brchno,
                --    '0000' AS unit,
                   a.glsegcode,
                   a.curcode,
                   b.name,
                   round (coalesce(a.retailbal, 0), 2) AS retailbal,
                -- coalesce (a.retailbal, 0) AS retailbal,
                   0 AS glbal,
                   0  AS calbal,
                   a.instid
              FROM tr_glretail_bal a
                   LEFT JOIN gl_account b
                      ON a.instid = b.instid AND TRIM (a.glsegcode) = b.acntno and b.statusid = 1
             WHERE a.instid = $instid AND a.date = '$sysdate'
            UNION ALL
              SELECT b.branch,
                    -- b.unit,
                     b.account,
                     b.currency,
                     a.name,
                     0 AS retailbal,
                     SUM (trunc(b.glbal, 2)),
                     SUM (trunc(coalesce (b.calbal, 0), 2)) AS calbal,
                     b.instid
                FROM (SELECT b.branch,
                            -- b.unit,
                             b.account,
                             b.currency,
                             0 AS retailbal,
                             trunc (coalesce (x.obal, 0), 2)
                                + $dailysumsql AS glbal,
                             0  AS calbal,
                             b.instid
                        FROM gl_balance b
                             LEFT JOIN gl_daily_bal c
                                ON     b.instid = c.instid
                                   AND b.account = c.account
                                   AND b.branch = c.branch
                                 --  AND b.unit = c.unit
                                   AND b.currency = c.currency
                                   AND b.year = c.year
                                   AND c.period = $month
                             LEFT JOIN gl_account a
                                ON b.instid = a.instid AND b.account = a.acntno and a.statusid = 1
                             LEFT JOIN x
                                ON     b.instid = x.instid
                                   AND b.account = x.account
                                   AND b.branch = x.branch
                                 --  AND b.unit = x.unit
                                   AND b.currency = x.currency
                                   AND b.year = x.year
                             INNER JOIN
                             (SELECT tmp.*
                                FROM (SELECT a.branch,
                                         --    a.unit,
                                             a.account,
                                             a.currency,
                                             a.instid
                                        FROM gl_balance a
                                       WHERE year = $year AND instid = $instid) tmp
                               ) d
                                ON     b.instid = d.instid
                                   AND b.branch = d.branch
                                 --  AND b.unit = d.unit
                                   AND b.currency = d.currency
                                   AND b.account = d.account
                       WHERE b.instid = $instid AND b.year = $year) b
                     LEFT JOIN gl_account a
                        ON a.instid = b.instid AND b.account = a.acntno and a.statusid = 1
            GROUP BY b.branch,
                   --  b.unit,
                     b.account,
                     b.currency,
                     a.name,
                     b.instid)
       SELECT
            " . ($isall ? "'ALL' AS brchno,
                             'ALL' AS brchname,
                             -- 'ALL' AS unit,
                             "
            :
            "a.brchno,
            coalesce (pb.name, '-') AS brchname,
            -- 'ALL' AS unit,
            ") . "
              a.glsegcode,
              a.curcode,
              coalesce (a.name, '-') AS name,
              SUM (trunc(coalesce (a.retailbal, 0), 2)) AS retailbal,
              SUM (trunc(coalesce (a.glbal, 0), 2)) AS glbal,
             -- 'ALL' AS unitname,
              SUM (trunc(coalesce (a.calbal, 0), 2)) AS calbal
         FROM combined a
              LEFT JOIN GP_inst_branch pb
                 ON     a.instid = pb.instid
                    AND a.brchno = pb.brchno
                    AND pb.statusid = 1
        WHERE pb.isonline = 1 AND a.instid = $instid
        " . ($isall ? "" : "AND a.brchno IN ($brchListSql)") . "
     GROUP BY
     " . ($isall ? "" : "a.brchno, pb.name, ") . "
            a.glsegcode, a.curcode, a.name
     ORDER BY
     " . ($isall ? "" : "a.brchno, pb.name, ") . "
            a.glsegcode, a.curcode
        ";

        /**
         * tr_glretail_bal-c glbal -г татаад байгаа тул хасав
         * coalesce (a.glbal, 0) AS glbal,
         *
         * Үүнийг gl_daily_bal-с glbal татаж чадахгүй байгаа тул хасав
         *                               EXCEPT
         *                     SELECT brchno AS branch,
         *                         --  '0000' AS unit,
         *                          glsegcode AS account,
         *                         curcode AS currency,
         *                        instid
         *                  FROM tr_glretail_bal
         *                WHERE date = '$sysdate' AND instid = $instid
         */
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }
    /**
     * Үлдэгдэл тулгах харах Хураангуй
     */
    public function SelectBalanceCompareSum($sysdate, $instid, $brchno)
    {
        $isall = empty($brchno);

        $brchListSql = '';
        if (!$isall) {
            if (is_array($brchno)) {
                $brchListSql = collect($brchno)
                    ->filter()                  // хоосон элементүүдийг хасна
                    ->unique()                  // давхардал арилгана
                    ->map(fn($b) => "'" . addslashes($b) . "'")
                    ->implode(',');             //  '001','005','010'
            } else {
                $brchListSql = "'" . addslashes($brchno) . "'";
            }
        }
        $sql = "SELECT
     " . ($isall ? "'ALL' AS brchno,
                    'ALL' AS brchname,
                    --'ALL' AS unit,
                    "
            :
            "a.brchno,
            coalesce (pb.name, '-') AS brchname,
            -- 'ALL' AS unit,
                    ") . "
                a.glsegcode,
                a.curcode,
                coalesce (a.name, '-') name,
                SUM (trunc(coalesce (a.retailbal, 0), 2)) retailbal,
                SUM (trunc(coalesce (a.glbal, 0), 2)) glbal,
                -- 'ALL' unitname,
                SUM (trunc(coalesce (calbal, 0), 2)) calbal
        FROM (SELECT a.brchno,
                       -- '0000' AS unit,
                        a.glsegcode,
                        a.curcode,
                        b.name name,
                        trunc(coalesce(a.retailbal, 0), 2) retailbal,
                        -- coalesce(a.retailbal, 0) retailbal,
                        trunc(coalesce(a.glbal, 0), 2) glbal,
                        0  calbal,
                        a.instid
                FROM tr_glretail_bal a
                        LEFT JOIN gl_account b
                        ON a.instid = b.instid AND TRIM (a.glsegcode) = b.acntno and b.statusid = 1
                WHERE a.instid = $instid AND a.date = '$sysdate') a
                LEFT JOIN GP_inst_branch pb
                ON     a.instid = pb.instid
                    AND a.brchno = pb.brchno
                    AND pb.statusid = 1
        WHERE pb.isonline = 1 AND a.instid = $instid
        " . ($isall ? "" : "AND a.brchno IN ($brchListSql)") . "
        GROUP BY
        " . ($isall ? "" : "a.brchno, pb.name, ") . "
        a.glsegcode, a.curcode, a.name
        ORDER BY
        " . ($isall ? "" : "a.brchno, pb.name, ") . "
        a.glsegcode, a.curcode
        ";
        return DB::select(DB::raw($sql));
    }

    /**
     * ЕД Орлого зарлага суурь валютруу хөрвүүлэх
     */
    public function SelectInExBalConvertBaseCur($sysdate, $instid, $brchno, $basecur)
    {
        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;

        $isall = false;
        if (empty($brchno)) {
            $isall = true;
        }

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . "+ trunc(coalesce (a.ct$tmpd, 0), 2) + trunc(coalesce (a.dt$tmpd, 0), 2) ";
        }
        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce (db.ct$tmpd, 0), 2) + trunc(coalesce (db.dt$tmpd, 0), 2) ";
        }

        $sql = "  SELECT
        " . ($isall ? " 'ALL' branch,
                        'ALL' branchname,
                        -- 'ALL' unit,
                        "
            : "
                         a.branch,
                         br.name as branchname,
                         -- '000' as unit,
                       ") . "
                            a.currency,
                            b.type,
                            tp.name typename,
                            a.account,
                            b.name accountname,
                            SUM (
                             trunc(coalesce(a.obal, 0), 2)
                            " . ($month != 1 ? "$monthlysumsql" : "") . "
                            + $dailysumsql) AS balance,
                            r.avgrateend AS currrate,
                            -- 'ALL' unitname
                            a.instid
                    FROM gl_balance a
                            LEFT JOIN gl_account b
                            ON a.instid = b.instid AND a.account = b.acntno and b.statusid = 1
                            LEFT JOIN gl_daily_bal db
                            ON     a.instid = db.instid
                                AND a.branch = db.branch
                               -- AND a.unit = db.unit
                                AND a.account = db.account
                                AND db.currency = a.currency
                                AND db.year = $year
                                AND db.period = $month
                            LEFT JOIN VW_DICT_GP_CONST_056 tp ON tp.value = b.type
                            LEFT JOIN GP_inst_branch br
                            ON     br.instid = a.instid
                                AND br.brchno = a.branch
                                AND br.statusid = 1
                            LEFT JOIN
                            (SELECT h.*, EXTRACT (YEAR FROM h.date) AS year
                            FROM (
                            SELECT curcode AS currency,
                                   MAX (date) ratedate,
                                   instid
                                   FROM tr_cur_rate_hist
                                   WHERE date <= '$sysdate'
                                        AND curcode IS NOT NULL
                                        AND instid = $instid
                                   GROUP BY curcode, instid
                                   ) t
                               INNER JOIN tr_cur_rate_hist h
                               ON  h.instid = t.instid
                               AND h.curcode = t.currency
                               AND h.date = t.ratedate) r
                            ON a.currency = r.curcode AND a.instid = r.instid
                    WHERE   a.instid = $instid
                            AND b.type IN ('4', '5')
                            AND a.currency <> '$basecur'
                            AND a.year = $year
        " . ($isall ? "" : "AND a.branch IN ('$brchno')") . "
                    GROUP BY
        " . ($isall ? "" : "a.branch,
                            br.name,") .
            "
                            a.currency,
                            b.type,
                            tp.name,
                            a.account,
                            b.name,
                            a.instid,
                            r.avgrateend
                    ORDER BY
                            branch,
                            a.currency,
                            b.type,
                            a.account
        ";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }
    /**
     * ЕД Орлого зарлага суурь валютруу хөрвүүлэх гүйлгээнд
     * Дахин судлах өөр SQL орж ирсэн
     */
    public function getInExBalConvertBaseCur($sysdate, $instid, $brchno, $basecur)
    {
        $year = Carbon::parse($sysdate)->year;


        $dailysumsql = "";

        for ($i = 0; $i < 13; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . "trunc (coalesce (a.ct$tmpd, 0), 2) + trunc (coalesce (a.dt$tmpd, 0), 2) ";
        }

        $sql = " SELECT
                    a.branch,
                    a.currency,
                    a.account,
                    trunc (coalesce (a.obal, 0), 2) +
                    $dailysumsql AS bal0,
                    trunc ( c.avgrateend
                    * (trunc (coalesce (a.obal, 0), 2) +
                    $dailysumsql), 2) AS bal1,
                    b.type,
                    d.equivacct
            from gl_balance a
                    left join (SELECT ac.acntno, ac.type, cl.balmoving, ac.instid
                                FROM gl_account ac, gl_account_class cl
                                WHERE ac.class = cl.class AND ac.instid = cl.instid and ac.statusid = 1 and cl.statusid = 1) b
                                ON a.instid = b.instid AND a.account = b.acntno
                    left join
                    (SELECT a.*
                            FROM tr_cur_rate_hist a,
                            ( SELECT curcode AS currency,
                                            MAX (date) ratedate,
                                            instid
                                            FROM tr_cur_rate_hist
                                            WHERE date <= '$sysdate'
                                                    AND curcode IS NOT NULL
                                                    AND instid = $instid
                                            GROUP BY curcode, instid
                                            ) b
                            WHERE a.instid = b.instid
                                    AND a.curcode = b.currency
                                    AND a.date = b.ratedate) c
                            ON a.currency = c.curcode AND a.instid = c.instid
                    LEFT JOIN GP_inst_cur d ON a.instid = d.instid AND a.currency = d.curcode AND d.statusid = 1
            where     a.instid = $instid
                    AND  b.type in ('4', '5')
                    AND trunc (coalesce (a.obal, 0), 2) +
                    $dailysumsql <> 0
                    and a.branch in ('$brchno')
                    and a.currency <> '$basecur'
                    and a.year = $year
            order by 1, 2
        ";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }

    /**
     * Арилжаа хаах жагсаалт
     * gl025200
     */
    public function getExchangeClose($sysdate, $brchno, $instid, $basecur, $spotacnt)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');
        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            if ($i != 1) {
                $monthlysumsql = $monthlysumsql . " + ";
            }
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . " trunc(coalesce(a.ct$tmpd, 0), 2) + trunc(coalesce(a.dt$tmpd, 0), 2) ";
        }

        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce(b.ct$tmpd, 0), 2) + trunc(coalesce(b.dt$tmpd, 0), 2) ";
        }

        $sql = "SELECT t1.brchno,
                    t1.currency,
                    coalesce (t3.avgrateend, 0) AS currrate,
                    SUM (coalesce (t2.balance, 0)) AS spot,
                    SUM (trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)) AS value,
                    SUM (coalesce (t1.balance, 0)) AS equiv,
                    SUM (trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)
                                - coalesce (t1.balance, 0))  AS difference,
                    t1.prof,
                    t1.loss,
                    t1.equivacct
            from (select d.currency,
                            d.brchno,
                            d.instid,
                           -- d.unitno,
                            d.equivacct,
                            d.prof,
                            d.loss,
                            trunc(coalesce(a.obal, 0), 2)
                            " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                            + $dailysumsql AS balance
                    from (select c.curcode currency,
                                    b.brchno,
                                   -- '0000' AS unitno,
                                    c.equivacct,
                                    c.fxprof     prof,
                                    c.fxloss     loss,
                                    b.instid
                            from GP_inst_cur c, GP_inst_branch b
                            where b.brchno = '$brchno' and c.instid = b.instid
                                    AND c.instid = $instid AND c.statusid = 1) d
                            left join gl_balance a
                                on  d.instid = a.instid
                                and d.brchno = a.branch
                                and d.equivacct = a.account
                                and a.currency = '$basecur'
                                and a.branch = '$brchno'
                                and a.year = $year
                            left join gl_daily_bal b
                                on  b.instid = d.instid
                                and b.branch = '$brchno'
                                and d.equivacct = b.account
                                and b.currency = '$basecur'
                                and b.year = $year
                                and b.period = $month) t1
                    left join
                    (select c.curcode currency,
                            a.branch,
                           -- a.unit,
                            a.instid,
                            a.account,
                            trunc(coalesce(a.obal, 0), 2)
                            " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                            + $dailysumsql    as balance
                    from GP_inst_cur c
                            left join gl_balance a
                                on c.instid = a.instid
                                AND c.curcode = a.currency
                                and a.account = '$spotacnt'
                                and a.year = $year
                            left join gl_daily_bal b
                                on  c.instid = b.instid
                                AND a.branch = b.branch
                              --  and a.unit = b.unit
                                and b.account = '$spotacnt'
                                and b.currency = c.curcode
                                and b.year = $year
                                and b.period = $month
                            where c.instid = $instid and c.statusid = 1) t2
                        on     t1.currency = t2.currency
                        and t1.brchno = t2.branch
                        and t1.instid = t2.instid
                       -- and t1.unitno = t2.unit
                    left join
                    (SELECT h.*
                                FROM tr_cur_rate_hist h, (
                                SELECT curcode AS currency,
                                    MAX (date) ratedate,
                                    instid
                                    FROM tr_cur_rate_hist
                                    WHERE date <= '$sysdate'
                                            AND curcode IS NOT NULL
                                            AND instid = $instid
                                    GROUP BY curcode, instid
                                    ) t
                                WHERE h.instid = t.instid
                                AND h.curcode = t.currency
                                AND h.date = t.ratedate) t3
                            ON t1.currency = t3.curcode AND t1.instid = t3.instid
            where   trunc (coalesce (t3.avgrateend, 0) * coalesce (t2.balance, 0), 2)
                    - coalesce (t1.balance, 0) <>
                    0
            group by t1.brchno,
                   -- t1.unitno,
                    t1.currency,
                    t3.avgrateend,
                    t1.prof,
                    t1.loss,
                    t1.equivacct
            order by t1.brchno,
                    -- t1.unitno,
                     t1.currency";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }

    /**
     * Позиц хаах жагсаалт
     * gl026200
     */
    public function getPositionClose($sysdate, $brchno, $instid, $basecur, $spotacnt, $recbrchno)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');
        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            if ($i != 1) {
                $monthlysumsql = $monthlysumsql . " + ";
            }
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . " trunc(coalesce(bal.ct$tmpd, 0), 2) + trunc(coalesce(bal.dt$tmpd, 0), 2) ";
        }


        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce(bal1.ct$tmpd, 0), 2) + trunc(coalesce(bal1.dt$tmpd, 0), 2) ";
        }

        $sql = "SELECT
            bal.branch,
            bal.instid,
            bal.currency,
            trunc(coalesce(bal.obal, 0), 2)
            " . ($month != 1 ? "+ $monthlysumsql" : "") . "
            + $dailysumsql fc,
            trunc (
             (trunc(coalesce (bal.obal, 0), 2)
             " . ($month != 1 ? "+ $monthlysumsql" : "") . "
            + $dailysumsql)
            * r.avgrateend, 2) lc,
            c.equivacct,
            r.avgrateend AS currrate
            FROM gl_balance bal
                    LEFT JOIN gl_daily_bal bal1
                    ON     bal.instid = bal1.instid
                        AND bal.currency = bal1.currency
                        AND bal.branch = bal1.branch
                        -- AND bal.unit = bal1.unit
                        AND bal1.account = '$spotacnt'
                        AND bal1.year = $year
                        AND bal1.period = $month
                    LEFT JOIN GP_inst_cur c
                    ON c.instid = bal.instid AND c.curcode = bal.currency AND c.statusid = 1
                    LEFT JOIN
                    (SELECT h.*
                                FROM  tr_cur_rate_hist h, (
                                SELECT curcode AS currency,
                                    MAX (date) ratedate,
                                    instid
                                    FROM tr_cur_rate_hist
                                    WHERE date <= '$sysdate'
                                            AND curcode IS NOT NULL
                                            AND instid = $instid
                                    GROUP BY curcode, instid
                                    ) t
                                WHERE h.instid = t.instid
                                AND h.curcode = t.currency
                                AND h.date = t.ratedate) r
                    ON bal.currency = r.curcode AND bal.instid = r.instid
            WHERE     bal.instid = $instid
                    AND bal.branch <> '$recbrchno'
                    AND bal.branch = '$brchno'
                    AND bal.account = '$spotacnt'
                    AND trunc(coalesce (bal.obal, 0), 2)
                    " . ($month != 1 ? "+ $monthlysumsql" : "") . "
                        + $dailysumsql <> 0
                    AND bal.year = $year";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }
    /**
     * Гүйлгээ тэнцэл
     */
    public function SelectTransactionBalance($sysdate, $year, $cur, $instid, $brchno, $freq, $period, $day, $shownonebal)
    {

        $monthsumsql = "";
        $monthcurrentdtsql = "";
        $monthcurrentctsql = "";
        $len = 0;
        $startM = 0;
        $oballen = 0;
        $firstday = $sysdate;
        switch ($freq) {
            case 'M':
                $len = $period;
                $oballen = $period;
                $startM = $period - 1;
                if ($day) {
                    $firstday = "$year-$period-$day";
                } else {
                    $firstday = "$year-$period-01";
                }
                break;
            case 'Q':
                switch ($period) {
                    case 1:
                        $len = 3;
                        $startM = 0;
                        $oballen = 0;
                        $sysdate = "$year-03-31";
                        $firstday = "$year-01-01";
                        break;
                    case 2:
                        $len = 6;
                        $startM = 3;
                        $oballen = 4;
                        $sysdate = "$year-06-30";
                        $firstday = "$year-04-01";
                        break;
                    case 3:
                        $len = 9;
                        $startM = 6;
                        $oballen = 7;
                        $sysdate = "$year-09-30";
                        $firstday = "$year-07-01";
                        break;
                    case 4:
                        $len = 12;
                        $startM = 9;
                        $oballen = 10;
                        $sysdate = "$year-12-31";
                        $firstday = "$year-10-01";
                        break;

                    default:
                        # code...
                        break;
                }
                break;
            case 'H':
                switch ($period) {
                    case 1:
                        $len = 6;
                        $startM = 0;
                        $oballen = 0;
                        $sysdate = "$year-06-30";
                        $firstday = "$year-01-01";
                        break;
                    case 2:
                        $len = 12;
                        $startM = 6;
                        $oballen = 7;
                        $sysdate = "$year-12-31";
                        $firstday = "$year-07-01";
                        break;

                    default:
                        # code...
                        break;
                }
                break;
            case 'Y':
                $len = 12;
                $startM = 0;
                $oballen = 0;
                $sysdate = "$year-12-31";
                $firstday = "$year-01-01";
                break;
            default:
                # code...
                break;
        }

        if ($oballen == 0) {
            $monthsumsql = '0';
        }
        for ($i = 0; $i < $oballen; $i++) {

            if ($i != 0 && $i != $oballen - 1) {
                $monthsumsql = $monthsumsql . " + ";
            }

            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            if ($i < $oballen - 1) {
                $monthsumsql = $monthsumsql . " trunc(coalesce (a.ct$tmpd, 0), 2) + trunc(coalesce (a.dt$tmpd, 0), 2) ";
            }
        }

        for ($i = $startM; $i < $len; $i++) {
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            if (empty($monthcurrentdtsql)) {
                $monthcurrentdtsql = " trunc(coalesce(a.dt$tmpd, 0), 2) ";
                $monthcurrentctsql = " trunc(coalesce(a.ct$tmpd, 0), 2) ";
            } else {
                $monthcurrentdtsql = $monthcurrentdtsql . "+ trunc(coalesce(a.dt$tmpd, 0), 2) ";
                $monthcurrentctsql = $monthcurrentctsql . "+ trunc(coalesce(a.ct$tmpd, 0), 2) ";
            }
        }

        $isall = empty($brchno);        // null, '', []  ➜  бүх салбар
        $branchListSql = '';

        if (!$isall) {
            if (is_array($brchno)) {
                $branchListSql = collect($brchno)
                    ->filter()                  // хоосон элементүүдийг хасна
                    ->unique()                  // давхардал арилгана
                    ->map(fn($b) => "'" . addslashes($b) . "'")
                    ->implode(',');             //  '001','005','010'
            } else {
                $branchListSql = "'" . addslashes($brchno) . "'";
            }
        }

        $monthsumsql = $monthsumsql . " + 0 ";

        $daysql = "LEFT JOIN (  SELECT branch,
                                instid,
                                account,
                                currency,
                                SUM (CASE WHEN amount > 0 THEN amount ELSE 0 END)
                                    dt,
                                SUM (CASE WHEN amount > 0 THEN 0 ELSE amount END)
                                    ct,
                                SUM (amount)
                                    net,
                                SUM (
                                    CASE
                                        WHEN amount > 0 AND day = $day THEN amount
                                        ELSE 0
                                    END)
                                    dtDay,
                                SUM (
                                    CASE
                                        WHEN amount < 0 AND day = $day THEN amount
                                        ELSE 0
                                    END)
                                    ctDay,
                                SUM (CASE WHEN day = $day THEN amount ELSE 0 END)
                                    netDay
                        FROM gl_transaction
                        WHERE   instid = $instid
                                AND  year = $year
                                AND period = $period
                                AND day <= $day
                                AND journal NOT LIKE 'BB%'
                                AND journal NOT LIKE 'GO%'
                        GROUP BY branch,
                                account,
                                currency,
                                instid) t
                        ON     a.branch = t.branch
                            AND a.instid = t.instid
                            AND a.account = t.account
                            AND a.currency = t.currency";

        $sql = "SELECT
        " . ($isall ? " 'ALL' branch,
            'ALL' branchname,"
            : " t1.branch,
            br.name  branchname,") . "
            -- 'all' unit,
            -- 'all' unitname,
            'all' currency,
            t1.asset,
            t1.type,
            tp.name typename,
            t1.class::TEXT AS class,
            cl.name classname,
            t1.account,
            an.name accountname,
            trunc (SUM (obal * coalesce (f.avgrate, 1)), 2) * asset obal,
            trunc (SUM (debit * coalesce (r.avgrate, 1)), 2) * asset debit,
            trunc (SUM (credit * coalesce (r.avgrate, 1)), 2) * asset credit,
            trunc (SUM ((debit + credit) * coalesce (r.avgrate, 1)), 2) * asset net,
            trunc (SUM ((obal + debit + credit) * coalesce (r.avgrate, 1)), 2) * asset cbal
          FROM (SELECT a.instid,
                       a.branch,
                       a.unit,
                       a.currency,
                       CASE WHEN b.type IN ('1', '5') THEN 1 ELSE -1 END asset,
                       b.type,
                       b.class,
                       a.account,
                       trunc(coalesce (a.obal, 0), 2) " . ((empty($monthsumsql) && empty($day)) ? "" : "+") . " $monthsumsql " . ($day ? " + trunc(coalesce (t.net, 0), 2) - trunc(coalesce (t.netDay, 0), 2)" : "") . " AS obal,
                        + " . ($day ? " trunc(coalesce (t.dtDay, 0), 2)" : "$monthcurrentdtsql") . " AS debit,
                        + " . ($day ? " trunc(coalesce (t.ctDay, 0), 2)" : "$monthcurrentctsql") . " AS credit
                  FROM gl_balance a
                  " . ($day ? $daysql : "") . "
                       LEFT JOIN gl_account b
                          ON a.account = b.acntno AND a.instid = b.instid and b.statusid = 1
                 WHERE     a.instid = $instid
                       AND b.type::INT < 6
                       AND a.year = $year
                " . ($isall ? "" : "AND a.branch IN ($branchListSql)") . "
                " . ($cur ? "AND a.currency IN ('$cur')" : "") . "
                " . ($shownonebal ? "" : "AND ( trunc(coalesce (a.obal, 0), 2) " . (empty($monthsumsql) ? "" : "+ $monthsumsql") . " <> 0
                OR $monthcurrentdtsql <> 0
                OR $monthcurrentctsql <> 0)") . "
                       ) t1
               LEFT JOIN GP_inst_branch br
                  ON br.brchno = t1.branch AND t1.instid = br.instid
               LEFT JOIN gl_account_class cl
                  ON cl.class = t1.class AND t1.instid = cl.instid and cl.statusid = 1
               LEFT JOIN gl_account an
                  ON an.acntno = t1.account AND t1.instid = an.instid and an.statusid = 1
               LEFT JOIN vw_dict_GP_const_056 tp ON tp.value = an.type
               LEFT JOIN
                (SELECT r.*
                    FROM tr_cur_rate_hist r,
                            (  SELECT curcode AS currency, MAX (date) ratedate, instid
                                FROM tr_cur_rate_hist
                                WHERE     date <= '$sysdate'
                                    AND curcode IS NOT NULL
                                    AND instid = $instid
                            GROUP BY curcode, instid) m
                    WHERE m.ratedate = r.date AND m.currency = r.curcode) r
                  ON t1.currency = r.curcode AND t1.instid = r.instid
               LEFT JOIN
                (SELECT r.*
                    FROM tr_cur_rate_hist r,
                            (  SELECT curcode AS currency, MAX (date) ratedate, instid
                                FROM tr_cur_rate_hist
                                WHERE     date <= '$firstday'::date - 1
                                    AND curcode IS NOT NULL
                                    AND instid = $instid
                            GROUP BY curcode, instid) m
                    WHERE m.ratedate = r.date AND m.currency = r.curcode) f
                  ON t1.currency = f.curcode AND t1.instid = f.instid
      GROUP BY
      " . ($isall ? "" : "t1.branch,
                          br.name,") . "
      " . ($cur ? "t1.currency," : "") . "
               t1.asset,
               t1.type,
               tp.name,
               t1.class,
               cl.name,
               t1.account,
               an.name
      ORDER BY branch,
               t1.type,
               class,
            --    unit,
               currency,
               t1.asset,
               t1.account
        ";
        // Log::debug($firstday);
        // Log::debug($sysdate);
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }
    /**
     * Үлдэгдэл тэнцэл
     */
    public function SelectBalance($sysdate, $year, $instid, $brchno, $period, $day, $shownonebal)
    {
        $monthsumsql = "";

        for ($i = 0; $i < $period; $i++) {
            if ($day) {
                if ($i != 0 && $i < $period - 1 && $i != $period) {
                    $monthsumsql = $monthsumsql . " + ";
                }
            } else {
                if ($i != 0 && $i != $period) {
                    $monthsumsql = $monthsumsql . " + ";
                }
            }

            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            if ($day) {
                if ($i == 0) {
                    $monthsumsql = $monthsumsql . "
                    trunc(coalesce (t.net, 0), 2) - trunc(coalesce (t.netDay, 0), 2) +
                    trunc(coalesce (t.dtDay, 0), 2) + trunc(coalesce (t.ctDay, 0), 2) +
                     ";
                }
                if ($i < $period - 1) {
                    $monthsumsql = $monthsumsql . "
                trunc(coalesce (a.ct$tmpd, 0), 2) + trunc(coalesce (a.dt$tmpd, 0), 2)
                 ";
                }
            } else {
                $monthsumsql = $monthsumsql . " trunc(coalesce (a.ct$tmpd, 0), 2) + trunc(coalesce (a.dt$tmpd, 0), 2) ";
            }
        }

        $isall = empty($brchno);        // null, '', []  ➜  бүх салбар
        $branchListSql = '';

        if (!$isall) {
            if (is_array($brchno)) {
                $branchListSql = collect($brchno)
                    ->filter()                  // хоосон элементүүдийг хасна
                    ->unique()                  // давхардал арилгана
                    ->map(fn($b) => "'" . addslashes($b) . "'")
                    ->implode(',');             //  '001','005','010'
            } else {
                $branchListSql = "'" . addslashes($brchno) . "'";
            }
        }
        // Log::debug($monthsumsql);
        $monthsumsql = $monthsumsql . " + 0 ";

        /*
        * Үлдэгдэл тэнцэл дээр өдөр оруулж өгсөн үед тухайн өдөр хүртэлх дүнгээ тооцож олно.
        * Example: period = 12; day = 20 байх юм бол 11 сарыг дуустал бүх дүнгээ нэмээд 12 сарын 20н хүртэлх гүйлгээний дүнг олно
        */
        $daysql = "LEFT JOIN (  SELECT branch,
                                        instid,
                                        account,
                                        currency,
                                        SUM (CASE WHEN amount > 0 THEN amount ELSE 0 END)
                                            dt,
                                        SUM (CASE WHEN amount > 0 THEN 0 ELSE amount END)
                                            ct,
                                        SUM (amount)
                                            net,
                                        SUM (
                                            CASE
                                                WHEN amount > 0 AND day = $day THEN amount
                                                ELSE 0
                                            END)
                                            dtDay,
                                        SUM (
                                            CASE
                                                WHEN amount < 0 AND day = $day THEN amount
                                                ELSE 0
                                            END)
                                            ctDay,
                                        SUM (CASE WHEN day = $day THEN amount ELSE 0 END)
                                            netDay
                                FROM gl_transaction
                                WHERE   instid = $instid
                                        AND  year = $year
                                        AND period = $period
                                        AND day <= $day
                                        AND journal NOT LIKE 'BB%'
                                        AND journal NOT LIKE 'GO%'
                                GROUP BY branch,
                                        account,
                                        currency,
                                        instid) t
                                ON     a.branch = t.branch
                                    AND a.instid = t.instid
                                    AND a.account = t.account
                                    AND a.currency = t.currency";

        $sql = "SELECT
        " . ($isall ? " 'ALL' branch, 'ALL' branchname,"
            : " a.branch, p.name  branchname,") . "
        --  'all' unit,
        --  'all' unitname,
        b.type,
        tp.name typename,
        b.class::TEXT class,
        c.name AS classname,
        substr (a.account, 1, 20) AS account,
        a.currency,
        b.name,
        coalesce (r.avgrate, 1) AS currrate,
        SUM(
            trunc(trunc(coalesce(a.obal, 0), 2) + $monthsumsql, 2)
            ) AS cbal,
        SUM(
            round(
                (trunc(coalesce (a.obal, 0), 2) + $monthsumsql)
                * coalesce (r.avgrate, 1), 2)
           ) AS value
   FROM (SELECT *
           FROM gl_balance
          WHERE year = $year AND instid = $instid) a
          " . ($day ? $daysql : "") . "
        LEFT JOIN gl_account b ON a.account = b.acntno AND a.instid = b.instid AND b.statusid = 1
        LEFT JOIN GP_inst_branch p
           ON a.branch = p.brchno AND a.instid = p.instid
        LEFT JOIN gl_account_class c
           ON b.class = c.class AND a.instid = c.instid and c.statusid = 1
        LEFT JOIN
        (SELECT r.*
           FROM tr_cur_rate_hist r,
                (  SELECT curcode AS currency, MAX (date) ratedate, instid
                     FROM tr_cur_rate_hist
                    WHERE     date <= '$sysdate'
                          AND curcode IS NOT NULL
                          AND instid = $instid
                 GROUP BY curcode, instid) m
          WHERE m.ratedate = r.date AND m.currency = r.curcode) r
           ON a.currency = r.curcode AND a.instid = r.instid
        LEFT JOIN vw_dict_GP_const_056 tp ON tp.value = b.type
  WHERE a.instid = $instid
        AND a.year = $year
        AND b.type::INT < 6
        " . ($isall ? "" : "AND a.branch IN ($branchListSql)") . "
        " . ($shownonebal ? "" : "AND  trunc(coalesce(a.obal, 0), 2) + $monthsumsql <> 0") . "
GROUP BY
        " . ($isall ? "" : "a.branch, p.name,") . "
        b.type,
        b.class,
        a.account,
        a.currency,
        r.avgrate,
        b.name,
        tp.name,
        c.name
ORDER BY
        branch,
        --  unit,
        b.type,
        class,
        c.name,
        a.account,
        a.currency
        ";
        // Log::debug($sysdate);
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }


    /**
     * Readonly intraday balance report.
     *
     * Builds the GL balance from existing GL balances plus transactions that would be
     * pulled from retail/journal sources, without writing to gl_transaction,
     * gl_balance, gl_daily_bal or tr_glretail_entry.
     */
    public function SelectIntradayBalance($sysdate, $year, $instid, $brchno, $period, $day, $shownonebal, $basecur, $spotacnt, $suspacnt)
    {
        $targetDate = Carbon::parse($sysdate)->format('Y-m-d');
        $targetMonth = (int) Carbon::parse($targetDate)->month;
        if (empty($period)) {
            $period = $targetMonth;
        }
        $period = (int) $period;
        $year = (int) $year;

        $monthBalanceSql = "trunc(coalesce(a.obal, 0), 2)";
        for ($i = 1; $i <= $period; $i++) {
            $tmpd = $i < 10 ? "0" . $i : $i;
            $monthBalanceSql .= " + trunc(coalesce(a.ct$tmpd, 0), 2) + trunc(coalesce(a.dt$tmpd, 0), 2)";
        }

        $isall = empty($brchno);
        $branchFilter = '';

        if (!$isall) {
            if (is_array($brchno)) {
                $branchListSql = collect($brchno)
                    ->filter()
                    ->unique()
                    ->map(fn($b) => "'" . addslashes($b) . "'")
                    ->implode(',');
            } else {
                $branchListSql = "'" . addslashes($brchno) . "'";
            }
            $branchFilter = "AND movement.branch IN ($branchListSql)";
        }

        $nonZeroFilter = $shownonebal ? "" : "HAVING round(SUM(movement.amount), 2) <> 0";

        $sql = "WITH
        base_balance AS (
            SELECT
                a.branch,
                a.account,
                a.currency,
                $monthBalanceSql AS amount,
                a.instid
            FROM gl_balance a
            WHERE a.instid = $instid
              AND a.year = $year
        ),
        raw_entry AS (
            SELECT
                a.jrno,
                a.jritemno,
                a.txndate,
                a.acntbrchno AS branch,
                a.curcode AS currency,
                a.gl || trim(coalesce(a.segcode, '00')) AS account,
                trunc(a.txnamount, 2) AS amount,
                trunc(coalesce(a.baseamount, 0), 2) AS baseamount,
                a.gl || trim(coalesce(a.segcode, '00')) AS glsegcode,
                a.instid
            FROM tr_glretail_entry a
            WHERE a.instid = $instid
              AND a.corr IN (0, 2)
              AND a.txndate = '$targetDate'
              AND coalesce(a.flags, 0) = 0
              AND coalesce(a.mark, 0) <> 1

            UNION ALL

            SELECT
                j.jrno,
                j.jritemno,
                j.txndate,
                j.acntbrchno AS branch,
                j.curcode AS currency,
                j.gl || trim(coalesce(j.segcode, '00')) AS account,
                trunc(j.txnamount * CASE j.sign WHEN '+' THEN -1 ELSE 1 END, 2) AS amount,
                trunc(coalesce(j.baseamount, 0) * CASE j.sign WHEN '+' THEN -1 ELSE 1 END, 2) AS baseamount,
                j.gl || trim(coalesce(j.segcode, '00')) AS glsegcode,
                j.instid
            FROM tr_journal j
            WHERE j.instid = $instid
              AND j.corr IN (0, 2)
              AND j.txndate = '$targetDate'
              AND coalesce(j.mark, 0) <> 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM tr_glretail_entry e
                    WHERE e.instid = j.instid
                      AND e.txndate = j.txndate
                      AND e.jrno = j.jrno
                      AND e.jritemno = j.jritemno
              )
        ),
        virtual_pull AS (
            SELECT
                branch,
                account,
                currency,
                trunc(SUM(amount), 2) AS amount,
                instid
            FROM raw_entry
            GROUP BY branch, account, currency, instid

            UNION ALL

            SELECT
                branch,
                '$suspacnt' AS account,
                currency,
                -trunc(SUM(amount), 2) AS amount,
                instid
            FROM raw_entry
            GROUP BY branch, currency, instid
            HAVING trunc(SUM(amount), 2) <> 0

            UNION ALL

            SELECT
                r.branch,
                c.equivacct AS account,
                '$basecur' AS currency,
                trunc(SUM(r.baseamount), 2) AS amount,
                r.instid
            FROM raw_entry r
                LEFT JOIN GP_inst_cur c
                    ON r.instid = c.instid
                   AND r.currency = c.curcode
                   AND c.statusid = 1
            WHERE r.glsegcode = '$spotacnt'
            GROUP BY r.branch, c.equivacct, r.instid
        ),
        movement AS (
            SELECT * FROM base_balance
            UNION ALL
            SELECT * FROM virtual_pull
        )
        SELECT
            " . ($isall ? "'ALL' branch, 'ALL' branchname," : "movement.branch, br.name branchname,") . "
            b.type,
            tp.name typename,
            b.class::TEXT class,
            c.name AS classname,
            substr(movement.account, 1, 20) AS account,
            movement.currency,
            b.name,
            coalesce(r.avgrate, 1) AS currrate,
            SUM(trunc(coalesce(movement.amount, 0), 2)) AS cbal,
            SUM(round(trunc(coalesce(movement.amount, 0), 2) * coalesce(r.avgrate, 1), 2)) AS value
        FROM movement
            LEFT JOIN gl_account b
                ON movement.account = b.acntno
               AND movement.instid = b.instid
               AND b.statusid = 1
            LEFT JOIN GP_inst_branch br
                ON movement.branch = br.brchno
               AND movement.instid = br.instid
               AND br.statusid = 1
            LEFT JOIN gl_account_class c
                ON b.class = c.class
               AND movement.instid = c.instid
               AND c.statusid = 1
            LEFT JOIN (
                SELECT r.*
                FROM tr_cur_rate_hist r,
                     (
                        SELECT curcode AS currency, MAX(date) ratedate, instid
                        FROM tr_cur_rate_hist
                        WHERE date <= '$targetDate'
                          AND curcode IS NOT NULL
                          AND instid = $instid
                        GROUP BY curcode, instid
                     ) m
                WHERE m.ratedate = r.date
                  AND m.currency = r.curcode
                  AND m.instid = r.instid
            ) r
                ON movement.currency = r.curcode
               AND movement.instid = r.instid
            LEFT JOIN vw_dict_GP_const_056 tp ON tp.value = b.type
        WHERE movement.instid = $instid
          AND b.type::INT < 6
          $branchFilter
        GROUP BY
            " . ($isall ? "" : "movement.branch, br.name,") . "
            b.type,
            b.class,
            movement.account,
            movement.currency,
            r.avgrate,
            b.name,
            tp.name,
            c.name
        $nonZeroFilter
        ORDER BY
            branch,
            b.type,
            class,
            c.name,
            account,
            movement.currency";

        return DB::select(DB::raw($sql));
    }

    /**
     * ЕД Орлого зарлага хаалтын гүйлгээнд
     */

    public function SelectInExBalSettle($sysdate, $instid, $brchno, $curcode, $type)
    {
        $year = Carbon::parse($sysdate)->year;
        $month = Carbon::parse($sysdate)->month;
        $day = Carbon::parse($sysdate)->day;

        $isallbrchno = false;
        if (empty($brchno)) {
            $isallbrchno = true;
        }
        $isalltype = false;
        if (empty($type)) {
            $isalltype = true;
        }
        $isallcurcode = false;
        if (empty($curcode)) {
            $isallcurcode = true;
        }

        $dailysumsql = "";
        $monthlysumsql = "";

        for ($i = 1; $i < $month; $i++) {
            $tmpd = $i;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $monthlysumsql = $monthlysumsql . "+ trunc(coalesce(a.ct$tmpd, 0), 2) + trunc(coalesce(a.dt$tmpd, 0), 2) ";
        }
        for ($i = 0; $i < $day; $i++) {
            if ($i != 0) {
                $dailysumsql = $dailysumsql . " + ";
            }
            $tmpd = $i + 1;
            if ($tmpd < 10) {
                $tmpd = "0" . $tmpd;
            }
            $dailysumsql = $dailysumsql . " trunc(coalesce (db.ct$tmpd, 0), 2) + trunc(coalesce(db.dt$tmpd, 0), 2) ";
        }

        $sql = "  SELECT
                            a.branch,
                            br.name as branchname,
                            a.currency,
                            b.type,
                            tp.name typename,
                            a.account,
                            b.name accountname,
                            trunc(coalesce (a.obal, 0), 2)
                            " . ($month != 1 ? "$monthlysumsql" : "") . "
                            + ($dailysumsql) AS amount,
                            a.instid
                    FROM gl_balance a
                            LEFT JOIN gl_account b
                            ON a.instid = b.instid AND a.account = b.acntno and b.statusid = 1
                            LEFT JOIN gl_daily_bal db
                            ON     a.instid = db.instid
                                AND a.branch = db.branch
                                AND a.account = db.account
                                AND db.currency = a.currency
                                AND db.year = $year
                                AND db.period = $month
                            LEFT JOIN VW_DICT_GP_CONST_056 tp ON tp.value = b.type
                            LEFT JOIN GP_inst_branch br
                            ON     br.instid = a.instid
                                AND br.brchno = a.branch
                                AND br.statusid = 1
                    WHERE   a.instid = $instid
                            AND a.year = $year
                            AND (trunc(coalesce (a.obal, 0), 2)
                            " . ($month != 1 ? "$monthlysumsql" : "") . " + $dailysumsql) != 0
                            " . ($isallbrchno ? "" : "AND a.branch IN ('$brchno')") . "
                            " . ($isalltype ? "AND b.type IN ('4', '5')" : "AND b.type IN ('$type')") . "
                            " . ($isallcurcode ? "" : "AND a.currency IN ('$curcode')") . "

                    ORDER BY
                            a.branch,
                            b.type,
                            a.currency,
                            a.account
        ";
        // Log::debug($sql);
        return DB::select(DB::raw($sql));
    }
}
