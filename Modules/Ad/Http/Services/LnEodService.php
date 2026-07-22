<?php

namespace Modules\Ad\Http\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ln\Entities\LnAccount;
use Modules\Ln\Entities\LnAccountHist;
use Modules\Ln\Entities\LnMor;
use Modules\Ln\Entities\LnMorHistMonthly;
use Modules\Tr\Entities\DpHoldTxn;

class LnEodService
{
    private function effectivePrincipalDueSql($accountAlias = 'a', $productAlias = 'p')
    {
        return "(CASE
            WHEN {$accountAlias}.dueprinc < 0 THEN 0
            WHEN COALESCE({$productAlias}.minprincdueamount, 0) > 0
                AND {$accountAlias}.dueprinc < COALESCE({$productAlias}.minprincdueamount, 0)
            THEN 0
            ELSE {$accountAlias}.dueprinc
        END)";
    }

    /**
     * Ангилал шилжүүлэх дансны жагсаалт авах
     *
     * @return array
     */
    public function getClsAccount($sysdate, $lastitem, $instid)
    {
        $effectiveDuePrinc = $this->effectivePrincipalDueSql('a', 'p');
        $sql = "SELECT t.*,
                        CASE
                        WHEN t.autoclstype = 0
                        THEN
                            CASE
                                WHEN t.autoclsduetype = 0
                                THEN
                                    CASE
                                    WHEN t.duedate > t.bintduedate
                                    THEN
                                        CASE
                                            WHEN t.duedate > t.cintduedate THEN t.duedate
                                            ELSE t.cintduedate
                                        END
                                    WHEN t.cintduedate > t.bintduedate
                                    THEN
                                        t.cintduedate
                                    ELSE
                                        t.bintduedate
                                    END
                                WHEN t.autoclsduetype = 1
                                THEN
                                    t.duedate
                                WHEN t.autoclsduetype = 2
                                THEN
                                    CASE
                                    WHEN t.cintduedate > t.bintduedate THEN t.cintduedate
                                    ELSE t.bintduedate
                                    END
                                ELSE
                                    0
                            END
                        ELSE
                            t.duedate
                        END AS duedays
                FROM (SELECT a.acntno,
                                a.curcode,
                                a.brchno,
                                a.clscode,
                                a.name,
                                a.princbal,
                                p.autoclsduetype,
                                p.autoclstype,
                                :txnDate::date - COALESCE (getduedate (a.acntno, $effectiveDuePrinc, :txnDate::date, :instid), :txnDate::date)
                                AS duedate,
                                :txnDate::date - COALESCE (
                                    getbintduedate (a.acntno,
                                                    a.capbint + COALESCE (a.ctacntno, 0),
                                                    :txnDate::date,
                                                    :instid),
                                    :txnDate::date)
                                AS bintduedate,
                                :txnDate::date - COALESCE (
                                    getcintduedate (a.acntno,
                                                    a.capcint + COALESCE (a.ctcomacntno, 0),
                                                    :txnDate::date,
                                                    :instid),
                                    :txnDate::date)
                                AS cintduedate,
                                (CASE WHEN COALESCE(a.comint2cap, 0) + COALESCE(a.adjcint2cap, 0) < 0
                                    THEN COALESCE(a.comint2cap, 0) + COALESCE(a.adjcint2cap, 0)
                                    ELSE 0
                                END)
                                +
                                (CASE WHEN COALESCE(a.baseint2cap, 0) + COALESCE(a.adjbint2cap, 0) < 0
                                    THEN COALESCE(a.baseint2cap, 0) + COALESCE(a.adjbint2cap, 0)
                                    ELSE 0
                                END)
                                +
                                (CASE WHEN COALESCE(a.fineint2cap, 0) + COALESCE(a.adjfint2cap, 0) < 0
                                    THEN COALESCE(a.fineint2cap, 0) + COALESCE(a.adjfint2cap, 0)
                                    ELSE 0
                                END)
                                + COALESCE (a.ctacntno, 0)
                                + COALESCE (a.ctcomacntno, 0)
                                + COALESCE (a.ctfineacntno, 0)
                                + COALESCE (a.capbint, 0)
                                + COALESCE (a.capcint, 0)
                                + COALESCE (a.capfint, 0)
                                AS capbint,
                                a.dueprinc,
                                a.dueint,
                                a.statusid,
                                a.instid
                        FROM ln_account a
                                INNER JOIN ln_account_type p ON a.prodcode = p.prodcode and a.instid = p.instid
                        WHERE     p.autocls = 1
                                AND p.autoclstype = 0
                                AND a.autocls = 0
                                AND a.statusid > 2
                                AND a.statusid <= 8
                                AND a.statusid != 5
                                AND a.instid = :instid) t ";
        // DB::enableQueryLog();
        // Log::debug([$sysdate, $lastitem, $instid]);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " where t.acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by t.acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'txnDate' => $sysdate,
            'instid' => $instid
        ]);
        // Log::debug(DB::getQueryLog());
        // DB::disableQueryLog();
        return $results;
    }

    public function getNewCls($days, $clscodes)
    {
        $ret = 0;
        foreach ($clscodes as $clscode) {
            if ($clscode->value_add1 <= $days) {
                return +$clscode->value;
            }
        }
        return $ret;
    }

    public function calcLNDailyRate($sysdate, $lastitem, $instid)
    {
        $sysdate = new Carbon($sysdate);
        $minus = " 0 ";
        $multp = " 1 ";
        $day31 = false;
        $fine_multp = " 1 ";
        if ($sysdate->day == 31) {
            $day31 = true;
            $minus = " CASE WHEN p.intdayoption = 0 THEN 0 ELSE a.prevprincbal END "; // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол өмнөх өдрийн үлдэгдлийг хасна
            $multp = " 1 ";  // аль ч аргын хувьд ижил
            $fine_multp = " CASE WHEN p.intdayoption = 0 THEN 1 ELSE 0 END ";
        } else if ($sysdate->copy()->daysInMonth == 28) {
            $minus = " 0 "; // аль ч аргын хувьд ижил
            $multp = " CASE WHEN p.intdayoption = 0 THEN 1 ELSE 3 END ";  // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол 3р үржүүлнэ
            $fine_multp = " CASE WHEN p.intdayoption = 0 THEN 1 ELSE 3 END ";
        } else if ($sysdate->copy()->daysInMonth == 29) {
            $minus = " 0 "; // аль ч аргын хувьд ижил
            $multp = " CASE WHEN p.intdayoption = 0 THEN 1 ELSE 2 END ";  // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол 2р үржүүлнэ
            $fine_multp = " CASE WHEN p.intdayoption = 0 THEN 1 ELSE 2 END ";
        }

        $digitCount = CoreService::getInstGp($instid, 'DigitCount');
        $effectiveDuePrinc = $this->effectivePrincipalDueSql('a', 'p');

        $sql = "SELECT a.tmp_baseintdaily,
                    a.baseroundint,
                    a.fineroundint,
                    a.comroundint,
                    a.finecomroundint,
                    a.baseintdaily,
                    a.comintdaily,
                    a.fineintdaily,
                    a.finecomintdaily,
                    a.dueamount,
                    a.comamount,
                    p.intdayoption,
                    a.acntno,
                    a.brchno,
                    round(
                        (
                            case p.capmethod
                                when 1 then a.princbal
                                when 2 then (a.princbal + a.capbint)
                            end
                            - $minus
                        ) * $multp * a.intrate / p.yeardays / 100, :digit
                    ) as newbaseint,
                    case
                        when p.prodtype = 2 then
                            case
                                when a.comdate + (p.comgrace) <= :sysdate
                                        and a.redrawlimit >= coalesce(a.linebal, 0)
                                        and a.enddate > :sysdate then
                                    a.redrawlimit - coalesce(a.linebal, 0)
                                else
                                    0
                            end - $minus
                        else
                            case
                                when a.begdate + (p.comgrace) <= :sysdate
                                        and p.redraw = 0
                                        and a.approvamount >= a.advamount
                                        and a.enddate > :sysdate then
                                    " . ($day31 ? "case
                                        when p.intdayoption = 0 then
                                            a.approvamount - a.advamount
                                        else
                                            (case
                                                when a.prevprincbal - a.princbal < 0 then
                                                    a.prevprincbal - a.princbal
                                                else
                                                    0
                                                end)
                                    end" : " a.approvamount - a.advamount ") . "
                                when a.comdate + (p.comgrace) <= :sysdate
                                        and p.redraw = 1
                                        and a.redrawlimit >= a.princbal
                                        and a.enddate > :sysdate then
                                    " . ($day31 ? "case
                                        when p.intdayoption = 0 then
                                            a.redrawlimit - a.princbal
                                        else
                                            a.prevprincbal - a.princbal end" : " a.redrawlimit - a.princbal ") . "
                                            ELSE
                                    0
                            END
                    end as newcomamount,
                    case
                        when p.prodtype = 2 then
                            case
                                when coalesce(a.arreardateint, a.arreardate) + (p.finegrace) <= :sysdate then
                                    case
                                        when p.finemethod = 1
                                                and p.minarrears2fine <= $effectiveDuePrinc then
                                            $effectiveDuePrinc
                                        when p.finemethod = 2
                                                and p.minarrears2fine <= $effectiveDuePrinc
                                                + (case when a.dueint < 0 then 0 else a.dueint end) then
                                            $effectiveDuePrinc
                                            + (case when a.dueint < 0 then 0 else a.dueint end)
                                        when p.finemethod = 3
                                                and p.minarrears2fine <= (case when a.dueint < 0 then 0 else a.dueint end) then
                                            (case when a.dueint < 0 then 0 else a.dueint end)
                                        when p.finemethod = 4
                                                and p.minarrears2fine <= $effectiveDuePrinc
                                                + (case when a.dueint < 0 then 0 else a.dueint end) then
                                            a.linebal
                                        else
                                            0
                                    end
                                else
                                    0
                            end
                        else
                            case
                                when coalesce(a.arreardateint, a.arreardate) + (p.finegrace) <= :sysdate
                                        or (p.finecondition = 0 and a.enddate <= :sysdate) then
                                    case
                                        when p.finemethod = 1
                                                and p.minarrears2fine <= $effectiveDuePrinc then
                                            $effectiveDuePrinc
                                        when p.finemethod = 2
                                                and p.minarrears2fine <= $effectiveDuePrinc
                                                + (case when a.dueint < 0 then 0 else a.dueint end) then
                                            $effectiveDuePrinc
                                            + (case when a.dueint < 0 then 0 else a.dueint end)
                                        when p.finemethod = 3
                                                and p.minarrears2fine <= (case when a.dueint < 0 then 0 else a.dueint end) then
                                            (case when a.dueint < 0 then 0 else a.dueint end)
                                        when p.finemethod = 4
                                                and p.minarrears2fine <= $effectiveDuePrinc
                                                + (case when a.dueint < 0 then 0 else a.dueint end) then
                                            a.princbal
                                        else
                                            0
                                    end
                                else
                                    0
                            end
                    end as newdueamount,
                    round(
                        case
                            when a.arreardatecom + case when a.enddate <= :sysdate then (p.finegrace) else 0 end <= :sysdate
                                    and p.minarrears2fine <= a.duecom
                                    and p.comfineintoption = 1
                                    and (p.finemethod = 2 or p.finemethod = 3) then
                                case p.finetype
                                    when 'R' then
                                        a.duecom * p.finerate / p.yeardays / 100
                                    when 'F' then
                                        p.fineflat
                                    when 'Z' then
                                        a.duecom * (p.comrate * p.finerate / 100) / p.yeardays / 100
                                    else
                                        0
                                end
                            else
                                0
                        end, :digit
                    ) as newfinecomint,
                    " . $fine_multp . " as fine_multp
                from ln_account a
                inner join ln_account_type p on a.prodcode = p.prodcode and a.instid = p.instid
                where (a.statusid > 1 and a.statusid < 9) and a.instid = :instid";
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'digit' => $digitCount,
            'sysdate' => $sysdate,
            'instid' => $instid
        ]);

        return $results;
    }

    public function calcLNDailyRateTier($sysdate, $lastitem, $instid)
    {
        $sysdate = new Carbon($sysdate);
        $fine_minus = " 0 ";
        if ($sysdate->day == 31) {
            $fine_minus = " CASE WHEN P.IntDayOption = 0 THEN 0 ELSE A.PrevDueAmount END ";
        } else if ($sysdate->copy()->daysInMonth == 28) {
            $fine_minus = " 0 ";
        } else if ($sysdate->copy()->daysInMonth == 29) {
            $fine_minus = " 0 ";
        }
        $fine_multp = " 1 ";
        $multp = " 1 ";
        if ($sysdate->day == 31) {
            $multp = " 1 ";  // аль ч аргын хувьд ижил
            $fine_multp = " CASE WHEN intdayoption = 0 THEN 1 ELSE 1 END "; // хэрэв тэнцүү хоногт сараар хүү тооцдог бол нэмэгдүүлсэн хүү тооцохгүй гэсэн үг
        } else if ($sysdate->copy()->daysInMonth == 28) {
            $multp = " CASE WHEN IntDayOption = 0 THEN 1 ELSE 3 END ";  // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол 3р үржүүлнэ
            $fine_multp = " CASE WHEN intdayoption = 0 THEN 1 ELSE 3 END "; // хэрэв тэнцүү хоногт сараар хүү тооцдог бол 3р үржүүлнэ
        } else if ($sysdate->copy()->daysInMonth == 29) {
            $multp = " CASE WHEN intdayoption = 0 THEN 1 ELSE 2 END "; // хэрэв тэнцүү хоногт сараар хүү тооцдог бол 2р үржүүлнэ
            $fine_multp = " CASE WHEN intdayoption = 0 THEN 1 ELSE 2 END "; // хэрэв тэнцүү хоногт сараар хүү тооцдог бол 2р үржүүлнэ
        }

        $digitCount = CoreService::getInstGp($instid, 'DigitCount');

        $sql = "SELECT a.baseroundint,
                    a.fineroundint,
                    a.comroundint,
                    a.finecomroundint,
                    a.baseintdaily,
                    a.comintdaily,
                    a.fineintdaily,
                    a.finecomintdaily,
                    p.intdayoption,
                    a.tmp_comintdaily,
                    a.tmp_fineintdaily,
                    a.acntno,
                    a.brchno,
                    round (
                        a.comamount
                    * CASE
                            WHEN p.comrateoption = 0 AND p.comuseratetier = 0
                            THEN
                            coalesce (p.comrate, 0)
                            WHEN p.comrateoption = 0 AND p.comuseratetier = 1
                            THEN
                            (SELECT coalesce (MAX (intrate), 0)
                                FROM ln_account_type_int_rate ir
                                WHERE     ir.prodcode = p.prodcode
                                    AND ir.inttype = 2
                                    AND ir.instid = p.instid
                                    AND a.comamount BETWEEN ir.minamount
                                                        AND ir.maxamount - 0.001)
                            WHEN p.comrateoption = 1 AND a.intratecomtier = 0
                            THEN
                            coalesce (a.intratecom, 0)
                            WHEN p.comrateoption = 1 AND a.intratecomtier = 1
                            THEN
                            (SELECT coalesce (MAX (intrate), 0)
                                FROM ln_account_int_rate ir
                                WHERE     ir.acntno = a.acntno
                                    AND ir.inttype = 2
                                    AND ir.instid = a.instid
                                    AND a.comamount BETWEEN ir.minamount
                                                        AND ir.maxamount - 0.001)
                            ELSE
                            0
                        END
                    / p.yeardays
                    / 100,
                    :digit) AS newcomint,
                    round (
                    CASE
                        WHEN p.finetype = 'R' OR p.finetype = 'Z'
                        THEN
                            (a.dueamount - $fine_minus)
                            * (CASE WHEN p.finetype = 'Z' THEN a.intrate / 100 ELSE 1 END)
                            / p.yeardays
                            / 100
                            * CASE
                                WHEN p.finerateoption = 0 AND p.fineuseratetier = 0
                                THEN
                                    coalesce (p.finerate, 0)
                                WHEN p.finerateoption = 0 AND p.fineuseratetier = 1
                                THEN
                                    (SELECT coalesce (MAX (intrate), 0)
                                    FROM ln_account_type_int_rate ir
                                    WHERE     ir.prodcode = p.prodcode
                                            AND ir.inttype = 1
                                            AND ir.instid = p.instid
                                            AND ir.statusid = 1
                                            AND (a.dueamount - $fine_minus) BETWEEN ir.minamount
                                                                    AND   ir.maxamount
                                                                        - 0.001)
                                WHEN p.finerateoption = 1 AND a.intratefinetier = 0
                                THEN
                                    coalesce (a.intratefine, 0)
                                WHEN p.finerateoption = 1 AND a.intratefinetier = 1
                                THEN
                                    (SELECT coalesce (MAX (intrate), 0)
                                    FROM ln_account_int_rate ir
                                    WHERE     ir.acntno = a.acntno
                                            AND ir.inttype = 1
                                            AND ir.instid = a.instid
                                            AND (a.dueamount - $fine_minus) BETWEEN ir.minamount
                                                                    AND   ir.maxamount
                                                                        - 0.001)
                                ELSE
                                    0
                            END
                        WHEN p.finetype = 'F'
                        THEN
                            p.fineflat
                        ELSE
                            0
                    END,
                    :digit) AS newfineint,
                    $multp as multp,
                    $fine_multp as fine_multp
            FROM ln_account a
                    INNER JOIN ln_account_type p
                    ON a.prodcode = p.prodcode AND a.instid = p.instid
            WHERE (a.statusid > 1 AND a.statusid < 9) and a.instid = :instid
            ";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and acntno >= '" . $lastitem->acntno . "'";
        }
        $sql = $sql . " order by acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'digit' => $digitCount,
            'instid' => $instid
        ]);

        return $results;
    }

    public function calcLNDailyRateFine($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT a.fineintdaily,
                        a.tmp_fineintdaily,
                        a.finecomintdaily,
                        a.acntno,
                        a.brchno,
                        p.minfine,
                        p.intdayoption
                FROM ln_account a
                        INNER JOIN ln_account_type p
                        ON a.prodcode = p.prodcode AND a.instid = p.instid
                WHERE     a.fineintdaily < p.minfine
                        AND a.fineintdaily >= 0
                        AND (a.statusid > 1 AND a.statusid < 9) and a.instid = :instid";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and acntno >= '" . $lastitem->acntno . "'";
        }
        $sql = $sql . " order by acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid
        ]);

        return $results;
    }


    public function createLNBaseAcrJrl($sysdate, $lastitem, $instid)
    {
        $txnCode = "'ln902041'";
        $txndesc = 'Үндсэн хүү хуримтлуулав. (EOD)';
        $sql  = LnAccount::select(
            'ln_account.curcode',
            'ln_account.brchno',
            'ln_account.acntno',
            'ln_account.prodcode'
        )
            ->leftjoin('ln_account_type', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type.instid');
            })
            ->leftjoin('GP_inst_qual', function ($join) {
                $join->on('ln_account.prodcode', '=', 'GP_inst_qual.prodcode')
                    ->on('ln_account.instid', '=', 'GP_inst_qual.instid')
                    ->where('GP_inst_qual.statusid', '=', 1)
                    ->on('ln_account.clscode', '=', 'GP_inst_qual.clscode');
            })
            ->leftjoin('ln_account_type_cls', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type_cls.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type_cls.instid')
                    ->on('ln_account.clscode', '=', 'ln_account_type_cls.clscode')
                    ->where('ln_account_type_cls.statusid', '=', 1);
            })
            ->where('ln_account.baseintdaily', '<>', 0)
            ->where('ln_account.instid', $instid)
            ->whereRaw("COALESCE(GP_inst_qual.txncode, $txnCode) = $txnCode")
            ->whereRaw('(
            CASE
                WHEN ln_account_type.intacr_opt = 1 THEN ln_account_type_cls.int::numeric
                ELSE 1 /*cls.int*/
            END = 1
        )')
            ->whereRaw('(ln_account.statusid = 4 OR ln_account.statusid = 8) OR (ln_account.statusid = 3
        AND ln_account.intstoptype = 1
        AND ln_account.intstopclass = 1)');

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('ln_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('ln_account.acntno', 'ASC')->get();
        return $results;
    }

    public function createLNComAcrJrl($sysdate, $lastitem, $instid)
    {
        $txnCode = "'ln902042'";
        $sql = LnAccount::select(
            'ln_account.curcode',
            'ln_account.brchno',
            'ln_account.acntno',
            'ln_account.prodcode'
        )
            ->leftjoin('ln_account_type', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type.instid');
            })
            ->leftjoin('GP_inst_qual', function ($join) {
                $join->on('ln_account.prodcode', '=', 'GP_inst_qual.prodcode')
                    ->on('ln_account.instid', '=', 'GP_inst_qual.instid')
                    ->on('ln_account.clscode', '=', 'GP_inst_qual.clscode')
                    ->where('GP_inst_qual.statusid', '=', 1);
            })
            ->leftjoin('ln_account_type_cls', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type_cls.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type_cls.instid')
                    ->on('ln_account.clscode', '=', 'ln_account_type_cls.clscode')
                    ->where('ln_account_type_cls.statusid', '=', 1);
            })
            // ->where('ln_account.comintdaily', '<>', 0)
            ->where('ln_account.instid', $instid)
            ->whereRaw("ln_account.enddate > '" . $sysdate . "':: DATE")
            ->whereRaw("COALESCE(GP_inst_qual.txncode, $txnCode) = $txnCode")
            ->whereRaw('(
                            CASE
                                WHEN ln_account_type.comacr_opt = 1 THEN ln_account_type_cls.com::numeric
                                ELSE 1 /*cls.com*/
                            END = 1
                        )')
            ->whereRaw(
                '((ln_account.statusid = 4 OR ln_account.statusid = 8 OR ln_account.statusid = 1)
            OR (ln_account.statusid = 3 AND ln_account.intstoptype = 1 AND ln_account.intstopclass = 1))'
            );

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('ln_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('ln_account.acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Барьцаа хөрөнгийн үнэлгээний зөрүүг тэнцлийн гадуур тусгах барьцаа хөрөнгийн дансны жагсаалт авах
     */
    public function moveMortgageCostCT($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT
                    ln.acntno,
                    lm.morno,
                    ln.brchno,
                    lm.costcurcode as curcode,
                    lm.statusid,
                    round(lm.costamount - coalesce(lm.ctacntno, 0), 2) AS diff,
                    mt.concount,
                    ln.cetype,
                    lm.ctacntno as currentbal,
                    coalesce(lm.statusid, 0) AS lnstatusid
                FROM ln_account ln
                LEFT JOIN ln_account_mor link ON ln.instid = link.instid AND ln.acntno = link.acntno AND link.statusid = 1
                LEFT JOIN ln_mor lm ON link.instid = lm.instid AND link.morno = lm.morno
                INNER JOIN ln_mor_type mt ON mt.instid = lm.instid AND mt.prodcode = lm.prodcode  AND iscontingent = 1
                WHERE ln.instid = :instid AND ln.statusid > 1 AND ln.statusid < 9 AND round(lm.ctacntno, 2) <> round(lm.costamount, 2)
                    OR (ln.ctacntno > 0 AND ln.cetype = 0 AND ln.statusid = 8 AND ln.instid = :instid)"
            . ($lastitem ? "AND ln.acntno >= :acntno " : " ") .
            "ORDER BY ln.acntno";
        $parameter = [
            'instid' => $instid,
        ];

        if ($lastitem && $lastitem->acntno) {
            $parameter['acntno'] = $lastitem->acntno;
        }
        $results = DB::select(DB::raw($sql), $parameter);
        return $results;
    }

    /**
     * Барьцаа хөрөнгийг зээлийн данснаас салгасан болон Зээлийн данс хаагдсан
     * Барьцаа хөрөнгийн үнэлгээний зөрүүг тэнцлийн гадуур тусгах барьцаа хөрөнгийн дансны жагсаалт авах
     */
    public function removeMortgageCostCT($sysdate, $lastitem, $instid)
    {
        $sql = "  SELECT
                        ln.acntno,
                        lm.morno,
                        ln.brchno,
                        lm.costcurcode AS curcode,
                        mt.ctgl,
                        lm.statusid as morstatusid,
                        mt.concount,
                        ln.cetype,
                        round (lm.ctacntno, 2) AS amount,
                        coalesce (ln.statusid, 0) AS lnstatusid,
                        lm.costperamount
                FROM ln_mor lm
                        INNER JOIN ln_mor_type mt
                        ON     mt.instid = lm.instid
                            AND mt.prodcode = lm.prodcode
                            AND iscontingent = 1
                        LEFT JOIN ln_account ln
                        ON     ln.instid = lm.instid
                            AND ln.acntno =
                                (SELECT MAX (ll.acntno)
                                    FROM ln_account_mor ll
                                WHERE     ll.instid = lm.instid
                                        AND ll.morno = lm.morno
                                        AND ll.updated_at =
                                            (SELECT MAX (l.updated_at)
                                            FROM ln_account_mor l
                                            WHERE     l.instid = lm.instid
                                                    AND l.morno = lm.morno))
                WHERE     lm.instid = :instid
                        AND (   NOT EXISTS
                                (SELECT 1
                                    FROM ln_account_mor l
                                    WHERE     l.instid = lm.instid
                                        AND l.morno = lm.morno
                                        AND l.statusid = 1)
                            OR NOT EXISTS
                                (SELECT 1
                                    FROM ln_account_mor l
                                        INNER JOIN ln_account a
                                            ON     a.instid = l.instid
                                                AND a.acntno = l.acntno
                                                AND a.statusid BETWEEN 2 AND 8
                                    WHERE     l.instid = lm.instid
                                        AND l.morno = lm.morno
                                        AND l.statusid = 1))
                        AND lm.ctacntno <> 0 ";

        $parameter = ['instid' => $instid];

        if ($lastitem && !empty($lastitem->acntno)) {
            $sql .= " AND ln.acntno >= :acntno ";
            $parameter['acntno'] = $lastitem->acntno;
        }

        $sql .= " ORDER BY acntno";

        $results = DB::select(DB::raw($sql), $parameter);
        return $results;
    }


    public function createLNFineAcrJrl($sysdate, $lastitem, $instid)
    {
        $txnCode = "'ln902043'";
        $effectiveDuePrinc = $this->effectivePrincipalDueSql('ln_account', 'ln_account_type');
        $sql = LnAccount::select(
            'ln_account.curcode',
            'ln_account.brchno',
            'ln_account.acntno',
            'ln_account.prodcode'
        )
            ->leftjoin('ln_account_type', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type.instid');
            })
            ->leftjoin('GP_inst_qual', function ($join) {
                $join->on('ln_account.prodcode', '=', 'GP_inst_qual.prodcode')
                    ->on('ln_account.instid', '=', 'GP_inst_qual.instid')
                    ->on('ln_account.clscode', '=', 'GP_inst_qual.clscode')
                    ->where('GP_inst_qual.statusid', '=', 1);
            })
            ->leftjoin('ln_account_type_cls', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type_cls.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type_cls.instid')
                    ->on('ln_account.clscode', '=', 'ln_account_type_cls.clscode')
                    ->where('ln_account_type_cls.statusid', '=', 1);
            })
            ->where('ln_account.fineintdaily', '<>', 0)
            ->where('ln_account.instid', $instid)
            // ->whereRaw("ln_account.enddate > '" . $sysdate . "':: DATE")
            ->whereRaw("COALESCE(GP_inst_qual.txncode, $txnCode) = $txnCode")
            ->whereRaw('(CASE
                    WHEN ln_account_type.fineacr_opt = 1 THEN ln_account_type_cls.fine::numeric
                    ELSE 1 /*cls.fine*/
                    END = 1)')
            ->whereRaw('((ln_account.statusid = 4 OR ln_account.statusid = 8) OR (ln_account.statusid = 3
                    AND ln_account.intstoptype = 1 AND ln_account.intstopclass = 1))')
            ->whereRaw("(
                            (ln_account_type.finecondition = 1 AND ln_account.enddate + ln_account_type.finegrace <= '$sysdate'::DATE )
                            OR (ln_account_type.finecondition = 0 AND (
                                                                        ($effectiveDuePrinc > 0 AND (ln_account.arreardate + ln_account_type.finegrace) <= '$sysdate'::DATE)
                                                                        OR (ln_account.arreardateint + ln_account_type.finegrace) <= '$sysdate':: DATE)
                                                                    )
                            OR (ln_account_type.finecondition = 2 AND $effectiveDuePrinc > COALESCE(NULLIF(ln_account_type.minarrears2fine, 0), 0.1) AND (ln_account.arreardate + ln_account_type.finegrace) <= '$sysdate'::DATE)
                            OR (ln_account_type.finecondition = 3 AND (
                                                                        ($effectiveDuePrinc
                                                                         +
                                                                         CASE WHEN ln_account.dueint < 0 THEN 0
                                                                              ELSE ln_account.dueint
                                                                        END) > COALESCE(NULLIF(ln_account_type.minarrears2fine, 0), 0.1))
                                                                AND (
                                                                        ($effectiveDuePrinc > 0 AND (ln_account.arreardate + ln_account_type.finegrace) <= '$sysdate'::DATE)
                                                                    OR (ln_account.arreardateint + ln_account_type.finegrace) <= '$sysdate':: DATE
                                                                    )
                                )
                        )");

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('ln_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('ln_account.acntno', 'ASC')->get();
        return $results;
    }

    public function updateLNFineInt2Cap($txndate, $instid)
    {
        $effectiveDuePrinc = $this->effectivePrincipalDueSql('a', 'p');
        $effectiveArrearDate = "(CASE WHEN $effectiveDuePrinc > 0 THEN a.arreardate ELSE NULL END)";
        $sql = "UPDATE ln_account a
        set fineint2cap = t.fineint2cap
            + CASE
                  WHEN (   (t.statusid = 4 OR t.statusid = 8)
                        OR (    t.statusid = 3
                            AND t.intstoptype = 1
                            AND t.intstopclass = 1))
                  THEN
                      CASE t.finecondition
                          WHEN 1
                          THEN
                              CASE
                                  WHEN     t.enddate + t.finegrace = :txndate::date
                                       AND t.finegrace > 0
                                  THEN
                                  ROUND (
                                            (t.finegrace + 1)
                                          * (t.fineintdaily + t.fineroundint),
                                          2)
                                  ELSE
                                      t.fineintdaily
                              END
                          ELSE
                              CASE
                                  WHEN     t.arreardatereal + t.finegrace > :txndate::date
                                       AND t.enddate = :txndate::date
                                  THEN
                                  ROUND (
                                            CASE
                                                WHEN t.finelastacrueddate
                                                         IS NULL
                                                THEN
                                                    (  t.enddate
                                                     - t.arreardatereal
                                                     + 1)
                                                ELSE
                                                    (CASE
                                                         WHEN t.arreardatereal >=
                                                              t.finelastacrueddate
                                                         THEN
                                                             (  t.enddate
                                                              - t.arreardatereal
                                                              + 1)
                                                         ELSE
                                                             (  t.enddate
                                                              - t.finelastacrueddate)
                                                     END)
                                            END
                                          * (t.fineintdaily + t.fineroundint),
                                          2)
                                  ELSE
                                      CASE
                                          WHEN       t.arreardatereal
                                                   + t.finegrace = :txndate::date
                                               AND t.enddate > :txndate::date
                                               AND t.finegrace > 0
                                          THEN
                                          ROUND (
                                                    (CASE
                                                         WHEN t.finelastacrueddate
                                                                  IS NULL
                                                         THEN
                                                             t.finegrace + 1
                                                         ELSE
                                                             (CASE
                                                                  WHEN t.arreardatereal >=
                                                                       t.finelastacrueddate
                                                                  THEN
                                                                        t.finegrace
                                                                      + 1
                                                                  ELSE
                                                                      ( :txndate::date - t.finelastacrueddate)
                                                              END)
                                                     END)
                                                  * (  t.fineintdaily
                                                     + t.fineroundint),
                                                  2)
                                          ELSE
                                              t.fineintdaily
                                      END
                              END
                      END
                  ELSE
                      0
              END,
              tmp_fineintdaily =
            CASE
                WHEN t.finecondition = 0 AND t.finegrace > 0
                THEN
                    CASE
                        WHEN (   (t.statusid = 4 OR t.statusid = 8)
                              OR (    t.statusid = 3
                                  AND t.intstoptype = 1
                                  AND t.intstopclass = 1))
                        THEN
                            CASE
                                WHEN     t.arreardatereal + t.finegrace > :txndate::date
                                     AND t.enddate = :txndate::date
                                THEN
                                      (CASE
                                           WHEN t.finelastacrueddate IS NULL
                                           THEN
                                               (  t.enddate
                                                - t.arreardatereal
                                                + 1)
                                           ELSE
                                               (CASE
                                                    WHEN t.arreardatereal >=
                                                         t.finelastacrueddate
                                                    THEN
                                                        (  t.enddate
                                                         - t.arreardatereal
                                                         + 1)
                                                    ELSE
                                                        (  t.enddate
                                                         - t.finelastacrueddate)
                                                END)
                                       END)
                                    * t.tmp_fineintdaily
                                ELSE
                                    CASE
                                        WHEN       t.arreardatereal
                                                 + t.finegrace =
                                                 :txndate::date
                                             AND t.enddate > :txndate::date
                                             AND t.finegrace > 0
                                        THEN
                                              (CASE
                                                   WHEN t.finelastacrueddate
                                                            IS NULL
                                                   THEN
                                                       (t.finegrace + 1)
                                                   ELSE
                                                       CASE
                                                           WHEN t.arreardatereal >=
                                                                t.finelastacrueddate
                                                           THEN
                                                               (  t.finegrace
                                                                + 1)
                                                           ELSE
                                                               (  t.arreardatereal
                                                                - t.finelastacrueddate)
                                                       END
                                               END)
                                            * t.tmp_fineintdaily
                                        ELSE
                                            t.tmp_fineintdaily
                                    END
                            END
                        ELSE
                            t.tmp_fineintdaily
                    END
                ELSE
                    CASE
                        WHEN (   (t.statusid = 4 OR t.statusid = 8)
                              OR (    t.statusid = 3
                                  AND t.intstoptype = 1
                                  AND t.intstopclass = 1))
                        THEN
                            CASE
                                WHEN     t.arreardatereal + t.finegrace > :txndate::date
                                     AND t.enddate = :txndate::date
                                THEN
                                      (t.enddate - t.arreardatereal + 1)
                                    * t.tmp_fineintdaily
                                ELSE
                                    CASE
                                        WHEN       t.arreardatereal
                                                 + t.finegrace = :txndate::date
                                             AND t.enddate > :txndate::date
                                             AND t.finegrace > 0
                                        THEN
                                              (t.finegrace + 1)
                                            * t.tmp_fineintdaily
                                        ELSE
                                            t.tmp_fineintdaily
                                    END
                            END
                        ELSE
                            t.tmp_fineintdaily
                    END
            END,
        fineroundint =
            CASE
                WHEN t.finecondition = 0 AND t.finegrace > 0
                THEN
                    CASE
                        WHEN (    t.arreardatereal + t.finegrace > :txndate::date
                              AND t.enddate = :txndate::date)
                        THEN
                            CASE
                                WHEN t.finelastacrueddate IS NULL
                                THEN
                                        (t.enddate - t.arreardatereal + 1)
                                      * (t.fineintdaily + t.fineroundint)
                                    - TRUNC (
                                            (t.enddate - t.arreardatereal + 1)
                                          * (t.fineintdaily + t.fineroundint),
                                          2)
                                ELSE
                                        (  t.enddate
                                         - CASE
                                               WHEN t.arreardatereal >=
                                                    t.finelastacrueddate
                                               THEN
                                                   t.arreardatereal + 1
                                               ELSE
                                                   t.finelastacrueddate
                                           END)
                                      * (t.fineintdaily + t.fineroundint)
                                    - TRUNC (
                                            (  t.enddate
                                             - CASE
                                                   WHEN t.arreardatereal >=
                                                        t.finelastacrueddate
                                                   THEN
                                                       t.arreardatereal + 1
                                                   ELSE
                                                       t.finelastacrueddate
                                               END)
                                          * (t.fineintdaily + t.fineroundint),
                                          2)
                            END
                        ELSE
                            CASE
                                WHEN    (    t.enddate + t.finegrace = :txndate::date
                                         AND t.finegrace > 0)
                                     OR (    t.arreardatereal + t.finegrace = :txndate::date
                                         AND t.enddate > :txndate::date
                                         AND t.finegrace > 0)
                                THEN
                                    CASE
                                        WHEN t.finelastacrueddate IS NULL
                                        THEN
                                                (t.finegrace + 1)
                                              * (  t.fineintdaily
                                                 + t.fineroundint)
                                            - TRUNC (
                                                    (t.finegrace + 1)
                                                  * (  t.fineintdaily
                                                     + t.fineroundint),
                                                  2)
                                        ELSE
                                            CASE
                                                WHEN t.arreardatereal >=
                                                     t.finelastacrueddate
                                                THEN
                                                        (t.finegrace + 1)
                                                      * (  t.fineintdaily
                                                         + t.fineroundint)
                                                    - TRUNC (
                                                            (t.finegrace + 1)
                                                          * (  t.fineintdaily
                                                             + t.fineroundint),
                                                          2)
                                                ELSE
                                                        (  :txndate::date - t.finelastacrueddate)
                                                      * (  t.fineintdaily
                                                         + t.fineroundint)
                                                    - TRUNC (
                                                            (:txndate::date - t.finelastacrueddate)
                                                          * (  t.fineintdaily
                                                             + t.fineroundint),
                                                          2)
                                            END
                                    END
                                ELSE
                                    t.fineroundint
                            END
                    END
                ELSE
                    CASE
                        WHEN (    t.arreardatereal + t.finegrace > :txndate::date
                              AND t.enddate = :txndate::date)
                        THEN
                                (t.enddate - t.arreardatereal + 1)
                              * (t.fineintdaily + t.fineroundint)
                            - TRUNC (
                                    (t.enddate - t.arreardatereal + 1)
                                  * (t.fineintdaily + t.fineroundint),
                                  2)
                        ELSE
                            CASE
                                WHEN    (    t.enddate + t.finegrace = :txndate::date
                                         AND t.finegrace > 0)
                                     OR (    t.arreardatereal + t.finegrace = :txndate::date
                                         AND t.enddate > :txndate::date
                                         AND t.finegrace > 0)
                                THEN
                                        (t.finegrace + 1)
                                      * (t.fineintdaily + t.fineroundint)
                                    - TRUNC (
                                            (t.finegrace + 1)
                                          * (t.fineintdaily + t.fineroundint),
                                          2)
                                ELSE
                                    t.fineroundint
                            END
                    END
            END,
        finelastacrueddate =
            CASE
                WHEN CASE
                         WHEN (   (t.statusid = 4 OR t.statusid = 8)
                               OR (    t.statusid = 3
                                   AND t.intstoptype = 1
                                   AND t.intstopclass = 1))
                         THEN
                             CASE t.finecondition
                                 WHEN 1
                                 THEN
                                     CASE
                                         WHEN     t.enddate + t.finegrace = :txndate::date
                                              AND t.finegrace > 0
                                         THEN
                                             TRUNC (
                                                   (t.finegrace + 1)
                                                 * (  t.fineintdaily
                                                    + t.fineroundint),
                                                 2)
                                         ELSE
                                             t.fineintdaily
                                     END
                                 ELSE
                                     CASE
                                         WHEN       t.arreardatereal
                                                  + t.finegrace > :txndate::date
                                              AND t.enddate = :txndate::date
                                         THEN
                                             TRUNC (
                                                   CASE
                                                       WHEN t.finelastacrueddate
                                                                IS NULL
                                                       THEN
                                                           (  t.enddate
                                                            - t.arreardatereal
                                                            + 1)
                                                       ELSE
                                                           (CASE
                                                                WHEN t.arreardatereal >=
                                                                     t.finelastacrueddate
                                                                THEN
                                                                    (  t.enddate
                                                                     - t.arreardatereal
                                                                     + 1)
                                                                ELSE
                                                                    (  t.enddate
                                                                     - t.finelastacrueddate)
                                                            END)
                                                   END
                                                 * (  t.fineintdaily
                                                    + t.fineroundint),
                                                 2)
                                         ELSE
                                             CASE
                                                 WHEN       t.arreardatereal
                                                          + t.finegrace = :txndate::date
                                                      AND t.enddate > :txndate::date
                                                      AND t.finegrace > 0
                                                 THEN
                                                     TRUNC (
                                                           (CASE
                                                                WHEN t.finelastacrueddate
                                                                         IS NULL
                                                                THEN
                                                                      t.finegrace
                                                                    + 1
                                                                ELSE
                                                                    (CASE
                                                                         WHEN t.arreardatereal >=
                                                                              t.finelastacrueddate
                                                                         THEN
                                                                               t.finegrace
                                                                             + 1
                                                                         ELSE
                                                                             (  :txndate::date - t.finelastacrueddate)
                                                                     END)
                                                            END)
                                                         * (  t.fineintdaily
                                                            + t.fineroundint),
                                                         2)
                                                 ELSE
                                                     t.fineintdaily
                                             END
                                     END
                             END
                         ELSE
                             0
                     END >
                     0
                THEN
                    :txndate::date
                ELSE
                    t.finelastacrueddate
            END
        from (SELECT
                    a.acntno,
                    a.instid,
                    a.fineint2cap,
                    a.fineintdaily,
                    a.fineroundint,
                    a.tmp_fineintdaily,
                    a.arreardate,
                    p.finegrace,
                    CASE p.finecondition
                        WHEN 1
                        THEN
                            a.enddate
                        ELSE
                            CASE
                                WHEN     $effectiveArrearDate IS NULL
                                    AND a.arreardateint IS NULL
                                THEN
                                    NULL
                                WHEN     $effectiveArrearDate IS NULL
                                    AND a.arreardateint IS NOT NULL
                                THEN
                                    a.arreardateint + 1
                                WHEN     $effectiveArrearDate IS NOT NULL
                                    AND a.arreardateint IS NULL
                                THEN
                                    $effectiveArrearDate
                                WHEN $effectiveArrearDate >= a.arreardateint + 1
                                THEN
                                    a.arreardateint + 1
                                WHEN a.arreardateint + 1 >= $effectiveArrearDate
                                THEN
                                    $effectiveArrearDate
                            END
                    END    arreardatereal,
                    a.enddate,
                    a.statusid,
                    a.intstoptype,
                    a.intstopclass,
                    p.finecondition,
                    a.finelastacrueddate
            FROM ln_account  a
                    INNER JOIN ln_account_type p ON a.prodcode = p.prodcode and a.instid = p.instid
                    INNER JOIN ln_account_type_cls AC ON a.prodcode = AC.prodcode AND a.clscode = AC.clscode and a.instid = AC.instid
            WHERE     a.fineintdaily <> 0 AND a.instid = :instid
                    AND CASE
                            WHEN p.fineacr_opt = 1 THEN AC.fine::numeric
                            ELSE 1 /*c.fine*/
                        END = 1
                    AND (   (p.finecondition = 1 AND a.enddate <= :txndate)
                        OR (p.finecondition = 0 AND ($effectiveDuePrinc > 0 OR a.dueint > 0))
                        OR (p.finecondition = 2 AND $effectiveDuePrinc > 0)
                        OR (    p.finecondition = 3
                            AND ($effectiveDuePrinc + a.dueint > 0)))
                    AND a.statusid IN (3, 4, 8)) t
                    where a.acntno = t.acntno and a.instid = t.instid ";

        DB::statement($sql, [
            'instid' => $instid,
            'txndate' => $txndate
        ]);
    }

    /**
     * Зээлийн дансны үлдэгдэл түр хадгалах ad800056
     */
    public function CreateTmpLNBals($sysdate, $lastitem, $instid)
    {

        $sql = LnAccount::select([
            'acntno',
            'princbal',
            'capbint',
            'capbinte',
            'capcint',
            'capfint',
            'statusid',
            'prodcode',
            'brchno',
            'clscode',
            'theorbal',
            'begdate',
            'enddate',
            'dueprinc',
            'dueint',
            'duecom',
            'arreardate',
            'arreardateint',
            'arreardatecom',
            'redrawlimit',
            'dueamount',
            'clscodetrm',
            'fineintdaily',
            'comintdaily',
            'baseintdaily',
            'clscodeqlt'
        ])
            ->selectRaw('baseint2cap + adjbint2cap as acrbint')
            ->selectRaw('comint2cap + adjcint2cap as acrcint')
            ->selectRaw('fineint2cap + adjfint2cap as acrfint')
            ->where(function ($query) {
                $query->whereRaw("COALESCE(tmp_princbal::varchar, '0') <> COALESCE(princbal::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_capbint::varchar, '0') <> COALESCE(capbint::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_capbinte::varchar, '0') <> COALESCE(capbinte::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_capcint::varchar, '0') <> COALESCE(capcint::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_capfint::varchar, '0') <> COALESCE(capfint::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_acrbint::varchar, '0') <> COALESCE((baseint2cap + adjbint2cap)::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_acrcint::varchar, '0') <> COALESCE((comint2cap + adjcint2cap)::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_acrfint::varchar, '0') <> COALESCE((fineint2cap + adjfint2cap)::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_statuscode::varchar, '0') <> COALESCE(statusid::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_prodcode::varchar, '') <> COALESCE(prodcode::varchar, '')")
                    ->orWhereRaw("COALESCE(tmp_brchno::varchar, '') <> COALESCE(brchno::varchar, '')")
                    ->orWhereRaw("COALESCE(tmp_clscode::varchar, '0') <> COALESCE(clscode::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_theorbal::varchar, '0') <> COALESCE(theorbal::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_begdate::date, '0001-01-01'::date) <> COALESCE(begdate::date, '0001-01-01'::date)")
                    ->orWhereRaw("COALESCE(tmp_enddate::date, '0001-01-01'::date) <> COALESCE(enddate::date, '0001-01-01'::date)")
                    ->orWhereRaw("COALESCE(tmp_dueprinc::varchar, '0') <> COALESCE(dueprinc::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_dueint::varchar, '0') <> COALESCE(dueint::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_duecom::varchar, '0') <> COALESCE(duecom::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_arreardate::date, '0001-01-01'::date) <> COALESCE(arreardate::date, '0001-01-01'::date)")
                    ->orWhereRaw("COALESCE(tmp_arreardateint::date, '0001-01-01'::date) <> COALESCE(arreardateint::date, '0001-01-01'::date)")
                    ->orWhereRaw("COALESCE(tmp_arreardatecom::date, '0001-01-01'::date) <> COALESCE(arreardatecom::date, '0001-01-01'::date)")
                    ->orWhereRaw("COALESCE(tmp_redrawlimit::varchar, '0') <> COALESCE(redrawlimit::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_dueamount::varchar, '0') <> COALESCE(dueamount::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_clscodetrm::varchar, '0') <> COALESCE(clscodetrm::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_baseintdaily::varchar, '0') <> COALESCE(baseintdaily::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_comintdaily::varchar, '0') <> COALESCE(comintdaily::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_fineintdaily::varchar, '0') <> COALESCE(fineintdaily::varchar, '0')")
                    ->orWhereRaw("COALESCE(tmp_clscodeqlt::varchar, '0') <> COALESCE(clscodeqlt::varchar, '0')");
            })
            ->where('instid', '=', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Зээлийн хүү балансын гадуур хуримтлуулах ad800060
     */
    public function accruLNBaseIntCT($sysdate, $lastitem, $instid)
    {
        $sql = LnAccount::select(
            'acntno',
            'ctacntno',
            'prodcode',
            'curcode',
            'baseintdaily',
            'brchno'
        )
            ->where('ctacruel', '=', 1)
            ->where('baseintdaily', '>', 0)
            ->where('statusid', '=', 3)
            ->where('intstoptype', '=', 1)
            ->where('instid', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Зээлийн нэмэгдүүлсэн хүү балансын гадуур хуримтлуулах ad800061
     */
    public function accruLNFineIntCT($sysdate, $lastitem, $instid)
    {
        $effectiveDuePrinc = $this->effectivePrincipalDueSql('ln_account', 'ln_account_type');
        $sql = LnAccount::select(
            'ln_account.acntno',
            'ln_account.ctfineacntno',
            'ln_account.prodcode',
            'ln_account.curcode',
            'ln_account.fineintdaily',
            'ln_account.finecomintdaily',
            'ln_account.brchno'
        )->join('ln_account_type', function ($join) {
            $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                ->on('ln_account.instid', '=', 'ln_account_type.instid');
        })
            ->where('ln_account.ctfineacruel', '=', 1)
            ->whereRaw('(ln_account.fineintdaily + COALESCE(ln_account.finecomintdaily,0)) <> 0')
            ->where('ln_account.statusid', '=', 3)
            ->where('ln_account.intstoptype', '=', 1)
            ->where('ln_account.instid', $instid)
            ->where(function ($subquery) use ($sysdate, $effectiveDuePrinc) {
                $subquery->where(function ($condition) use ($sysdate) {
                    $condition->where('ln_account_type.finecondition', 1)
                        ->where('ln_account.enddate', '<=', $sysdate);
                })
                    ->orWhere(function ($condition) use ($effectiveDuePrinc) {
                        $condition->where('ln_account_type.finecondition', 0)
                            ->whereRaw("($effectiveDuePrinc > 0 OR ln_account.dueint > 0)");
                    })
                    ->orWhere(function ($condition) use ($effectiveDuePrinc) {
                        $condition->where('ln_account_type.finecondition', 2)
                            ->whereRaw("$effectiveDuePrinc > 0");
                    })
                    ->orWhere(function ($condition) use ($effectiveDuePrinc) {
                        $condition->where('ln_account_type.finecondition', 3)
                            ->whereRaw("($effectiveDuePrinc + ln_account.dueint) > 0");
                    });
            });
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Зээлийн комитмэнт хүү балансын гадуур хуримтлуулах ad800062
     */
    public function accruLNComIntCT($sysdate, $lastitem, $instid)
    {
        $sql = LnAccount::select(
            'ln_account.acntno',
            'ln_account.ctcomacntno',
            'ln_account.prodcode',
            'ln_account.curcode',
            'ln_account.comintdaily',
            'ln_account.brchno'
        )
            ->where('ln_account.ctcomacruel', '=', 1)
            ->where('ln_account.comintdaily', '>', 0)
            ->where('ln_account.statusid', '=', 3)
            ->where('ln_account.intstoptype', '=', 1)
            ->where('ln_account.instid', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Зээлийн комитмэнт хүү балансын гадуур хуримтлуулах ad800062
     * //LNAcnt. StatusCode=3 үед л балансын гадуур хуримтлуулах асуудал яригдана.
     *          //+IntStopType	Number(1)	not null	default (0); 	// Балансын гадуур бүртгэдэг эсэх
     *         //    0 – хэвийн
     *        //    1 – Балансын дотуур зогссон. Балансын гадуур хүүний данс тохируулсан бол балансын гадуур хүү хуримтлуулна.
     *       //    2 – Хүү зогсоосон. Хүү огт хуримтуулахгүй
     *      //+IntStopClass	Number(1)	not null	default (0); 	// Балансын гадуур гарсан эсэх
     *     //    0 – Дотуур зогсоод гадуур хуримтлуулна
     *    //    1 – Балансын дотуур, гадуур ч хуримтлуулна
     *
     *               // төлбөрийн хуваарьтай ижил (CapFreq = 'S') ба маргааш төлбөр хийхээр байвал :
     *              // (энэ алхам хийгдэх үед Main.mstrSysDate маргаашийг зааж байна.)
     *             // сарын ойн дээр кап. (A.CapFreq = 'M') хийдэг бөгөөд өнөөдөр ой нь бол
     *            // Хүү зогсоосон, балансын дотуур зогссон, IntStopClass=0 гадуур хуримтлуулж байгаа үед хүүг кап хийдэг.

     *           // Бүтээгдэхүүн дээр "Балансын гадуур хүүний данснаас төлөлт хийгдэх эсэх" = 0 үед бал.гад кап хийдэггүй.
     *          // CTLoanRepayment: 0 – автоматаар төлөлт хийгдэхгүй;     1 – автоматаар  төлөлт хийгдэнэ"
     */
    public function capLnNrsAcnts($sysdate, $lastitem, $instid)
    {
        $csysdate = new Carbon($sysdate);
        $capdate = $csysdate->format('Y-m-d');
        $capIntExpiredLoan = '';
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'CapIntExpiredLoan')->first();
        if ($gp) {
            $capIntExpiredLoan = $gp->itemvalue;
        }
        $day = $csysdate->format('d');

        $sql = "SELECT ln_account.statusid,
            ln_account.princbal,
            ln_account.acntno,
            ln_account.prodcode,
            ln_account.curcode,
            ln_account.nextpayday,
            ln_account.baseint2cap,
            ln_account.adjbint2cap,
            ln_account.comint2cap,
            ln_account.adjcint2cap,
            ln_account.fineint2cap,
            ln_account.adjfint2cap,
            ln_account.brchno,
            ln_account.intstopclass,
            ln_account_type.ctloanrepayment,
            ln_account.ctacntno AS ctbint,
            ln_account.ctcomacntno AS ctcint,
            ln_account.ctfineacntno AS ctfint,
            COALESCE (ln_account_type.intreturn, 0) AS intreturn,
            CASE
            WHEN ln_account_type.intreturn = 1
            THEN
                COALESCE (ln_schd.intreturnamount, 0)
            ELSE
                0
            END AS intreturnamount
            FROM ln_account
                INNER JOIN ln_account_type
                    ON     ln_account.prodcode = ln_account_type.prodcode
                        AND ln_account.instid = ln_account_type.instid
                LEFT JOIN ln_schd
                    ON     ln_account.acntno = ln_schd.acntno
                        AND ln_account.instid = ln_schd.instid
                        AND ln_account.nextpayday = ln_schd.payday
                        AND ln_schd.statusid <> -1
            WHERE     (   (    ln_account.capfreq = 'S'
                    AND ln_account.nextpayday = '$capdate'::DATE)
                OR (ln_account.capfreq = 'M' AND ln_account.capday = $day)
                " . (!empty($capIntExpiredLoan) ? "OR '$capdate'::DATE - ln_account.enddate > 0" : " ") . "
                )
            AND ln_account.instid = :instid
            AND (   ln_account.statusid IN (2, 4, 8)
                OR (    ln_account.statusid = 3
                    AND ln_account.intstoptype = 1))
            AND   ln_account.baseint2cap
                + ln_account.adjbint2cap
                + ln_account.comint2cap
                + ln_account.adjcint2cap
                + ln_account.fineint2cap
                + ln_account.adjfint2cap
                <> 0";
        // + ln_account.ctacntno    2025.03.11 Тэнцлийн гадуурх хүүг кап хийх шаардлаггүй гэж үзэн хасав. Орхоо
        // + ln_account.ctcomacntno
        // + ln_account.ctfineacntno

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and ln_account.acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by ln_account.acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
        ]);
        // Log::debug($results);
        // Log::debug($sql);
        // Log::debug([
        //     $sysdate, $lastitem, $instid, $tomorrow
        // ]);
        // Log::debug(DB::getQueryLog());
        // DB::disableQueryLog();
        return $results;
    }

    /**
     * Зээлийн хүү капитализэшн хийх сарын эцэст
     */
    public function capLnEOMAcnts($sysdate, $lastitem, $instid)
    {
        $query = LnAccount::select(
            'ln_account.acntno',
            'ln_account.princbal',
            'ln_account.prodcode',
            'ln_account.curcode',
            'ln_account.nextpayday',
            'ln_account.baseint2cap',
            'ln_account.adjbint2cap',
            'ln_account.comint2cap',
            'ln_account.adjcint2cap',
            'ln_account.fineint2cap',
            'ln_account.adjfint2cap',
            'ln_account.brchno',
            'ln_account.statusid',
            'ln_account.intstopclass',
            'ln_account_type.ctloanrepayment',
            'ln_account.ctacntno as ctbint',
            'ln_account.ctcomacntno as ctcint',
            'ln_account.ctfineacntno as ctfint',
            DB::raw('0 AS intreturn'),
            DB::raw('0 AS intreturnamount')
        )
            ->join('ln_account_type', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                    ->whereColumn('ln_account.instid', 'ln_account_type.instid');
            })
            ->where('ln_account.capfreq', 'N')
            ->where('ln_account.instid', $instid)
            ->where(function ($query) {
                $query->whereBetween('ln_account.statusid', [2, 8])
                    ->orWhere(function ($query) {
                        $query->where('ln_account.statusid', 3)
                            ->where('ln_account.intstoptype', 1);
                    });
            })
            ->where(DB::raw('ln_account.baseint2cap + ln_account.adjbint2cap +
        ln_account.comint2cap + ln_account.adjcint2cap + ln_account.fineint2cap
         + ln_account.adjfint2cap'), '<>', 0);
        //  + ln_account.ctacntno  + ln_account.ctcomacntno + ln_account.ctfineacntno 2025.03.11 Тэнцлийн гадуурх хүүг кап хийх шаардлаггүй гэж үзэн хасав. Орхоо
        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('ln_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $query->orderBy('ln_account.acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800091
     */
    public function LnAcntHistDel($sysdate, $lastitem, $instid)
    {
        LnAccountHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад зээлийн дансны мэдээлэл авах ad800091
     */
    public function LnAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = LnAccount::where(function ($query) use ($instid, $sysdate) {
            $query->where('instid', $instid)
                ->whereNotIn('statusid', [0, 9]);
        })
            ->orWhere(function ($query) use ($instid, $sysdate) {
                $query->where('instid', $instid)
                    ->whereIn('statusid', [0, 9])
                    ->where('closeddate', $sysdate);
            })
            ->count();

        $sql = "
            INSERT INTO Ln_Account_Hist (
                acntno, txndate, brchno, custno, segcode, prodcode, curcode, name,
                name2, catcode, loantype, purpcode, intrate, termlen, termbasis,
                begdate, enddate, approvdate, approvamount, openeddate, closeddate,
                advdate, advamount, theorbal, princbal, capbint, capcint, capfint,
                paidlon, paidint, baseintdaily, comintdaily, fineintdaily, baseint2cap,
                comint2cap, fineint2cap, adjbint2cap, adjcint2cap, adjfint2cap, dueprinc,
                dueint, arreardate, clscode, condchg, fintxncount, lasttxndate, balchanged,
                advchanged, arrearchanged, bratechanged, cratechanged, fratechanged,
                prevstatus, closedtype, lonnum, appno, nextpayday, nexttheorbal, capfreq,
                capday, repayacntno, repaytype, payamount, payfreq, payday, calcbaltype,
                paymonth, recalcschd, nextcapday, finestop, finestopdate, ctacruel,
                ctacntno, repaypriority, redrawlimit, comdate, moramount, arreardateint,
                ctcomacruel, ctcomacntno, ctfineacruel, ctfineacntno, baseroundint,
                comroundint, fineroundint, arreardatecom, duecom, finecomintdaily,
                finecomroundint, hide, ctlineacntno, comcustno, statusbad, riskmanager,
                sellermanager, analysismanager, tellerbad, trackno, intratecom, intratefine,
                intratecomtier, intratefinetier, invacntno, collacntno, prevprincbal,
                prevbal, inffreq, auditmanager, gotinsurance, comamount, dueamount,
                payday2, statusbadapp, cecustno, lasttellertxndate, txndef, subpurpcode,
                insureorgcode, rootacntno, linebal, linebasebal, linemodule, linecurcode,
                lgriskrate, lcriskrate, lcunusedriskrate, lgunusedriskrate, lglcenddate,
                tellerfunc, createddate, intstoptype, intstopclass, cetype, cectacntno,
                ceinvacntno, ceinvintrate, cemovecomint, cemovefintint, autocls,
                previntstoptype, intstopteller, repayrtypecode, defaultamount, defaultdate,
                capbinte, schddate, nrsfromfile, prevdueamount, isdphold, dpacntholdid,
                escapemonths, assrlnacntno, clscodetrm, clscodeqlt, finelastacrueddate,
                sourcecode, bintrateruleid, greenpurpcode, greensubpurpcode,
                sendcreditbureo, tmp_princbal, tmp_capbint, tmp_capcint, tmp_capfint,
                tmp_acrbint, tmp_acrcint, tmp_acrfint, tmp_statuscode, tmp_brchno,
                tmp_prodcode, tmp_clscode, tmp_theorbal, tmp_begdate, tmp_enddate,
                tmp_dueprinc, tmp_dueint, tmp_duecom, tmp_arreardate, tmp_arreardateint,
                tmp_arreardatecom, tmp_bal, tmp_baseintdaily, tmp_comintdaily,
                tmp_fineintdaily, tmp_redrawlimit, tmp_finecomintdaily, tmp_dueamount,
                tmp_capbinte, tmp_clscodetrm, tmp_clscodeqlt, statusid, instid, created_by,
                lnsubtype, created_at, tmp_ctacntno, tmp_ctcomacntno, tmp_ctfineacntno
            )
            SELECT
                acntno, '$sysdate' AS txndate, brchno, custno, segcode, prodcode, curcode,
                name, name2, catcode, loantype, purpcode, intrate, termlen, termbasis,
                begdate, enddate, approvdate, approvamount, COALESCE(openeddate, created_at) AS openeddate,
                closeddate, advdate, advamount, theorbal, princbal, capbint, capcint, capfint, paidlon,
                paidint, baseintdaily, comintdaily, fineintdaily, baseint2cap, comint2cap, fineint2cap,
                adjbint2cap, adjcint2cap, adjfint2cap, dueprinc, dueint, arreardate, clscode, condchg,
                fintxncount, lasttxndate, balchanged, advchanged, arrearchanged, bratechanged, cratechanged,
                fratechanged, prevstatus, closedtype, lonnum, appno, nextpayday, nexttheorbal, capfreq,
                capday, repayacntno, repaytype, payamount, payfreq, payday, calcbaltype, paymonth,
                recalcschd, nextcapday, finestop, finestopdate, ctacruel, ctacntno, repaypriority,
                redrawlimit, comdate, moramount, arreardateint, ctcomacruel, ctcomacntno, ctfineacruel,
                ctfineacntno, baseroundint, comroundint, fineroundint, arreardatecom, duecom, finecomintdaily,
                finecomroundint, hide, ctlineacntno, comcustno, statusbad, riskmanager, sellermanager,
                analysismanager, tellerbad, trackno, intratecom, intratefine, intratecomtier, intratefinetier,
                invacntno, collacntno, prevprincbal, prevbal, inffreq, auditmanager, gotinsurance,
                comamount, dueamount, payday2, statusbadapp, cecustno, lasttellertxndate, txndef, subpurpcode,
                insureorgcode, rootacntno, linebal, linebasebal, linemodule, linecurcode, lgriskrate,
                lcriskrate, lcunusedriskrate, lgunusedriskrate, lglcenddate, tellerfunc, createddate,
                intstoptype, intstopclass, cetype, cectacntno, ceinvacntno, ceinvintrate, cemovecomint,
                cemovefintint, autocls, previntstoptype, intstopteller, repayrtypecode, defaultamount,
                defaultdate, capbinte, schddate, nrsfromfile, prevdueamount, isdphold, dpacntholdid,
                escapemonths, assrlnacntno, clscodetrm, clscodeqlt, finelastacrueddate, sourcecode,
                bintrateruleid, greenpurpcode, greensubpurpcode, sendcreditbureo,
                ROUND(tmp_princbal, 8), ROUND(tmp_capbint, 8), ROUND(tmp_capcint, 8), ROUND(tmp_capfint, 8),
                ROUND(tmp_acrbint, 8), ROUND(tmp_acrcint, 8), ROUND(tmp_acrfint, 8), tmp_statuscode,
                tmp_brchno, tmp_prodcode, tmp_clscode, ROUND(tmp_theorbal, 8), tmp_begdate, tmp_enddate,
                ROUND(tmp_dueprinc, 8), ROUND(tmp_dueint, 8), ROUND(tmp_duecom, 8), tmp_arreardate,
                tmp_arreardateint, tmp_arreardatecom, ROUND(tmp_bal, 8), ROUND(tmp_baseintdaily, 8),
                ROUND(tmp_comintdaily, 8), ROUND(tmp_fineintdaily, 8), ROUND(tmp_redrawlimit, 8),
                ROUND(tmp_finecomintdaily, 8), ROUND(tmp_dueamount, 8), ROUND(tmp_capbinte, 8),
                tmp_clscodetrm, tmp_clscodeqlt, statusid, instid, created_by, lnsubtype,
                 '$caldate' AS created_at, tmp_ctacntno, tmp_ctcomacntno, tmp_ctfineacntno
            FROM Ln_Account
            WHERE instid = :instid
            AND (statusid NOT IN (0, 9) OR (statusid IN (0, 9) AND closeddate = '$sysdate'))
        ";

        // Execute the SQL query
        DB::statement($sql, ['instid' => $instid]);
        return $results;
    }

    /**
     * Дараагийн төлбөр хийх өдөр, онолын үлдэгдлийг шинэчлэх
     */
    public function NextPayDaySelect($sysdate, $lastitem, $instid)
    {
        $sql = DB::table('ln_account AS u')
            ->selectRaw('u.acntno, u.brchno, COALESCE(MIN(sc.payday), u.enddate) AS nextpayday, COALESCE(MIN(sc.theorbal), 0) AS nexttheorbal')
            ->where(function ($query) use ($sysdate) {
                $query->where('u.nextpayday', '<=', $sysdate)
                    ->orWhereNull('u.nextpayday');
            })
            ->where('u.statusid', '>', 0)
            ->where('u.statusid', '<', 9)
            ->where('u.enddate', '>=', $sysdate)
            ->where('u.instid', '=', $instid)
            ->leftJoin(
                DB::raw("(SELECT MIN(s.payday) AS payday, s.acntno, s.instid
                      FROM ln_schd s
                      INNER JOIN ln_account ac ON s.acntno = ac.acntno AND s.instid = ac.instid
                      WHERE COALESCE(ac.nextpayday, '$sysdate') <= '$sysdate'
                      AND s.payday > '$sysdate'
                      AND ac.statusid > 0
                      AND ac.statusid < 9
                      AND s.instid = $instid
                      GROUP BY s.acntno, s.instid) t"),
                function ($join) {
                    $join->on('u.instid', '=', 't.instid')
                        ->on('u.acntno', '=', 't.acntno');
                }
            )
            ->leftJoin('ln_schd AS sc', function ($join) {
                $join->on('sc.payday', '=', 't.payday')
                    ->on('sc.acntno', '=', 't.acntno')
                    ->on('sc.instid', '=', 't.instid');
            })
            ->groupBy('u.acntno', 'u.brchno', 'u.enddate');

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('u.acntno', '>=', $lastitem->acntno);
        }

        // Log::debug($sql->toSql());
        $results = $sql->orderBy('u.acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Limit өөрчлөх дансуудын жагсаалт
     */
    public function NextRedrawLimitAcnts($sysdate, $lastitem, $instid)
    {

        $sql = LnAccount::select('ln_account.acntno', 'ln_account.brchno', 'ln_account.curcode', 'subquery.linelimit', 'ln_account.redrawlimit')
            ->joinSub(function ($query) use ($sysdate, $instid) {
                $query->select('acntno', DB::raw('MIN(linelimit) AS linelimit'))
                    ->from('ln_account_limit_schd')
                    ->where('startdate', $sysdate)
                    ->where('instid', $instid)
                    ->where('statusid', 1)
                    ->groupBy('acntno');
            }, 'subquery', function ($join) {
                $join->on('ln_account.acntno', '=', 'subquery.acntno');
            })
            ->where('ln_account.instid', $instid)
            ->whereNotIn('ln_account.statusid', [0, 5, 9])
            ->where('ln_account.enddate', '>=', $sysdate);

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('ln_account.acntno', '>=', $lastitem->acntno);
        }

        $results = $sql->orderBy('ln_account.acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Битүүмжтэй зээлийн дансны мэдээлэл авах
     */
    public function AdjustDepMorLonAcnts($sysdate, $lastitem, $instid)
    {
        $tyestoday = Carbon::createFromFormat('Y-m-d', $sysdate);
        $yestoday = $tyestoday->subDay()->format('Y-m-d');

        $query = DpHoldTxn::select(
            'lt.acntno as lnacntno',
            'l.curcode as lncurcode',
            'l.princbal',
            'l.intrate',
            'l.begdate',
            'l.enddate',
            'l.statusid as lnacntstatusid',
            'dp_hold_txn.jrno',
            'dp_hold_txn.holdamount',
            'dp_hold_txn.acntno as dpacntno',
            'd.curcode as dpcurcode',
            'dp_hold_txn.txndate',
            'dp_hold_txn.brchno',
            'dp_hold_txn.expiredate',
            'dp_hold_txn.holdtype',
            'p.yeardays',
            'p.depmorloanformula',
            'p.prodcode'
        )
            ->leftJoin('dp_account as d', function ($join) use ($instid) {
                $join->on('dp_hold_txn.acntno', '=', 'd.acntno')
                    ->where('d.instid', '=', $instid);
            })
            ->leftJoin('ln_account as l', function ($join) use ($instid) {
                $join->on('dp_hold_txn.morloanacntno', '=', 'l.acntno')
                    ->on('d.acntno', '=', 'l.collacntno')
                    ->where('l.instid', '=', $instid);
            })
            ->join(DB::raw("
            (
                select instid, acntno
                from ln_txn
                where txndate in ('$yestoday', '$sysdate')
                  and corr = 0
                  and txncode in (
                      'ln902010',
                      'ln902011',
                      'ln802011',
                      'ln902090',
                      'ln902091'
                  )
                  and instid = $instid
                group by instid, acntno
            ) as lt
        "), function ($join) use ($instid) {
                $join->on('l.acntno', '=', 'lt.acntno')
                    ->where('lt.instid', '=', $instid);
            })
            ->leftJoin('ln_account_type as p', function ($join) use ($instid) {
                $join->on('l.prodcode', '=', 'p.prodcode')
                    ->where('p.instid', '=', $instid);
            })
            ->where('p.depmorloan', '=', 1)
            ->where('dp_hold_txn.statusid', '=', 1)
            ->where('dp_hold_txn.instid', '=', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('dp_hold_txn.acntno', '>=', $lastitem->acntno);
        }

        $results = $query->orderBy('dp_hold_txn.acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Барьцаа хөрөнгийн зээлд үүрэг хүлээж буй дүнг бууруулах жагсаалт
     */

    public function AdjustMorObAmt($sysdate, $lastitem, $instid)
    {
        $tyestoday = Carbon::createFromFormat('Y-m-d', $sysdate);
        $yestoday = $tyestoday->subDay()->format('Y-m-d');

        $sql = "SELECT * from (
                    select
                    ln.princbal,
                    (select sum(tx.txnamount) from ln_txn tx where
                    tx.instid = am.instid and
                    tx.acntno = am.acntno and
                    tx.corr = 0 and
                    tx.txndate = :yesterday and
                    tx.txncode in ('ln902010','ln902011','ln802011')
                    ) AS txnamount,
                    mr.obamount,
                    mr.obpercent,
                    mr.costamount,
                    mt.adjobligation,
                    am.id,
                    am.acntno,
                    am.morno,
                    am.perlon,
                    am.amtlon,
                    am.permor,
                    am.amtmor,
                    am.releaseorder,
                    am.instid,
                    am.statusid,
                    am.morstatus,
                    row_number() over (
                        partition by am.instid, am.acntno
                        order by am.releaseorder desc, am.created_at desc, am.id desc
                    ) as rn
                    from ln_account_mor am
                    left join ln_account ln on ln.instid = am.instid and ln.acntno = am.acntno
                    left join ln_mor mr on mr.instid = am.instid and am.morno = mr.morno
                    left join ln_mor_type mt on mt.instid = am.instid and mr.prodcode = mt.prodcode
                    left join ln_account_type lt on lt.instid = am.instid and lt.prodcode = ln.prodcode
                    where
                    am.instid = :instid and
                    am.statusid = 1 and
                    am.amtmor > 0 and
                    mt.adjobligation = 1 and
                    am.morstatus = 2
                    ) rr
                    where rr.txnamount is not null
                    and rr.rn = 1
                ";
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and rr.acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by rr.acntno";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'yesterday' => $yestoday
        ]);
        return $results;
    }
    /**
     * Барьцаа хөрөнгийн үүрэгтэй зээл нь хаагдсан жагсаалт
     */

    public function ClosedLnMorObAmt($sysdate, $lastitem, $instid)
    {
        $sql = "select
                        ln.princbal,
                        mr.obamount,
                        mr.obpercent,
                        mr.costamount,
                        mt.adjobligation,
                        am.id,
                        am.acntno,
                        am.morno,
                        am.perlon,
                        am.amtlon,
                        am.permor,
                        am.amtmor,
                        am.releaseorder,
                        am.instid,
                        am.statusid,
                        am.morstatus
                from ln_account_mor am
                left join ln_account ln on ln.instid = am.instid and ln.acntno = am.acntno
                left join ln_mor mr on mr.instid = am.instid and am.morno = mr.morno
                left join ln_mor_type mt on mt.instid = am.instid and mr.prodcode = mt.prodcode
                left join ln_account_type lt on lt.instid = am.instid and lt.prodcode = ln.prodcode
                where
                am.instid = :instid and
                am.statusid = 1 and
                coalesce(am.amtmor, 0) >= 0 and
                ln.statusid in (0, 9) and
                mt.adjobligation = 1
                ";
        // and lt.depmorloan = 0
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and am.acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by am.acntno";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
        ]);
        return $results;
    }
    /**
     * Барьцаа хөрөнгийн зээлд үүрэг хүлээж буй дүнг бууруулах жагсаалт NEXT
     */

    public function AdjustNextMorObAmt($acntno, $instid)
    {
        $sql = "SELECT
                mr.obamount,
                mr.obpercent,
                mr.costamount,
                mt.adjobligation,
                am.id,
                am.acntno,
                am.morno,
                am.perlon,
                am.amtlon,
                am.permor,
                am.amtmor,
                am.releaseorder,
                am.instid,
                am.statusid,
                am.morstatus
                from ln_account_mor am
                left join ln_mor mr on mr.instid = am.instid and am.morno = mr.morno
                left join ln_mor_type mt on mt.instid = am.instid and mr.prodcode = mt.prodcode
                where
                am.instid = :instid and
                am.acntno = :acntno and
                am.statusid = 1 and
                am.morstatus = 2 and
                mt.adjobligation = 1
                order by am.releaseorder desc, am.created_at desc
                ";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'acntno' => $acntno
        ]);
        return $results;
    }
    /**
     * Зээлийн эргэн төлөлтийн жагсаалт
     */
    public function txnLnRepayAcnt($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT a.acntno,
                    a.repayacntno,
                    a.curcode,
                    a.princbal,
                    a.prodcode,
                    a.brchno,
                    a.repayrtypecode,
                    a.insuranceamount,
                    a.insureorgcode,
                    a.assrlnacntno,
                    da.curcode AS curcode1,
                        CASE
                            WHEN a.princbal > a.theorbal AND a.theorbal >= 0
                            THEN
                            a.princbal - a.theorbal
                            ELSE
                            0
                        END
                    + a.capbint
                    + a.capfint
                    + a.capcint
                    + (CASE pa.itemvalue
                            WHEN '1'
                            THEN
                                coalesce (a.fineint2cap, 0)
                                + coalesce (a.adjfint2cap, 0)
                                + coalesce (a.ctfineacntno, 0)
                            WHEN '2'
                            THEN
                                coalesce (a.fineint2cap, 0)
                                + coalesce (a.adjfint2cap, 0)
                                + coalesce (a.ctfineacntno, 0)
                                + coalesce (a.comint2cap, 0)
                                + coalesce (a.adjcint2cap, 0)
                                + coalesce (a.ctcomacntno, 0)
                                + coalesce (a.baseint2cap, 0)
                                + coalesce (a.adjbint2cap, 0)
                                + coalesce (a.ctacntno, 0)
                            ELSE
                                0
                        END) + CASE
                            WHEN a.insuranceamount IS NOT NULL
                            AND a.insureorgcode IS NOT NULL
                            AND a.assrlnacntno IS NOT NULL
                            THEN coalesce(nrs.suminsuranceamount, 0) - rp.balance - rp.insurancepaidamount
                            ELSE 0
                        END AS requiredamount,
                    CASE
                        WHEN coalesce (dh.holdtype, 0) = 3 THEN 0
                        ELSE da.currentbal - p.minbalance - coalesce (dh.holdbal, 0)
                    END AS availableamount,
                    CASE WHEN (a.theorbal >= 0  AND a.princbal - a.theorbal > 0) THEN a.princbal - a.theorbal ELSE 0 END AS dueprinc,
                    a.capbint,
                    a.capfint,
                    a.capcint,
                    coalesce (a.fineint2cap, 0) AS fineint2cap,
                    coalesce (a.adjfint2cap, 0) AS adjfint2cap,
                    a.ctfineacntno AS ctcurrentbal,
                    pl.autorepayamount,
                    coalesce (a.baseint2cap, 0) AS baseint2cap,
                    coalesce (a.comint2cap, 0) AS comint2cap,
                    coalesce (a.adjbint2cap, 0) AS adjbint2cap,
                    coalesce (a.adjcint2cap, 0) AS adjcint2cap,
                    a.ctacntno AS bctcurrentbal,
                    a.ctcomacntno AS comctcurrentbal
            FROM ln_account a
                    INNER JOIN ln_account_type pl
                    ON pl.prodcode = a.prodcode AND pl.instid = a.instid
                    INNER JOIN dp_account da
                    ON da.acntno = a.repayacntno AND da.instid = a.instid
                    INNER JOIN dp_account_type p
                    ON p.prodcode = da.prodcode AND p.instid = da.instid
                    LEFT JOIN dp_account_hold dh
                    ON dh.acntno = da.acntno AND dh.instid = da.instid -- бүхлээр битүүмжтэй дп данс орж ирэхгүй
                    LEFT JOIN GP_inst_gp pa
                    ON pa.itemname = 'IntCapBeforeLNPayment' AND pa.instid = a.instid
                    LEFT JOIN (
                        SELECT acntno, instid, SUM(insuranceamount) AS suminsuranceamount
                        FROM ln_schd
                        WHERE statusid = 1
                        AND payday <= :txndate
                        GROUP BY acntno, instid
                    ) nrs ON nrs.acntno = a.acntno AND nrs.instid = a.instid
                    LEFT JOIN ia_rec_pay rp ON rp.instid = a.instid and rp.recpayno::TEXT = a.assrlnacntno and rp.statusid != 9
            WHERE     (a.capbint > 0 OR a.capfint > 0 OR a.capcint > 0 OR a.dueprinc > 0 OR (
                            a.insuranceamount IS NOT NULL
                            AND a.insureorgcode IS NOT NULL
                            AND a.assrlnacntno IS NOT NULL
                            AND nrs.suminsuranceamount > (rp.balance + rp.insurancepaidamount)
                        ))
                    AND a.statusid > 1
                    AND a.statusid < 9
                    AND a.statusid <> 5
                    AND da.statusid = 4
                    AND p.procflag <> 'T'
                    AND a.instid = :instid
                    AND (CASE
                        WHEN coalesce (dh.holdtype, 0) = 3 THEN 0
                        ELSE da.currentbal - p.minbalance - coalesce (dh.holdbal, 0)
                    END) > 0.01
                    AND (CASE
                            WHEN coalesce (da.coveracntno, '0') = '0'
                            THEN
                            CASE
                                WHEN coalesce (dh.holdtype, 0) = 3
                                THEN
                                    0
                                ELSE
                                    da.currentbal
                                    - p.minbalance
                                    - coalesce (dh.holdbal, 0)
                            END
                            ELSE
                            1
                        END) > 0
                ";
        if ($lastitem && $lastitem->acntno) {
            $lnaccount = LnAccount::where('acntno', $lastitem->acntno)
                ->where('instid', $instid)->first();
            $sql = $sql . " and da.acntno >= '" . $lnaccount->repayacntno . "' ";
        }
        $sql = $sql . " order by a.repayAcntNo, a.repayPriority ";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'txndate' => $sysdate,
        ]);
        return $results;
    }
    /**
     * Зээлийн хүүг балансын гадуур автомат гаргах дансд
     */
    public function MoveOutOffBalance($sysdate, $lastitem, $instid, $gp)
    {
        $effectiveDuePrinc = $this->effectivePrincipalDueSql('ln_account', 'ln_account_type');
        if ($gp == 'A') {
            $query = DB::table('ln_account')
                ->join('ln_account_type', function ($join) {
                    $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                        ->on('ln_account.instid', '=', 'ln_account_type.instid')
                        ->where('ln_account_type.autocont', '=', 1)
                        ->where('ln_account_type.prodtype', '<>', 2);
                })
                ->select(
                    'ln_account.acntno',
                    'ln_account.curcode',
                    'ln_account.brchno',
                    'ln_account.clscode',
                    DB::raw('ln_account.baseint2cap+ln_account.adjbint2cap as baseint2cap'),
                    DB::raw('ln_account.comint2cap+ln_account.adjcint2cap as comint2cap'),
                    DB::raw('ln_account.fineint2cap+ln_account.adjfint2cap as fineint2cap'),
                    'ln_account.ctacruel',
                    'ln_account.ctcomacruel',
                    'ln_account.ctfineacruel',
                    'ln_account.ctacntno',
                    'ln_account.ctcomacntno',
                    'ln_account.ctfineacntno',
                    DB::raw("CASE WHEN $effectiveDuePrinc > 0 THEN coalesce ('$sysdate'::date - ln_account.arreardate, 0) ELSE 0 END as duedays"),
                    DB::raw("coalesce ('$sysdate'::date - ln_account.arreardateint, 0) as bintduedays"),
                    DB::raw("coalesce ('$sysdate'::date - ln_account.arreardatecom, 0) as cintduedays"),
                    'ln_account.statusid',
                    'ln_account_type.autoconttype',
                    'ln_account_type.autocontdueopt',
                    'ln_account_type.autocontcls',
                    'ln_account_type.autocontduedays',
                    'ln_account.intstoptype'
                )
                ->where('ln_account.statusid', '>', 0)
                ->where('ln_account.statusid', '<', 8)
                ->where('ln_account.instid', $instid)
                ->whereRaw("(
                    (
                        ln_account_type.autoconttype = 0
                        AND (
                            (ln_account.clscode >= ln_account_type.autocontcls AND ln_account.intstoptype = 0)
                            OR (ln_account.clscode < ln_account_type.autocontcls AND ln_account.intstoptype = 1 AND ln_account.statusid = 3)
                        )
                    )
                    OR (
                        ln_account_type.autoconttype = 1
                        AND (
                            (
                                ln_account.intstoptype = 0
                                AND ln_account_type.autocontduedays > 0
                                AND (
                                    (
                                        ln_account_type.autocontdueopt = 0
                                        AND (
                                            (CASE WHEN $effectiveDuePrinc > 0 THEN COALESCE('$sysdate'::date - ln_account.arreardate, 0) ELSE 0 END) >= ln_account_type.autocontduedays
                                            OR COALESCE('$sysdate'::date - ln_account.arreardateint, 0) >= ln_account_type.autocontduedays
                                            OR COALESCE('$sysdate'::date - ln_account.arreardatecom, 0) >= ln_account_type.autocontduedays
                                        )
                                    )
                                    OR (
                                        ln_account_type.autocontdueopt = 1
                                        AND (CASE WHEN $effectiveDuePrinc > 0 THEN COALESCE('$sysdate'::date - ln_account.arreardate, 0) ELSE 0 END) >= ln_account_type.autocontduedays
                                    )
                                    OR (
                                        ln_account_type.autocontdueopt = 2
                                        AND (
                                            COALESCE('$sysdate'::date - ln_account.arreardateint, 0) >= ln_account_type.autocontduedays
                                            OR COALESCE('$sysdate'::date - ln_account.arreardatecom, 0) >= ln_account_type.autocontduedays
                                        )
                                    )
                                )
                            )
                            OR (
                                ln_account.intstoptype = 1
                                AND ln_account.statusid = 3
                                AND (
                                    (
                                        ln_account_type.autocontdueopt = 0
                                        AND (
                                            ln_account_type.autocontduedays <= 0
                                            OR (
                                                (CASE WHEN $effectiveDuePrinc > 0 THEN COALESCE('$sysdate'::date - ln_account.arreardate, 0) ELSE 0 END) < ln_account_type.autocontduedays
                                                AND COALESCE('$sysdate'::date - ln_account.arreardateint, 0) < ln_account_type.autocontduedays
                                                AND COALESCE('$sysdate'::date - ln_account.arreardatecom, 0) < ln_account_type.autocontduedays
                                            )
                                        )
                                    )
                                    OR (
                                        ln_account_type.autocontdueopt = 1
                                        AND (CASE WHEN $effectiveDuePrinc > 0 THEN COALESCE('$sysdate'::date - ln_account.arreardate, 0) ELSE 0 END) < ln_account_type.autocontduedays
                                    )
                                    OR (
                                        ln_account_type.autocontdueopt = 2
                                        AND COALESCE('$sysdate'::date - ln_account.arreardateint, 0) < ln_account_type.autocontduedays
                                        AND COALESCE('$sysdate'::date - ln_account.arreardatecom, 0) < ln_account_type.autocontduedays
                                    )
                                )
                            )
                        )
                    )
                )");
        } else if ($gp == 'Y') {
            $rearDays = CoreService::getInstGp($instid, 'MoveOutOffBalDays');
            $effectiveDuePrinc = $this->effectivePrincipalDueSql('A', 'P');
            $query = DB::table(DB::raw("(
                select
                    A.acntno, A.curcode, A.brchno,
                    A.capbint, A.capcint, A.capfint,
                    A.baseint2cap + A.adjbint2cap as baseint2cap,
                    A.comint2cap + A.adjcint2cap as comint2cap,
                    A.fineint2cap + A.adjfint2cap as fineint2cap,
                    A.ctacruel, A.ctcomacruel, A.ctfineacruel,
                    A.ctacntno, A.ctcomacntno, A.ctfineacntno,
                    CASE WHEN $effectiveDuePrinc > 0 THEN A.arreardate ELSE NULL END as duedate,
                    A.arreardateint as bintduedate,
                    A.arreardatecom as cintduedate,
                    A.statusid, A.clscode
                from ln_account as A
                INNER JOIN ln_account_type as P ON A.prodcode = P.prodcode and A.instid = P.instid and P.prodtype <> 2
                where A.statusid in (1,4,5)
                  and ($effectiveDuePrinc > 0 or A.dueint > 0 or A.duecom > 0) and A.instid = '$instid'::int
            ) as subquery"))
                ->whereRaw("(
                (coalesce ('$sysdate'::date - subquery.duedate, 0) >= '$rearDays'::int) or
                (coalesce ('$sysdate'::date - subquery.bintduedate, 0) >= '$rearDays'::int) or
                (coalesce ('$sysdate'::date - subquery.cintduedate, 0) >= '$rearDays'::int) or
                subquery.clscode >= 3
            )");
        }
        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $query->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Зээлийн шугамын хязгаарын гүйлгээ хийх дансд
     */
    public function CreditLineTxn($sysdate, $lastitem, $instid)
    {
        $query = DB::table('ln_account')
            ->leftJoin('ln_account_type', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type.instid');
            })
            ->select(
                'ln_account.acntno',
                'ln_account.brchno',
                'ln_account.curcode',
                'ln_account.statusid',
                'ln_account.redrawlimit',
                DB::raw("
                CASE
                    WHEN ln_account_type.prodtype = 2 THEN (ln_account.redrawlimit - ln_account.linebal)
                    ELSE (ln_account.redrawlimit - ln_account.princbal)
                END as redraw
            "),
                'ln_account.ctlineacntno as currentbal',
                'ln_account_type.autoctltype',
                'ln_account.custno',
                'ln_account.segcode',
                'ln_account.rootacntno',
                'ln_account.name',
                'ln_account.name2'
            )
            ->where(function ($q) use ($sysdate) {
                $q->where(function ($q1) {
                    $q1->where('ln_account.statusid', '>', 0)
                        ->where('ln_account.statusid', '<', 9);
                })
                    ->orWhere(function ($q2) use ($sysdate) {
                        $q2->whereIn('ln_account.statusid', [0, 9])
                            ->whereDate('ln_account.closeddate', '=', $sysdate);
                    });
            })
            ->whereRaw("
            (
                CASE
                    WHEN ln_account_type.prodtype = 2
                        AND (ROUND(ln_account.redrawlimit - ln_account.linebal, 2) > 0 OR ln_account.ctlineacntno > 0)
                    THEN 1
                    WHEN ln_account_type.prodtype != 2
                        AND (ROUND(ln_account.redrawlimit - ln_account.princbal, 2) > 0 OR ln_account.ctlineacntno > 0)
                    THEN 1
                    ELSE 0
                END
            ) = 1
        ")
            ->whereNotNull('ln_account_type.autoctltype')
            ->where('ln_account.instid', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query->where('ln_account.acntno', '>=', $lastitem->acntno);
        }
        return $query->orderBy('ln_account.acntno', 'ASC')->get();
    }

    /**
     * Худалдсан зээлийн үлдэгдэл тэнцэлийн гадуур тохируулах дансны жагсаалт
     */
    public function CeCtAcntTxn($sysdate, $lastitem, $instid)
    {
        $query = DB::table('ln_account')
            ->leftJoin('ln_account_type', function ($join) {
                $join->on('ln_account.prodcode', '=', 'ln_account_type.prodcode')
                    ->on('ln_account.instid', '=', 'ln_account_type.instid');
            })
            ->select(
                'ln_account.acntno',
                'ln_account.brchno',
                'ln_account.curcode',
                'ln_account.statusid',
                'ln_account.princbal',
                DB::raw("COALESCE(ln_account.cectacntno::FLOAT, 0) as cectbal"),
                'ln_account.cetype',
                'ln_account_type.cecttype as cectgl',
                'ln_account.custno',
                'ln_account.segcode',
                'ln_account.name',
                'ln_account.name2'
            )
            ->where('ln_account.instid', $instid)
            ->where('ln_account.cetype', '=', 0)
            ->whereRaw("(
                (COALESCE(ln_account.cectacntno::FLOAT, 0) <> ln_account.princbal AND ln_account.statusid = 8)
                OR (COALESCE(ln_account.cectacntno::FLOAT, 0) <> 0 AND ln_account.statusid = 9)
              )")
            ->whereNotNull('ln_account_type.cecttype');

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $query->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800139
     */
    public function LnMorMonthlyHistDel($sysdate, $lastitem, $instid)
    {
        LnMorHistMonthly::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад авлагын мэдээлэл авах ad800139
     */
    public function LnMorMonthlyHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = LnMor::select(
            DB::raw("'$sysdate' as txndate"),
            'morno',
            'custno',
            'prodcode',
            'mrtcode',
            'name',
            'name2',
            'loc',
            'regno',
            'regtypecode',
            'certno',
            'certtypecode',
            'morprice',
            'docdesc',
            'costingdate',
            'morstatus',
            'costamount',
            'costcurcode',
            'saleamount',
            'salecurcode',
            'otherdesc',
            'ownername',
            'brchno',
            'subcode',
            'costmarket',
            'collacntno',
            'addr1',
            'addr2',
            'addr3',
            'coord_lon',
            'coord_lat',
            'w3w',
            'zipcode',
            'ctacntno',
            'costcount',
            'costperamount',
            'totalsquare',
            'roomcount',
            'persqmprice',
            'squaremeasure',
            'obamount',
            'obpercent',
            'statusid',
            'instid',
            'created_by',
            'updated_by',
            'updated_at',
            DB::raw("'$caldate' AS created_at")
        )
            ->where('instid', $instid)->where('statusid', '!=', 0)->where('morstatus', 2)->get();
        return $results;
    }
}
