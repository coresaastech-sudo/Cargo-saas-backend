<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Dp\Entities\DpAccount;
use Modules\Dp\Entities\DpAccountHist;
use Modules\Dp\Entities\DpAccountIntRate;
use Modules\Gp\Enums\EodContinueResponseCodesEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Services\DpTxnService;
use TypeError;

class DpEodService
{

    public function defineDPCrIntRate($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate);
        $sql = DpAccount::select(
            'dp_account.crintrate',
            'dp_account_type.useratetier',
            'dp_account_type.intrate',
            'dp_account_type.craccruelfreq',
            'dp_account.totalbalperiod',
            'dp_account.prodcode',
            'dp_account.currentbal',
            'dp_account.minbalance',
            'dp_account.acntno',
            'dp_account.brchno',
        )
            ->selectRaw('CASE
                    WHEN dp_account_type.useratetier = 0 THEN dp_account_type.intrate
                    WHEN dp_account_type.useratetier = 1 THEN
                        COALESCE(
                            (SELECT MIN(r.intrate)
                            FROM dp_account_type_int_rate r
                            WHERE r.prodcode = dp_account_type.prodcode  AND r.instid = dp_account_type.instid
                                AND CASE dp_account_type.craccruelfreq
                                    WHEN \'M\' THEN
                                        CASE
                                            WHEN dp_account_type.crintmethod = 3 THEN dp_account.minbalance
                                            WHEN dp_account_type.crintmethod = 4 THEN dp_account.currentbal
                                            ELSE dp_account.totalbalperiod / ' . $csysdate->daysInMonth . '
                                        END
                                    ELSE
                                        CASE
                                            WHEN dp_account_type.crintmethod = 4 THEN dp_account.currentbal
                                            ELSE dp_account.currentbal
                                        END
                                END
                                BETWEEN r.minamount AND r.maxamount - 0.001),
                            dp_account_type.intrate
                        )
                END AS INTRATENEW')
            ->leftJoin('dp_account_type', function ($join) {
                $join->on('dp_account.prodcode', '=', 'dp_account_type.prodcode')
                    ->on('dp_account.instid', '=', 'dp_account_type.instid');
            })
            ->where('dp_account.currentbal', '>', 0)
            ->where('dp_account.instid', $instid)
            ->where(function ($query) {
                $query->where('dp_account_type.crintratechg', 0)
                    ->orWhere(function ($query) {
                        $query->where('dp_account_type.crintratechg', 1)
                            ->where('dp_account.crintrateacnt', 0);
                    });
            });
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('dp_account.acntno', 'ASC')->get();

        return $results;
    }

    public function defineDPCrIntRateAcnt($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate);

        $sql = DpAccount::select(
            'dp_account.crintrate',
            'dp_account_type.useratetier',
            'dp_account_type.intrate',
            'dp_account_type.craccruelfreq',
            'dp_account.totalbalperiod',
            'dp_account.prodcode',
            'dp_account.currentbal',
            'dp_account.minbalance',
            'dp_account.acntno',
            'dp_account.brchno',
        )
            ->selectRaw('COALESCE(
                            (SELECT MIN(r.intrate)
                            FROM dp_account_int_rate r
                            WHERE r.acntno = dp_account.acntno AND r.instid = dp_account.instid
                                AND CASE dp_account_type.craccruelfreq
                                    WHEN \'M\' THEN
                                        CASE
                                            WHEN dp_account_type.crintmethod = 3 THEN dp_account.minbalance
                                            WHEN dp_account_type.crintmethod = 4 THEN dp_account.currentbal
                                            ELSE dp_account.totalbalperiod / ' . $csysdate->daysInMonth . '
                                        END
                                    ELSE
                                        CASE
                                            WHEN dp_account_type.crintmethod = 4 THEN dp_account.currentbal
                                            ELSE dp_account.currentbal
                                        END
                                END
                                BETWEEN r.minamount AND r.maxamount - 0.001),
                            dp_account_type.intrate
                        )
                AS INTRATENEW')
            ->join('dp_account_type', function ($join) {
                $join->on('dp_account.prodcode', '=', 'dp_account_type.prodcode')
                    ->on('dp_account.instid', '=', 'dp_account_type.instid');
            })
            ->where('dp_account.currentbal', '>', 0)
            ->where('dp_account.useratetier', 1)
            ->where('dp_account.instid', $instid)
            ->where(function ($query) {
                $query->where('dp_account_type.crintratechg', 2)
                    ->orWhere(function ($query) {
                        $query->where('dp_account_type.crintratechg', 1)
                            ->where('dp_account.crintrateacnt', 1);
                    });
            });
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('dp_account.acntno', 'ASC')->get();
        return $results;
    }

    public function calcDPCrDailyInt($sysdate, $lastitem, $instid)
    {
        $sysdate = new Carbon($sysdate);
        $minus = " 0 ";
        $multpTheor = " 1 ";
        $multp = " 1 ";

        if ($sysdate->day == 31) {
            $minus = "CASE WHEN dp_account_type.CrIntDayOption = 0 THEN 0 ELSE dp_account.PrevBal END "; // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол өмнөх өдрийн үлдэгдлийг хасна
            $multp = " 1 ";  // аль ч аргын хувьд ижил
            $multpTheor = " case when dp_account_type.CrIntMethod = 4 then 0 else 1 end ";
        } else if ($sysdate->copy()->daysInMonth == 28) {
            // нам жилийн 2р сарын сүүлчийн өдөр байна
            $minus = " 0 "; // аль ч аргын хувьд ижил
            $multp = " CASE WHEN dp_account_type.CrIntDayOption = 0 THEN 1 ELSE 3 END ";  // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол 3р үржүүлнэ
        } else if ($sysdate->copy()->daysInMonth == 29) {
            // өндөр жилийн 2р сарын сүүлчийн өдөр байна
            $minus = " 0 "; // аль ч аргын хувьд ижил
            $multp = " CASE WHEN dp_account_type.CrIntDayOption = 0 THEN 1 ELSE 2 END ";  // Хэрэв тэнцүү хоногт сараар хүү тооцдог бол 2р үржүүлнэ
        }
        $digitCount = CoreService::getInstGp($instid, 'DigitCount');
        $sql = DpAccount::select(
            [
                'dp_account.drdailyint',
                'dp_account.drint2acr',
                'dp_account.drroundint',
                'dp_account.drfinedailyint',
                'dp_account.drfineroundint',
                'dp_account.crdailyint',
                'dp_account.crint2acr',
                'dp_account.balchanged',
                'dp_account.crroundint',
                'dp_account.acntno',
                'dp_account_type.procflag',
                'dp_account.brchno',
            ]
        )->selectRaw(
            'ROUND (
                CASE
                    WHEN (   ' . $sysdate->day . ' <> 31
                          OR dp_account_type.CrIntDayOption = 0
                          OR dp_account.Tmp_ProdCode IS NULL
                          OR dp_account.Tmp_ProdCode = dp_account.ProdCode)
                    THEN
                          CASE
                              WHEN dp_account_type.CrIntMethod = 1
                              THEN
                                  (  (dp_account.CurrentBal - ' . $minus . ') * ' . $multp . '
                                   - CASE COALESCE(dp_account_type.CRINTRealBal, 0)
                                         WHEN 1
                                         THEN
                                             CASE
                                                 WHEN (dp_account.CurrentBal - ' . $minus . ') * ' . $multp . ' <
                                                      dp_account_type.MinBalance
                                                 THEN
                                                     (dp_account.CurrentBal - ' . $minus . ') * ' . $multp . '
                                                 ELSE
                                                     dp_account_type.minbalance
                                             END
                                         WHEN 2
                                         THEN
                                             CASE
                                                 WHEN dp_account.CurrentBal < dp_account_type.MinBalance
                                                 THEN
                                                     (dp_account.CurrentBal - ' . $minus . ') * ' . $multp . '
                                                 ELSE
                                                     0
                                             END
                                         ELSE
                                             0
                                     END)
                              ELSE
                                  (  (dp_account.currentbal - ' . $minus . ') * ' . $multp . ' * ' . $multpTheor . '
                                   -   CASE COALESCE(dp_account_type.CRINTRealBal, 0)
                                           WHEN 1
                                           THEN
                                               CASE
                                                   WHEN (dp_account.currentbal - ' . $minus . ') * ' . $multp . ' <
                                                        dp_account_type.MinBalance
                                                   THEN
                                                       dp_account.currentbal - ' . $minus . ' * ' . $multp . '
                                                   ELSE
                                                       dp_account_type.minbalance
                                               END
                                           WHEN 2
                                           THEN
                                               CASE
                                                   WHEN dp_account.currentbal <
                                                        dp_account_type.MinBalance
                                                   THEN
                                                       (dp_account.currentbal - ' . $minus . ') * ' . $multp . '
                                                   ELSE
                                                       0
                                               END
                                           ELSE
                                               0
                                       END
                                     * ' . $multpTheor . ')
                          END
                        * (COALESCE(dp_account.CrIntRate, 0) / dp_account_type.CrIntYearDays)
                        / 100
                    ELSE
                            CASE
                                WHEN dp_account_type.CrIntMethod = 1
                                THEN
                                    (  dp_account.CurrentBal
                                     - CASE COALESCE(dp_account_type.CRINTRealBal, 0)
                                           WHEN 1
                                           THEN
                                               CASE
                                                   WHEN dp_account.CurrentBal <
                                                        dp_account_type.MinBalance
                                                   THEN
                                                       dp_account.CurrentBal
                                                   ELSE
                                                       dp_account_type.MinBalance
                                               END
                                           WHEN 2
                                           THEN
                                               CASE
                                                   WHEN dp_account.CurrentBal <
                                                        dp_account_type.MinBalance
                                                   THEN
                                                       dp_account.CurrentBal
                                                   ELSE
                                                       0
                                               END
                                           ELSE
                                               0
                                       END)
                                ELSE
                                    (  dp_account.currentbal
                                     - CASE COALESCE(dp_account_type.CRINTRealBal, 0)
                                           WHEN 1
                                           THEN
                                               CASE
                                                   WHEN dp_account.currentbal <
                                                        dp_account_type.MinBalance
                                                   THEN
                                                       dp_account.currentbal
                                                   ELSE
                                                       dp_account_type.MinBalance
                                               END
                                           WHEN 2
                                           THEN
                                               CASE
                                                   WHEN dp_account.currentbal <
                                                        dp_account_type.MinBalance
                                                   THEN
                                                       dp_account.currentbal
                                                   ELSE
                                                       0
                                               END
                                           ELSE
                                               0
                                       END)
                            END
                          * (COALESCE(dp_account.CrIntRate, 0) / dp_account_type.CrIntYearDays)
                          / 100
                          * ' . $multpTheor . '
                        -   (  dp_account.PrevBal
                             - CASE COALESCE(P1.CRINTRealBal, 0)
                                   WHEN 1
                                   THEN
                                       CASE
                                           WHEN dp_account.PrevBal < P1.MinBalance
                                           THEN
                                               dp_account.PrevBal
                                           ELSE
                                               P1.MinBalance
                                       END
                                   WHEN 2
                                   THEN
                                       CASE
                                           WHEN dp_account.PrevBal < P1.MinBalance
                                           THEN
                                               dp_account.PrevBal
                                           ELSE
                                               0
                                       END
                                   ELSE
                                       0
                               END)
                          * (COALESCE(dp_account.TMP_CrIntRate, 0) / P1.CrIntYearDays)
                          / 100
                          * ' . $multpTheor . '
                END,
                ' . $digitCount . ')    AS NewAcr'
        )->join('dp_account_type', function ($join) {
            $join->on('dp_account.prodcode', '=', 'dp_account_type.prodcode')
                ->on('dp_account.instid', '=', 'dp_account_type.instid');
        })->leftJoin('dp_account_type as p1', function ($join) {
            $join->on('dp_account.tmp_prodcode', '=', 'p1.prodcode')
                ->on('dp_account.instid', '=', 'p1.instid');
        })->where('dp_account.statusid', '>', 2)
            ->where('dp_account.currentbal', '>', 0)
            ->where('dp_account.instid', $instid)
            ->where(function ($query) {
                $query->where('dp_account_type.crintmethod', 1)
                    ->orWhere('dp_account_type.crintmethod', 4);
            })
            ->where('dp_account_type.craccruelfreq', 'D');

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('dp_account.acntno', 'ASC')->get();
        return $results;
    }

    public function calcDPCrMOMInt($sysdate, $lastitem, $instid)
    {
        /// Кредит хүү тооцоолох - сараар, бүх төрлийн үлдэгдлээс, сар бүр тэнцүү 30 хоногтой гэж тооцно,
        /// хугацаа нь дуусаж байгаа хадгаламжийн
        // сарын эцсийн өдөр бол энэ функцыг дуудахгүй.
        $csysdate = Carbon::parse($sysdate);
        $lastday = $csysdate->daysInMonth;
        $nextdate = $csysdate->copy()->addDay();
        $digitCount = CoreService::getInstGp($instid, 'DigitCount');
        $openeddate = CoreService::getEodSysdate($instid);
        $sql = DpAccount::select(
            'dp_account.crint2acr',
            'dp_account.totalbalperiod',
            'dp_account.crroundint',
            'dp_account.acntno',
            'dp_account.brchno',
        )
            ->selectRaw('ROUND (
                CASE dp_account_type.CRINTMETHOD
                    WHEN 1
                    THEN
                        (  dp_account.TOTALBALPERIOD
                         * COALESCE(dp_account.CRINTRATE, 0)
                         / CASE
                               WHEN dp_account_type.CRINTDAYOPTION = 1 THEN 360
                               ELSE dp_account_type.CRINTYEARDAYS
                           END
                         / 100)            /*  САРЫН БОДИТ ҮЛДЭГДЛЭЭС ХҮҮ ТООЦОХ */
                    WHEN 2
                    THEN
                        (  dp_account.TOTALBALPERIOD
                         * COALESCE(dp_account.CRINTRATE, 0)
                         / 12
                         / CASE WHEN dp_account_type.CRINTDAYOPTION = 1 THEN 30 ELSE ' . $lastday . ' END
                         / 100)            /*  САРЫН ДУНДАЖ ҮЛДЭГДЛЭЭС ХҮҮ ТООЦОХ*/
                    WHEN 3
                    THEN
                        (  dp_account.MINBALANCE
                         * COALESCE(dp_account.CRINTRATE, 0)
                         / 12
                         / CASE WHEN dp_account_type.CRINTDAYOPTION = 1 THEN 30 ELSE ' . $lastday . ' END
                         / 100)            /* САРЫН MINIMUM ҮЛДЭГДЛЭЭС ХҮҮ ТООЦОХ*/
                    WHEN 4
                    THEN
                        (  dp_account.CURRENTBAL
                         * COALESCE(dp_account.CRINTRATE, 0)
                         / 12
                         / CASE WHEN dp_account_type.CRINTDAYOPTION = 1 THEN 30 ELSE ' . $lastday . ' END
                         / 100)                                /* ОНОЛЫН ҮЛДЭГДЭЛ*/
                END,
                ' . $digitCount . ')    AS NEWCRINTACR')
            ->join('dp_account_type', function ($join) {
                $join->on('dp_account.prodcode', '=', 'dp_account_type.prodcode')
                    ->on('dp_account.instid', '=', 'dp_account_type.instid');
            })
            ->where('dp_account.currentbal', '>', 0)
            ->where('dp_account.statusid', '>', 2)
            ->where('dp_account_type.craccruelfreq', 'M')
            ->where('dp_account_type.procflag', 'T')
            ->where('dp_account.termexpdate', $nextdate)
            ->where('dp_account.openeddate', $openeddate)
            ->where('dp_account.instid', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('dp_account.acntno', 'ASC')->get();
        return $results;
    }

    public function CreateDPCrAcrJrl($sysdate, $lastitem, $instid)
    {
        $txnCode = "'dp901041'";
        $txndesc = 'Кредит хүү акрулдав. (EOD)';
        $sql = DpAccount::select(
            'dp_account.brchno',
            'dp_account.acntno',
            'dp_account.prodcode'
        )
            ->leftjoin('GP_inst_qual', function ($join) {
                $join->on('dp_account.prodcode', '=', 'GP_inst_qual.prodcode')
                    ->on('dp_account.instid', '=', 'GP_inst_qual.instid')
                    ->where('GP_inst_qual.statusid', '=', 1);
            })
            ->where('dp_account.currentbal', '>', 0)
            ->where('dp_account.statusid', '>', 2)
            ->whereRaw('dp_account.crint2acr + COALESCE(dp_account.bonus, 0) <> 0')
            ->whereRaw("COALESCE(GP_inst_qual.txncode, $txnCode) = $txnCode")
            ->where('dp_account.instid', $instid);
        // Log::debug($sql->toSql());
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('dp_account.acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Депозит дансны үлдэгдэл түр хадгалах ad800052
     */
    public function CreateTmpDPBals($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate);

        $sql = DpAccount::select([
            'dp_account.acntno',
            'dp_account.currentbal',
            'dp_account.statusid',
            'dp_account.prodcode',
            'dp_account.brchno',
            'crintrate',
            'termstartdate',
            'termexpdate',
            'drcasint2cap',
            'drfine2cap',
            'drcom2cap',
            'odclscode',
            'drcasbalance',
            'dp_account.taxamount',
            'dp_account.crcaptotal2',
            'dp_account.odclscodetrm',
            'dp_account.odclscodeqlt'
        ])
            ->selectRaw('dp_account.crint2cap + dp_account.cradjint as crint2cap')
            ->selectRaw("drint2cap + case when procflag ='C' then 0 else dradjint end as drint2cap")
            ->selectRaw("drint2acr + case when procflag ='C' then dradjint else 0 end as drint2acr")
            ->selectRaw("drcasint2acr + drcasintadj as drcasint2acr")
            ->selectRaw("drcom2acr + drcomadjint as drcom2acr")
            ->selectRaw("drfine2acr + drfineadjint as drfine2acr")
            ->leftJoin('dp_account_type AS p', function ($join) use ($instid) {
                $join->on('dp_account.prodcode', '=', 'p.prodcode')
                    ->on('dp_account.instid', '=', 'p.instid');
            })
            ->where(function ($query) {
                $query->whereRaw('COALESCE(dp_account.tmp_bal::varchar, \'0\') <> COALESCE(dp_account.currentbal::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_crint2cap::varchar, \'0\') <> COALESCE((dp_account.crint2cap + dp_account.cradjint)::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_statuscode::varchar, \'0\') <> COALESCE(dp_account.statusid::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_prodcode::varchar, \'0\') <> COALESCE(dp_account.prodcode::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_brchno, \'\') <> COALESCE(dp_account.brchno, \'\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_crintrate::varchar, \'0\') <> COALESCE(dp_account.crintrate::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_termstartdate::date, \'0001-01-01\'::date) <> COALESCE(dp_account.termstartdate::date, \'0001-01-01\'::date)')
                    ->orWhereRaw('COALESCE(dp_account.tmp_termexpdate::date, \'0001-01-01\'::date) <> COALESCE(dp_account.termexpdate::date, \'0001-01-01\'::date)')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drint2cap::varchar, \'0\') <> COALESCE((drint2cap + CASE WHEN procflag = \'C\' THEN 0 ELSE dradjint END)::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drcasint2cap::varchar, \'0\') <> COALESCE(dp_account.drcasint2cap::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drfine2cap::varchar, \'0\') <> COALESCE(dp_account.drfine2cap::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drcom2cap::varchar, \'0\') <> COALESCE(dp_account.drcom2cap::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_odclscode::varchar, \'0\') <> COALESCE(dp_account.odclscode::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drint2acr::varchar, \'0\') <> COALESCE((drint2acr + CASE WHEN procflag = \'C\' THEN dradjint ELSE 0 END)::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drcasint2acr::varchar, \'0\') <> COALESCE((drcasint2acr + drcasintadj)::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drcom2acr::varchar, \'0\') <> COALESCE((drcom2acr + drcomadjint)::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drfine2acr::varchar, \'0\') <> COALESCE((drfine2acr + drfineadjint)::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_drcasbalance::varchar, \'0\') <> COALESCE(dp_account.drcasbalance::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_taxamount::varchar, \'0\') <> COALESCE(dp_account.taxamount::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_crcaptotal2::varchar, \'0\') <> COALESCE(dp_account.crcaptotal2::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_odclscodetrm::varchar, \'0\') <> COALESCE(dp_account.odclscodetrm::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(dp_account.tmp_odclscodeqlt::varchar, \'0\') <> COALESCE(dp_account.odclscodeqlt::varchar, \'0\')');
            })
            ->where('dp_account.instid', '=', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('dp_account.acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Хугацаа нь дуусах хадгаламжийн хүүний капитализэшн хийх ad800066
     * Дансанд өдөр тохируулсан хадгаламжийн хүүний капитализэшн хийх
     */
    public function capIntDPOnTerm($sysdate, $lastitem, $instid)
    {
        $sub = DB::table('dp_account as a')
            ->join(DB::raw('(select ?::date as sysdate) as s'), DB::raw('1'), '=', DB::raw('1'))
            ->addBinding($sysdate, 'join')  // <-- энэ binding зөвхөн join-д

            ->select(
                'a.acntno',
                'a.brchno',
                'a.prodcode',
                'a.curcode',
                'c.avgrate',
                'a.crint2cap',
                'a.cradjint',
                'a.crcapmethod',
                'a.crcapacntmod',
                'a.crcapacnt',
                'a.termexpdate',
                'a.termstartdate',
                'p.crcapday',
                'p.crcapfreq',
                's.sysdate',
                DB::raw("s.sysdate AS compare_date"),
                DB::raw("
                fn_dp_nextcapday(
                    s.sysdate,
                    a.termstartdate,
                    a.termexpdate,
                    p.crcapday,
                    p.crcapfreq,
                    a.cracapday
                ) AS nextcapday
                ")
            )
            ->join('dp_account_type as p', function ($j) {
                $j->on('a.prodcode', '=', 'p.prodcode')
                    ->on('p.instid', '=', 'a.instid');
            })
            ->join('GP_inst_cur as c', function ($j) {
                $j->on('a.curcode', '=', 'c.curcode')
                    ->on('c.instid', '=', 'a.instid')
                    ->where('c.statusid', 1);
            })
            // эх үүсвэр оруулалтын данс биш
            ->whereNotExists(function ($sub) {
                $sub->from('dp_inv_account as i')
                    ->whereColumn('i.invacntno', 'a.acntno')
                    ->whereColumn('i.instid', 'a.instid')
                    ->where('i.statusid', 1);
            })
            // капитализ хийх дүн байгаа эсэх (NULL-safe)
            ->whereRaw('(COALESCE(a.crint2cap,0) + COALESCE(a.cradjint,0)) <> 0')
            // хугацаат хадгаламж
            ->where('p.procflag', '=', 'T')
            ->whereIn('p.crcapday', ['M', 'A'])
            // институц
            ->where('a.instid', '=', $instid)
            // идэвхтэй (танай enum-д тааруулна)
            ->where('a.statusid', '>', 1);

        // Хугацаа дууссан эсвэл тухайн өдөр нь кап хийх өдөр таарсан дансууд
        $q = DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub) // bindings-г дамжуулна
            ->whereRaw("
                sub.compare_date = sub.nextcapday
                OR (
                    sub.termexpdate = sub.compare_date
                    AND sub.nextcapday < sub.compare_date
                )
            ")
            ->select('*');

        if ($lastitem && $lastitem->acntno) {
            $q->where('sub.acntno', '>=', $lastitem->acntno);
        }
        // Log::debug($query->toSql());
        return $q->orderBy('sub.acntno', 'asc')->get();
    }

    /**
     * Эх үүсвэрийн хуваарийн дагуу хүү капитализэшн хийж төлбөр шилжүүлэх ad800072
     */
    public function capIntDPInvNrs($sysdate, $lastitem, $instid)
    {
        $query = DB::table('dp_account as a')
            ->select(
                'a.acntno',
                'a.brchno',
                'a.prodcode',
                'a.curcode',
                'c.avgrate',
                'a.crint2cap',
                'a.cradjint',
                'a.crcapmethod',
                'a.crcapacntmod',
                'a.crcapacnt',
                DB::raw("
                    (SELECT MIN(PAYDAY)
                        FROM dp_inv_schd
                        WHERE instid = a.instid
                        AND acntno = a.acntno
                        AND statusid = 1 AND payday >= '$sysdate') as invpayday
                "),
                DB::raw("
                    fn_dp_nextcapday(
                        '$sysdate'::date,
                        a.termstartdate,
                        a.termexpdate,
                        p.crcapday,
                        p.crcapfreq,
                        a.cracapday
                    ) AS nextcapday
                ")
            )
            ->join('dp_account_type as p', function ($join) {
                $join->on('a.prodcode', '=', 'p.prodcode')
                    ->on('p.instid', '=', 'a.instid');
            })
            ->join('GP_inst_cur as c', function ($join) {
                $join->on('a.curcode', '=', 'c.curcode')
                    ->on('c.instid', '=', 'a.instid')
                    ->where('c.statusid', 1);
            })
            ->join('dp_inv_account as i', function ($join) {
                $join->on('a.acntno', '=', 'i.invacntno')
                    ->on('i.instid', '=', 'a.instid')
                    ->where('i.statusid', 1);
            })
            ->where('a.instid', $instid)
            ->where('a.statusid', '>', 1);

        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query) // bindings-г дамжуулна
            ->whereRaw("
                (CASE WHEN invpayday IS NULL THEN nextcapday ELSE invpayday END) = '$sysdate'::date
            ")
            ->select('*');

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('sub.acntno', '>=', $lastitem->acntno);
        }

        // Log::debug($query->toSql());
        $results = $query->orderBy('sub.acntno', 'ASC')->get();
        return $results;
    }


    /**
     * Депозит кредит хүү капитализэшн хийх ad800067-71
     * $runfreq, 'P'- өдөр бүр ажиллана хэрэв хугацааны эцэст гэсэн тохиргоо байвал хийнэ,
     *  'M'- сарын эцэст, 'Q'- улирлын эцэст, 'B'- хагас жилийн эцэст, 'Ү'- жилийн эцэст
     */
    public function capCrIntDP($sysdate, $lastitem, $instid, $freq)
    {

        $query = DB::table('dp_account AS a')
            ->join(DB::raw('(select ?::date as sysdate) as s'), DB::raw('1'), '=', DB::raw('1'))
            ->addBinding($sysdate, 'join')
            ->select(
                'a.acntno',
                'a.brchno',
                'a.prodcode',
                'a.curcode',
                DB::raw('a.crint2cap + a.cradjint AS capint'),
                'p.crcapfreq',
                'p.crcapday',
                'a.openeddate',
                'a.termstartdate',
                'a.termexpdate',
                'a.crcapmethod',
                'a.crcapacntmod',
                'a.crcapacnt',
                's.sysdate',
                DB::raw("
                fn_dp_nextcapday(
                    s.sysdate,
                    a.termstartdate,
                    a.termexpdate,
                    p.crcapday,
                    p.crcapfreq,
                    a.cracapday
                ) AS nextcapday
                ")
            )
            ->join('dp_account_type AS p', function ($join) use ($instid) {
                $join->on('a.prodcode', '=', 'p.prodcode')
                    ->where('p.instid', '=', $instid);
            })
            ->leftJoin('dp_inv_account AS i', function ($join) {
                $join->on('a.acntno', '=', 'i.invacntno')
                    ->on('i.instid', '=', 'a.instid')
                    ->where('i.statusid', 1);
            })
            ->where(function ($condition) use ($freq) {
                $condition->whereRaw('a.crint2cap + a.cradjint <> 0')
                    ->where('p.crcapfreq', $freq)
                    ->where('p.crcapday', 'E')
                    ->where('a.statusid', '>', 1);
            })
            ->where('a.instid', $instid)->whereNull('i.invacntno');

        $q = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query) // bindings-г дамжуулна
            ->whereRaw("sub.sysdate = sub.nextcapday")
            ->select('*');

        if ($lastitem && $lastitem->acntno) {
            $q = $q->where('sub.acntno', '>=', $lastitem->acntno);
        }
        // Log::debug($query->toSql());
        $results = $q->orderBy('sub.acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Хугацаа нь дуусаж байгаа хадгаламжийн бүтээгдэхүүн солих дансдын жагсаалт
     */
    public function termEndSavingsAcnts($sysdate, $lastitem, $instid)
    {
        $sql = "WITH
        A
        AS
           (SELECT A.*,
                   termnextedate AS termnewexpday,
                   CASE
                        WHEN A.prodcode = A.termnextprodcode
                        THEN
                            CASE COALESCE (A.termcurrentcycle, 0)
                                WHEN 0
                                    THEN
                                        CASE newP.procflag
                                        WHEN 'T' THEN 'TO_TERMDEP'
                                    ELSE 'TO_SAVING'
                                    END
                                WHEN 1
                                    THEN
                                        'LAST_ROLL'
                                    ELSE
                                        'ROLL'
                            END
                        ELSE
                            CASE newP.procflag
                                WHEN 'T' THEN 'TO_TERMDEP'
                                ELSE 'TO_SAVING'
                            END
                   END AS trantype,
                   A.drint2cap AS newdrint2cap,
                   A.dradjint AS newdradjint,
                   newP.crintratechg AS newcrintratechg,
                   newP.crintrateupd AS newcrintrateupd,
                   p.termnextprodcode AS tempprodcode,
                   CASE
                        WHEN A.prodcode = A.termnextprodcode
                            THEN
                            CASE COALESCE (A.termcurrentcycle, 0)
                                WHEN 0
                                THEN
                                    COALESCE (A.termnextprodcode, p.termnextprodcode)
                                ELSE
                                    A.prodcode
                            END
                        ELSE
                            COALESCE (A.termnextprodcode, p.termnextprodcode)
                   END AS newprodcode,
                   newP.procflag AS newprocflag,
                   CASE
                      WHEN    (newP.crintratechg = 1 AND A.crintrateacnt = 1)
                           OR newP.crintratechg = 2
                      THEN
                         CASE
                            WHEN    A.termnextcrintrate < newP.crintminrate
                                OR A.termnextcrintrate > newP.crintmaxrate
                            THEN
                                newP.intrate
                            ELSE
                                A.termnextcrintrate
                        END
                      ELSE
                         newP.intrate
                   END AS newcrintrate,
                   CASE
                      WHEN newP.crintratechg = 0
                      THEN
                         0
                      WHEN     newP.crintratechg = 1
                           AND COALESCE (A.termnextcrintrateacnt, A.crintrateacnt) =
                               0
                      THEN
                         0
                      WHEN     newP.crintratechg = 1
                           AND COALESCE (A.termnextcrintrateacnt, A.crintrateacnt) =
                               1
                      THEN
                         1
                      WHEN newP.crintratechg = 2
                      THEN
                         1
                   END AS newcrintrateacnt,
                   COALESCE (A.termnextlen, newP.termminlen) AS newtermlen,
                   COALESCE (A.termbasis, newP.termbasis) AS newtermbasis,
                   CASE
                      WHEN newP.crcapmethod = 3
                      THEN
                         COALESCE (A.termnextcrcapmethod, A.crcapmethod)
                      ELSE
                         newP.crcapmethod
                   END AS newcrcapmethod,
                   CASE
                      WHEN    (    newP.crcapmethod = 3
                               AND COALESCE (A.termnextcrcapmethod, A.crcapmethod) =
                                   2)
                           OR (newP.crcapmethod = 2)
                      THEN
                         COALESCE (A.termnextcrcapacntmod, A.crcapacntmod)
                      ELSE
                         ''
                   END AS newcrcapacntmod,
                   CASE
                      WHEN    (    newP.crcapmethod = 3
                               AND COALESCE (A.termnextcrcapmethod, A.crcapmethod) =
                                   2)
                           OR (newP.crcapmethod = 2)
                      THEN
                         COALESCE (A.termnextcrcapacnt, A.crcapacnt)
                      ELSE
                         ''
                   END AS newcrcapacnt,
                   CASE A.termnextlen
                      WHEN 0
                      THEN
                         CAST (A.termlen AS VARCHAR) || ' ' || A.termbasis
                      ELSE
                         CAST (A.termnextlen AS VARCHAR) || ' ' || P.termbasis
                   END AS RollStr,
                   A.termexpdate AS newtermstartdate,
                   A.termnextedate AS newtermexpdate,
                   newP.termminlen AS newtermminlen,
                   newP.termmaxlen AS newtermmaxlen,
                   newP.statusid AS newprodstatus,
                   newP.crintminrate AS newcrintminrate,
                   newP.crintmaxrate AS newcrintmaxrate,
                   newP.termnextprodcode AS newtermnextprodcodetmp,
                   CASE A.termnextlen
                      WHEN 0
                      THEN
                         CAST (A.termlen AS VARCHAR) || ' ' || A.termbasis
                      ELSE
                         CAST (A.termnextlen AS VARCHAR) || ' ' || P.termbasis
                   END AS ExtStr
              FROM dp_account A
                   INNER JOIN dp_account_type P
                      ON P.prodcode = A.prodcode AND P.instid = A.instid
                   INNER JOIN dp_account_type newP
                      ON     newP.instid = A.instid
                         AND newP.prodcode =
                             CASE
                                WHEN COALESCE (a.termcurrentcycle, 0) = 0
                                THEN
                                   COALESCE (A.termnextprodcode,
                                             P.termnextprodcode)
                                ELSE
                                   A.prodcode
                             END
             WHERE     P.procflag = 'T'
                   AND A.statusid >= 1
                   AND A.instid = :instid
                   AND A.termexpdate = :txndate)
     SELECT A.*,
            CASE A.trantype WHEN 'ROLL' THEN A.newprodcode ELSE nextP.prodcode END
               AS newtermnextprodcode,
            CASE A.trantype WHEN 'ROLL' THEN A.newcrintrate ELSE nextP.intrate END
               AS newtermnextcrintrate,
            CASE A.trantype
               WHEN 'ROLL'
               THEN
                  A.newcrintrateacnt
               ELSE
                  CASE
                     WHEN nextP.crintratechg = 0
                     THEN
                        0
                     WHEN     nextP.crintratechg = 1
                          AND COALESCE (A.termnextcrintrateacnt, A.crintrateacnt) =
                              0
                     THEN
                        0
                     WHEN     nextP.crintratechg = 1
                          AND COALESCE (A.termnextcrintrateacnt, A.crintrateacnt) =
                              1
                     THEN
                        1
                     WHEN nextP.crintratechg = 2
                     THEN
                        1
                  END
            END
               AS newtermnextcrintrateacnt,
            CASE A.trantype
               WHEN 'ROLL' THEN A.newtermlen
               ELSE nextP.termminlen
            END
               AS newtermnextlen,
            CASE A.trantype
               WHEN 'ROLL'
               THEN
                  A.newcrcapmethod
               ELSE
                  CASE
                     WHEN nextP.crcapmethod = 3
                     THEN
                        COALESCE (A.termnextcrcapmethod, A.crcapmethod)
                     ELSE
                        nextP.crcapmethod
                  END
            END
               AS newtermnextcrcapmethod,
            CASE A.trantype
               WHEN 'ROLL'
               THEN
                  A.newcrcapacntmod
               ELSE
                  CASE
                     WHEN    (    nextP.crcapmethod = 3
                              AND COALESCE (A.termnextcrcapmethod, A.crcapmethod) =
                                  2)
                          OR (nextP.crcapmethod = 2)
                     THEN
                        COALESCE (A.termnextcrcapacntmod, A.crcapacntmod)
                     ELSE
                        ''
                  END
            END
               AS newtermnextcarcapacntmod,
            CASE A.trantype
               WHEN 'ROLL'
               THEN
                  A.newcrcapacnt
               ELSE
                  CASE
                     WHEN    (    nextP.crcapmethod = 3
                              AND COALESCE (A.termnextcrcapmethod, A.crcapmethod) =
                                  2)
                          OR (nextP.crcapmethod = 2)
                     THEN
                        COALESCE (A.termnextcrcapacnt, A.crcapacnt)
                     ELSE
                        ''
                  END
            END
               AS newtermnextcrcapacnt,
            A.newtermexpdate
               AS newtermnextstartdate,
           CASE
            WHEN nextP.procflag='T' THEN
             CASE
                 WHEN A.newtermlen is Null THEN
                     CASE
                         WHEN A.newtermbasis = 'D' THEN A.newtermexpdate + COALESCE(nextP.termminlen,0)
                         WHEN A.newtermbasis = 'M' THEN A.newtermexpdate + INTERVAL '1 MONTH' * COALESCE(nextP.termminlen,0)
                         WHEN A.newtermbasis = 'Y' THEN A.newtermexpdate + INTERVAL '1 YEAR' * COALESCE(nextP.termminlen,0)
                         ELSE A.newtermexpdate
                     END
                 ELSE
                     CASE
                        WHEN A.trantype = 'ROLL' THEN
                            CASE
                                WHEN nextP.termbasis = 'D' THEN A.newtermexpdate + COALESCE(A.newtermlen,0)
                                WHEN nextP.termbasis = 'M' THEN A.newtermexpdate + INTERVAL '1 MONTH' * COALESCE(A.newtermlen,0)
                                WHEN nextP.termbasis = 'Y' THEN A.newtermexpdate + INTERVAL '1 YEAR' * COALESCE(A.newtermlen,0)
                                ELSE A.newtermexpdate
                            END
                        ELSE
                            CASE
                                WHEN nextP.termbasis = 'D' THEN A.newtermexpdate + COALESCE(nextP.termminlen,0)
                                WHEN nextP.termbasis = 'M' THEN A.newtermexpdate + INTERVAL '1 MONTH' * COALESCE(nextP.termminlen,0)
                                WHEN nextP.termbasis = 'Y' THEN A.newtermexpdate + INTERVAL '1 YEAR' * COALESCE(nextP.termminlen,0)
                                ELSE A.newtermexpdate
                            END
                     END
             END
            ELSE NULL
           END AS newnexttermexpdate,

            tempProd.prodcode
               AS newtempprodcode,
            tempProd.crintminrate
               AS newtempcrintminrate,
            tempProd.crintmaxrate
               AS newtempcrintmaxrate,
            nextP.termbasis
               AS newtermnextbasis,
            A.termnextsdate
               AS newtermnextsdate,
            A.termnextedate
               AS newtermnextedate,
            tempProd.procflag
               AS tempprodprocflag
       FROM A
            LEFT JOIN dp_account_type nextP
               ON     nextP.prodcode = A.newtermnextprodcodetmp AND nextP.instid = A.instid
            LEFT JOIN dp_account_type tempProd
               ON tempProd.curcode = A.curcode AND tempProd.tempprod = 1 AND tempProd.instid = A.instid
        ";

        if ($lastitem) {
            $sql = $sql . " and acntno >= '" . $lastitem->acntno . "'";
        }
        $sql = $sql . " order by acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'txndate' => $sysdate,
        ]);

        return $results;
    }

    /**
     * Нэг дансны хувьд хугацааг сунгах
     */

    public function extendTermDp($data, $sysdate, $instid, $userid)
    {
        //Хугацааг сунгаж дансны өөрчлөлт хийх
        $this->updateExtendTermDp($sysdate, $data->acntno, $instid);
        //Дансны түвшин дэхь хүүний бичилтийг устгах
        $this->deleteIntRate($data->acntno, $instid);
        //Бүтээгдэхүүнээс хүүний шатлал авчрах
        $this->insertIntRate($data->acntno, $data->newprodcode, $instid, $userid);

        //Дараагийн шилжих бүтээгдэхүүнд авах хүүний хувийг шалгаж тодорхойлох]
        // БҮТЭЭГДЭХҮҮН
        //Хүүний хувь тодорхойлох түвшин
        //0 - зөвхөн бүтээгдэхүүний түвшинд тодорхойлогдоно
        //1 - бүтээгдэхүүний ба дансны аль ч түвшинд тодорхойлогдож болно
        //2 - зөвхөн дансны түвшинд тодорхойлогдоно

        // ДАНС
        //Кредит хүүг бүтээгдэхүүнээс авах, эсвэл дансны түвшинд тодорхойлох эсэх
        //1 - дансны түвшинд тодорхойлогдоно
        //0 - бүтээгдэхүүний түвшинд тодорхойлогдоно
        $prodintratechg = (+$data->newcrintratechg);
        $bytcrintrateacnt = (+$data->newcrintrateacnt);
        $intRateFromProd = false;
        switch ($prodintratechg) {
            case 0: //зөвхөн бүтээгдэхүүний түвшинд
                $intRateFromProd = true;
                break;
            case 1: //бүтээгдэхүүний ба дансны аль ч түвшинд
                if ($bytcrintrateacnt == 0) {
                    $intRateFromProd = true;
                }

                break;
            case 2: //зөвхөн дансны түвшинд
                if ($bytcrintrateacnt == 1) {
                    $intRateFromProd = true;
                }

                break;
        }
        if ($intRateFromProd) {
            //Авчирсан шатлалтай хүүний дагуу дансны хүүг өөрчлөх
            $this->updateAcntIntRate($data->acntno, $instid);
        }
    }

    /**
     * Хугацааг сунгаж дансны өөрчлөлт хийх
     */
    public function  updateExtendTermDp($sysdate, $acntno, $instid)
    {
        $query = "UPDATE dp_account a
        SET
                termstartdate = t.termexpdate,
                termexpdate = t.termnextedate,
                termlen =
                        CASE
                            WHEN t.termbasis = 'D' AND t.termnextedate IS NOT NULL THEN t.termnextedate - t.termexpdate
                            ELSE
                                CASE
                                    WHEN t.termcurrentcycle-1 > 0 THEN t.termlen
                                    ELSE
                                        CASE
                                            WHEN t.termnextlen = 0 THEN t.termlen
                                            ELSE COALESCE(t.termnextlen, t.termlen)
                                        END
                                END
                        END,
                crcapmethod = t.termnextcrcapmethod,
                crcapacnt = t.termnextcrcapacnt,
                crcapacntmod = t.termnextcrcapacntmod,
                crintrateacnt = t.newcrintrateacnt,
                crintrate = t.newcrintrate,
                termnextlen = newtermlen,
                termnextedate = t.nexttermexpdate,
                termnextsdate = t.newtermedate,
                termnextprodcode =
                            CASE
                                WHEN t.termcurrentcycle - 1 > 0 THEN t.termnextprodcode
                                ELSE t.newprodcode
                            END,

                            termcurrentcycle =
                            CASE
                                WHEN t.termcurrentcycle > 0 THEN t.termcurrentcycle - 1
                                ELSE 0
                            END,
                termnextcrintrateacnt = t.p2crintrateacnt,
                termnextcrintrate = t.p2crintrate,
                crcaptotal2 = 0,
                lasttellertxndate = :txndate
        FROM (
        SELECT
        a.termstartdate, a.termexpdate, a.termlen,
         a.crcapmethod, a.crcapacntmod, a.crcapacnt,
         a.crintrateacnt, a.crintrate, a.termbasis,
         a.termnextprodcode, a.termcurrentcycle,
         a.termnextsdate, a.termnextedate, a.termnextlen,
         a.termnextcrintrateacnt, a.termnextcrintrate,
         a.termnextcrcapmethod, a.termnextcrcapacntmod, a.termnextcrcapacnt,
         p.intrate, p.termnextprodcode newprodcode,
         p.useratetier newuseratetier, a.acntno, a.instid,
         case
            when p1.crintratechg = 1 and a.crintrateacnt = 1 then
                case
                    when a.termnextcrintrate < p1.crintminrate or a.termnextcrintrate > p1.crintmaxrate then p1.intrate
                    else a.termnextcrintrate
                end
            when p1.crintratechg = 2 then
                case
                    when a.termnextcrintrate < p1.crintminrate or a.termnextcrintrate > p1.crintmaxrate
                    then p1.intrate
                    else a.termnextcrintrate
                end
            else p1.intrate
        end as newcrintrate,

        case
            when p.crintratechg = 0 then 0
            when p.crintratechg = 1 and a.termnextcrintrateacnt = 0 then 0
            when p.crintratechg = 1 and a.termnextcrintrateacnt = 1 then 1
            when p.crintratechg = 2 then 1
            else null
        end as newcrintrateacnt,
         case
            when p1.procflag = 'T' then
                case
                    -- check if a.termnextlen is null, else choose a.termbasis or p.termbasis
                    case when a.termnextlen is null then a.termbasis else p.termbasis end
                    when 'D' then
                        a.termnextedate +
                        case
                            when a.termnextlen = 0 then a.termlen
                            else coalesce(a.termnextlen, a.termlen)
                        end
                    when 'M' then
                        a.termnextedate + interval '1 month' *
                        case
                            when a.termnextlen = 0 then a.termlen
                            else coalesce(a.termnextlen, a.termlen)
                        end
                    when 'Y' then
                        a.termnextedate + interval '1 year' *
                        case
                            when a.termnextlen = 0 then a.termlen
                            else coalesce(a.termnextlen, a.termlen)
                        end * 12
                end
            else null
        end as nexttermexpdate,
        case
            when p1.procflag = 'T' then a.termnextedate
            else null
        end as newtermedate,

        case
            when p1.procflag = 'T' then p.termbasis
            else null
        end as newtermbasis,

        case
            when p1.procflag = 'T' then a.termnextlen
            else null
        end as newtermlen,

        case
            when a.termcurrentcycle > 0 then p1.crintratechg
            when p2.crintratechg = 0 then 0
            else 1
        end as p2crintrateacnt,

        case when a.termnextedate IS NOT NULL then
            case
                when p1.crintratechg = 2 and p1.crintrateupd = 0 then
                    case
                        when a.termnextcrintrate < p1.crintminrate or a.termnextcrintrate > p1.crintmaxrate then p1.intrate
                        else a.termnextcrintrate
                    end
                when p1.crintratechg = 1 and a.crintrateacnt = 1 then
                    case
                        when a.termnextcrintrate < p1.crintminrate or a.termnextcrintrate > p1.crintmaxrate then p1.intrate
                        else a.termnextcrintrate
                    end
                when p1.crintratechg = 2 and p1.crintrateupd = 1 then p1.intrate
                when p1.crintratechg = 0 and (a.termcurrentcycle - 1) > 0 then p1.intrate
                else p2.intrate
            end
        else
			p1.intrate
		end as p2crintrate,
         a.crcaptotal2, a.lasttellertxndate
    FROM
        dp_account a
    INNER JOIN
        dp_account_type p ON a.prodcode = p.prodcode AND a.instid = p.instid
    LEFT JOIN
        dp_account_type p1 ON a.termnextprodcode = p1.prodcode AND a.instid = p1.instid
    LEFT JOIN
        dp_account_type p2 ON p1.termnextprodcode = p2.prodcode AND a.instid = p2.instid
    WHERE
        a.acntno = :acntno
    AND
        p1.statusid = 1
    AND
        a.instid = :instid
    ) AS t
    WHERE a.acntno = t.acntno and a.instid = t.instid";
        $results = DB::statement($query, [
            'instid' => $instid,
            'acntno' => $acntno,
            'txndate' => $sysdate,
        ]);

        return $results;
    }

    /**
     * 3. Дансны түвшин дэхь хүүний бичилтийг устгах
     */
    public function deleteIntRate($acntno, $instid)
    {
        DpAccountIntRate::where('acntno', $acntno)
            ->where('instid', $instid)->delete();
    }

    /**
     * 4. Бүтээгдэхүүнээс хүүний шатлал авчрах
     */
    public function insertIntRate($acntno, $prodcode, $instid, $userid)
    {
        $query = "INSERT INTO dp_account_int_rate(acntno, intervalno, minamount, maxamount, intrate, statusid, instid, created_by, updated_by)
                    SELECT '" . $acntno . "', intervalno, minamount, maxamount, intrate, 1, $instid,  $userid, $userid
                        FROM dp_account_type_int_rate
                            WHERE prodcode = '" . $prodcode . "' AND instid = $instid";
        $results = DB::statement($query);
        return $results;
    }

    /**
     * 5. Авчирсан шатлалтай хүүний дагуу дансны хүүг өөрчлөх
     */
    public function updateAcntIntRate($acntno, $instid)
    {
        $query = "UPDATE dp_account d
                 SET  crintrate = CASE t.useratetier WHEN 0 THEN t.intrate WHEN 1 THEN
                            COALESCE((SELECT MIN(r.intrate) FROM dp_account_type_int_rate r
                                        WHERE r.prodcode = t.prodcode AND r.instid = t.instid
                                        AND t.currentbal BETWEEN r.minamount AND r.maxamount-0.001
                            ), t.intrate)
                                    END,
                    useratetier = 1
                 FROM (
                 SELECT
                 a.crintrate, a.useratetier acntuseratetier, p.useratetier,
                      p.intrate, p.craccruelfreq, a.totalbalperiod, a.prodcode, a.currentbal, a.instid
                      FROM dp_account a
                      INNER JOIN dp_account_type p ON a.prodcode = p.prodcode AND a.instid = p.instid
                      WHERE a.acntno = '" . $acntno . "' AND p.useratetier = 1 AND a.currentbal > 0 AND a.instid = $instid
                 ) AS t
                 WHERE d.acntno = '" . $acntno . "' AND d.instid = $instid";
        $results = DB::statement($query);
        return $results;
    }

    /**
     * Битүүмжээс дансдыг чөлөөлөх (Хугацаа нь дуусаж байгаа)
     */
    public function unHoldDpAcnts($sysdate, $lastitem, $instid)
    {

        // $query = DpHoldTxn::select('acntno', 'jrno', 'txndesc', 'txndate', 'inittype', 'brchno')
        //     ->where('statusid', 1)
        //     ->whereIn('inittype', [1, 4])
        //     ->where('expiredate', '<=', $sysdate)
        //     ->where('useexpirehour', 0)
        //     ->where('instid', $instid);

        $sql = "SELECT * from (SELECT
                    dh.acntno, dh.jrno, dh.txndesc, dh.txndate, dh.inittype, dh.brchno
                from dp_hold_txn dh
                    left join ln_account la ON la.acntno = dh.morloanacntno and la.instid = dh.instid
                    left join ln_account_type lt ON lt.prodcode = la.prodcode and lt.instid = la.instid
                where dh.statusid = 1 and dh.inittype in (1, 4)
                    and (dh.expiredate <= :txndate or (la.enddate = :txndate and lt.depmorloan = 1))
                    and dh.useexpirehour = 0 and dh.instid = :instid) a";

        if ($lastitem) {
            $sql = $sql . " where acntno >= '" . $lastitem->acntno . "'";
        }
        $sql = $sql . " order by acntno ASC ";
        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'txndate' => $sysdate,
        ]);

        // if ($lastitem && $lastitem->acntno) {
        //     $query = $query->where('acntno', '>=', $lastitem->acntno);
        // }
        // $results = $query->orderBy('acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Түр идэвхгүй төлөвт оруулах дансны мэдээлэл
     */
    public function DormantAcnts($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');

        $query = DB::table(function ($subquery) use ($csysdate, $instid) {
            $subquery->select('a.brchno', 'a.acntno', 'a.prodcode', 'a.statusid as oldstatuscode')
                ->selectRaw("
                    CASE
                        WHEN (A.lasttellertxndate < A.termstartdate)
                        THEN
                            CASE
                            WHEN (DATE '$csysdate' - COALESCE(A.termstartdate, A.lasttellertxndate)) >= P.dormance
                            THEN
                                5
                            ELSE
                                A.statusid
                            END
                        ELSE
                            CASE
                            WHEN (DATE '$csysdate' - COALESCE(A.lasttellertxndate, A.openeddate)) >= P.dormance
                            THEN
                                5
                            ELSE
                                A.statusid
                            END
                    END AS status
                ")
                ->from('dp_account AS a')
                ->join('dp_account_type AS p', function ($join) {
                    $join->on('a.prodcode', '=', 'p.prodcode')
                        ->where('a.instid', '=', DB::raw('p.instid'));
                })
                ->whereIn('a.statusid', [1, 3, 4])
                ->where('p.dormance', '>', 0)
                ->where('a.instid', $instid);
        }, 't')
            ->where('status', '=', 5);

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('t.acntno', '>=', $lastitem->acntno);
        }
        $results = $query->orderBy('t.acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Кредит хүү тооцоолох - сараар, сарын эцэст дансууд
     */
    public function CalcDPCrEOMIntAcnts($sysdate, $lastitem, $instid)
    {
        $firstOfMonth = Carbon::parse($sysdate)->startOfMonth()->format('Y-m-d');
        $day = Carbon::parse($sysdate)->day;
        $digitCount = CoreService::getInstGp($instid, 'DigitCount');

        $query = DB::table('dp_account')
            ->join('dp_account_type', function ($join) {
                $join->on('dp_account.prodcode', '=', 'dp_account_type.prodcode')
                    ->whereColumn('dp_account.instid', 'dp_account_type.instid');
            })
            ->select('dp_account.crint2acr',  'dp_account.totalbalperiod', 'dp_account.crroundint', 'dp_account.acntno')
            ->selectRaw("
                ROUND(
                    CASE dp_account_type.crintmethod
                      WHEN 1 THEN (dp_account.totalbalperiod * dp_account.crintrate / dp_account_type.crintyeardays / 100) --  Бодит үлдэгдлээс хүү тооцох
                      WHEN 2 THEN (dp_account.totalbalperiod / 30) * dp_account.crintrate *
                                   CASE
                                     WHEN dp_account_type.crintdayoption = 1 THEN 30
                                     ELSE " . $day . "
                                   END /
                                   dp_account_type.crintyeardays / 100  --  Дундаж үлдэгдлээс хүү тооцох
                      WHEN 3 THEN dp_account.minbalance * dp_account.crintrate *
                                   CASE
                                     WHEN dp_account_type.crintdayoption = 1 THEN 30
                                     ELSE " . $day . "
                                   END /
                                   dp_account_type.crintyeardays / 100                 --  Сарын доод үлдэгдлээс хүү тооцох
                      WHEN 4 THEN dp_account.currentbal * dp_account.crintrate *
                                   CASE
                                     WHEN dp_account_type.crintdayoption = 1 THEN 30
                                     ELSE " . $day . "
                                   END /
                                   dp_account_type.crintyeardays / 100                 --  Сарын доод үлдэгдлээс хүү тооцох
                    END,
                    " . $digitCount . "
                  ) as newcrintacr")
            ->where('dp_account.statusid', '>', 2)
            ->where('dp_account.currentbal', '>', 0)
            ->where('dp_account_type.craccruelfreq', '=', 'M')
            ->where('dp_account.instid', $instid)
            ->where('dp_account.openeddate', '<=', $firstOfMonth);


        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('dp_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $query->orderBy('dp_account.acntno', 'ASC')->get();
        return $results;
    }

    public function doCapCrInt($step, $lastitem, $ACTION_CODE, $runfreq)
    {

        $service = new DpEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => $ACTION_CODE,
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $service->capCrIntDP($txndate, $lastitem, $instid, $runfreq);
        // Log::debug("ad800067-71");
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new DpTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                switch ($runfreq) {
                    case 'P':
                        $txndesc = "Кредит хүү / үр шим кап хийв. (EOP)";
                        break;
                    case 'M':
                        $txndesc = "Кредит хүү / үр шим кап хийв. (EOM)";
                        break;
                    case 'Q':
                        $txndesc = "Кредит хүү / үр шим кап хийв. (EOQ)";
                        break;
                    case 'B':
                        $txndesc = "Кредит хүү / үр шим кап хийв. (EOB)";
                        break;
                    case 'Y':
                        $txndesc = "Кредит хүү / үр шим кап хийв. (EOY)";
                        break;

                    default:
                        # code...
                        break;
                }
                $p = new TxnJrnlEntity();
                $p->setTxnAcntCode($data->acntno);
                $p->setCurCode($data->curcode);
                $p->setTxnDesc($txndesc);
                $p->setSourcecode(1);
                $p->setTxnAmount(0);
                // $p->setRate(1);
                $p->setInstid(CoreService::getCurInstId());
                $p->setPostdate(getNow());
                $p->setUserid(CoreService::getCurUserId());
                $p->setTxndate($txndate);
                $p->setTxncode('dp901051');
                $service->doCapInt($p);
                $step->succount = $step->succount + 1;
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                }
            } catch (Exception $ex) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $ex->getMessage();
                throw $ex;
            } catch (TypeError $ex) {
                $eodlogs['errtype'] = 'F';
                $eodlogs['errdesc'] = $ex->getMessage();
                throw $ex;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
    }
    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800119
     */
    public function DpAcntHistDel($sysdate, $lastitem, $instid)
    {
        DpAccountHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад зээлийн дансны мэдээлэл авах ad800119
     */
    public function DpAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = DpAccount::select(
            'acntno',
            DB::raw("'$sysdate' as txndate"),
            'brchno',
            'custno',
            'segcode',
            'prodcode',
            'curcode',
            'name',
            'name2',
            'catcode',
            'currentbal',
            DB::raw('COALESCE (openeddate, created_at) AS openeddate'),
            'closeddate',
            'totalbalperiod',
            'totaldayperiod',
            'crcapmethod',
            'crcapacnt',
            'crcapacntmod',
            'crintrateacnt',
            'crintrate',
            'crdailyint',
            'crint2acr',
            'crint2cap',
            'cradjint',
            'crcaptotal',
            'odbasicallow',
            'odorderno',
            'odstartdate',
            'odenddate',
            'odbasicintrate',
            'odintrateacnt',
            'odbasiclimit',
            'odexclimit',
            'drdailyint',
            'drint2acr',
            'drint2cap',
            'dradjint',
            'drcaptotal',
            'drcapmethod',
            'drcapacnt',
            'termbasis',
            'termlen',
            'termstartdate',
            'termexpdate',
            'termnextprodcode',
            'termnextlen',
            'termnextcrcapmethod',
            'termnextcrcapacnt',
            'termnextcrcapacntmod',
            'termnextcrintrateacnt',
            'termnextcrintrate',
            'termtoldays',
            'fintxncount',
            'lasttxndate',
            'balchanged',
            'prevstatus',
            'crroundint',
            'drroundint',
            'termnextsdate',
            'termnextedate',
            'useratetier',
            'hide',
            'termcyclecount',
            'termcurrentcycle',
            'lasttellertxndate',
            'prevbal',
            'certno',
            'inffreq',
            'avgbalance',
            'bonus',
            'gotinsurance',
            'monthlyacumbal',
            'avgballastm3',
            'avgballastm2',
            'avgballastm',
            'txndef',
            'invbalance',
            'odcomintrate',
            'odcomintrateacnt',
            'odexceedintrate',
            'odstatus',
            'drcasbalance',
            'drcasdailyint',
            'drcasint2acr',
            'drcasint2cap',
            'drcasintadj',
            'drcasroundint',
            'drcomdailyint',
            'drcom2acr',
            'drcom2cap',
            'drcomadjint',
            'drcomroundint',
            'drfinedailyint',
            'drfine2acr',
            'drfine2cap',
            'drfineadjint',
            'drfineroundint',
            'odclscode',
            'oddueamount',
            'odduedate',
            'odcasdate',
            'drnextpayday',
            'oddrintstop',
            'tellerfunc',
            'odcasintoption',
            'ctacntno',
            'ctcomacntno',
            'ctfineacntno',
            'intstoptype',
            'intstopclass',
            'odlinectacntno',
            'previntstoptype',
            'intstopteller',
            'ctacruel',
            'ctcomacruel',
            'ctfineacruel',
            'autocls',
            'nrsfromfile',
            'advicetriedcount',
            'prevdrcasbalance',
            'prevoddueamount',
            'cracapday',
            'taxamount',
            'taxamountround',
            'minbalance',
            'maxbalance',
            'signaturecount',
            'pausedtermextend',
            'termexpireatentf',
            'codenddatentf',
            'crcaptotal2',
            'coveracntno',
            'odclscodeqlt',
            'odclscodetrm',
            'odbintpay',
            'odcintpay',
            'odpintpay',
            'drcapfreq',
            'drcapday',
            'odpintlimit',
            'odenddatentf',
            'termexpdatentf',
            'purpcode',
            'subpurpcode',
            'sourcecode',
            'statusid',
            'instid',
            'tmp_bal',
            'tmp_drint2cap',
            'tmp_crint2cap',
            'tmp_statuscode',
            'tmp_brchno',
            'tmp_prodcode',
            'tmp_crintrate',
            'tmp_termstartdate',
            'tmp_termexpdate',
            'tmp_drcasint2cap',
            'tmp_drcom2cap',
            'tmp_drfine2cap',
            'tmp_odclscode',
            'tmp_drint2acr',
            'tmp_drcasint2acr',
            'tmp_drcom2acr',
            'tmp_drfine2acr',
            'tmp_drcasbalance',
            'tmp_custtotalbal',
            'tmp_taxamount',
            'tmp_crcaptotal2',
            'tmp_odclscodetrm',
            'tmp_odclscodeqlt',
            'created_by',
            DB::raw("'$caldate' AS created_at")
        )
            ->where('instid', $instid)
            ->where(function ($q) use ($sysdate) {
                $q->where('statusid', '!=', 0)
                    ->orWhere(function ($q2) use ($sysdate) {
                        $q2->where('statusid', 0)
                            ->where('closeddate', $sysdate);
                    });
            })
            ->get();
        return $results;
    }
}
