<?php

namespace Modules\Ad\Http\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEodLog;
use Modules\Gp\Entities\GPInstEodSteps;
use Modules\Gp\Entities\GPInstSeq;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\TrCurRateHist;

class TrEodService
{

    /**
     * Дундаж ханшийн түүх цвэрлэх
     */
    public function AvgRateDel($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate);

        TrCurRateHist::where('instid', $instid)
            ->where('date', $sysdate)->delete();
    }
    /**
     * Дундаж ханш
     */
    public function AvgRateSelect($sysdate, $lastitem, $instid)
    {

        $query = DB::table('GP_inst_cur AS a')
            ->select(
                DB::raw("'" . $sysdate . "' AS date"),
                'a.curcode',
                'a.avgrate',
                'a.avgrateend'
            )
            ->where('a.statusid', 1)
            ->where('a.instid', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('a.curcode', '>=', $lastitem->curcode);
        }
        $results = $query->orderBy('a.curcode', 'ASC')->get(['date', 'curcode', 'avgrate', 'avgrateend']);
        return $results;
    }

    /**
     * Өдрийн ханш түүх дээр
     */
    public function DailyRateSelectHist($sysdate, $lastitem, $instid)
    {

        $query = DB::table('GP_inst_cur_rate_hist AS a')
            ->select(
                'a.rtypecode',
                'a.curcode',
                'a.salerate',
                'a.buyrate'
            )
            ->where('a.instid', $instid)
            ->where('a.date', $sysdate);

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('a.curcode', '>=', $lastitem->curcode);
        }
        $results = $query->orderBy('a.curcode', 'ASC')->get();
        return $results;
    }
    /**
     * Өдрийн ханш
     */
    public function DailyRateSelect($sysdate, $lastitem, $instid)
    {

        $query = DB::table('GP_inst_cur_rate AS a')
            ->select(
                'a.rtypecode',
                'a.curcode',
                'a.salerate',
                'a.buyrate',
                DB::raw("'" . $sysdate . "' AS date"),
            )
            ->where('a.statusid', 1)
            ->where('a.instid', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where('a.curcode', '>=', $lastitem->curcode);
        }
        $results = $query->orderBy('a.curcode', 'ASC')->get();
        return $results;
    }

    /**
     * Шинээр салбар эсвэл валют нэмэгдвэл түүний шинэ бичлэгийг Позицийн баазад нэмэх
     */
    public function NewBrCurPosSelect($sysdate, $lastitem, $instid)
    {

        $query = "SELECT U.brchno, C.curcode
                    FROM GP_inst_branch U, GP_inst_cur C
                    WHERE U.instid = :instid AND C.instid = :instid AND C.statusid = 1
                    AND NOT EXISTS (
                        SELECT 1
                        FROM ia_position B
                        WHERE B.brchno = U.brchno AND B.curcode = C.curcode AND B.instid = :instid
                    )
        ";

        $results = DB::select(DB::raw($query), [
            'instid' => $instid
        ]);

        return $results;
    }

    /**
     * Шинээр салбар эсвэл валют нэмэгдвэл түүний шинэ бичлэгийг Позицийн баазад нэмэх
     */
    public function LastPosSelect($sysdate, $lastitem, $instid)
    {

        $query = "
            SELECT T.acntbrchno AS brchno, T.curcode, SUM(CASE T.sign WHEN '+' THEN T.txnamount ELSE -1*T.txnamount END) AS currentposition
            FROM tr_journal T
            INNER JOIN GP_inst_susp S ON T.instid = S.instid AND (T.gl = S.acntno AND acntcode = 'CC' AND COALESCE(S.curcode,T.curcode) = T.curcode)
            INNER JOIN GP_inst_cur C ON T.instid = c.instid AND (C.curcode = T.curcode) AND C.statusid = 1
            WHERE T.instid = :instid AND T.corr = 0 AND T.curcode <> 'MNT'
            GROUP BY T.acntbrchno, T.curcode
    ";
        $results = DB::select(DB::raw($query), [
            'instid' => $instid
        ]);

        return $results;
    }


    /**
     * ЕД дансны үлдэгдэл тулгалтын мэдээ
     */
    public function PostGLBal($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate)->format('Y-m-d');
        $query = "SELECT b.gl,
                    coalesce(b.segcode, '00') AS segcode,
                    b.brchno,
                    b.curcode,
                    c.gl AS glcurcode,
                    b.gl || coalesce(b.segcode, '00') AS glsegcode,
                    coalesce(SUM (b.balance), 0) AS retailbal,
                    :txndate AS date,
                    b.instid
            FROM (  /* Дотоодын дансны үлдэгдэл */
                    SELECT t.gl AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.tmp_currentbal) * (-1) AS balance,
                            a.instid
                        FROM ia_account a
                            INNER JOIN ia_account_type t
                                ON a.typecode = t.typecode AND a.instid = t.instid
                        WHERE a.instid = :instid
                    GROUP BY t.gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Депозит дансны кредит үлдэгдэл*/
                    SELECT p.gl1 AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.tmp_bal) * (-1) AS balance,
                            a.instid
                        FROM dp_account a
                            INNER JOIN dp_account_type p
                                ON a.tmp_prodcode = p.prodcode AND a.instid = p.instid
                        WHERE a.tmp_bal > 0 AND a.instid = :instid
                    GROUP BY p.gl1,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Хуримтлагдсан кредит хүүний үлдэгдэл */
                    SELECT q.acntno2 AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.tmp_crint2cap) * (-1) AS balance,
                            a.instid
                        FROM dp_account a
                            INNER JOIN GP_inst_qual q
                                ON     a.tmp_prodcode = q.prodcode
                                    AND q.txncode = 'dp901041'
                                    AND a.instid = q.instid
                                    AND q.statusid = 1
                        WHERE a.instid = :instid
                    GROUP BY q.acntno2,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Зээлийн үлдэгдэл */
                    SELECT AC.gl AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_princbal, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type_cls AC
                                ON     a.tmp_prodcode = AC.prodcode
                                    AND a.tmp_clscode = AC.clscode
                                    AND a.instid = AC.instid
                        WHERE a.tmp_statuscode > 0 AND a.tmp_statuscode < 5 AND a.instid = :instid
                    GROUP BY AC.gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Үндсэн хүүний үлдэгдэл */
                    SELECT AC.glbint AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_capbint, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type_cls AC
                                ON     a.tmp_prodcode = AC.prodcode
                                    AND a.tmp_clscode = AC.clscode
                                    AND a.instid = AC.instid
                        WHERE a.tmp_statuscode > 0 AND a.tmp_statuscode < 5 AND a.instid = :instid
                    GROUP BY AC.glbint,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Ашиглаагүй дүнд тооцох шимтгэлийн үлдэгдэл */
                    SELECT AC.glcint AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_capcint, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type_cls AC
                                ON     a.tmp_prodcode = AC.prodcode
                                    AND a.tmp_clscode = AC.clscode
                                    AND a.instid = AC.instid
                        WHERE a.tmp_statuscode > 0 AND a.tmp_statuscode < 5 AND a.instid = :instid
                    GROUP BY AC.glcint,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Нэмэгдүүлсэн хүүний үлдэгдэл */
                    SELECT AC.glfint AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_capfint, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type_cls AC
                                ON     a.tmp_prodcode = AC.prodcode
                                    AND a.tmp_clscode = AC.clscode
                                    AND a.instid = AC.instid
                        WHERE a.tmp_statuscode > 0 AND a.tmp_statuscode < 5 AND a.instid = :instid
                    GROUP BY AC.glfint,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Хуримтлагдсан үндсэн хүүний үлдэгдэл */
                    SELECT q.acntno1 AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_acrbint, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN GP_inst_qual q
                                ON a.tmp_prodcode = q.prodcode AND a.instid = q.instid AND q.statusid = 1
                            LEFT JOIN ln_account_type p
                                ON a.prodcode = p.prodcode AND a.instid = p.instid
                        WHERE     a.tmp_statuscode > 0
                            AND a.tmp_statuscode < 5
                            AND q.txncode = 'ln902041'
                            AND a.tmp_clscode = COALESCE (q.clscode, a.tmp_clscode)
                            AND a.instid = :instid
                    GROUP BY q.acntno1,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Хуримтлагдсан ашиглаагүй дүнд тооцох шимтгэлийн үлдэгдэл */
                    SELECT q.acntno1 AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_acrcint, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN GP_inst_qual q
                                ON a.tmp_prodcode = q.prodcode AND a.instid = q.instid
                                AND q.statusid = 1
                            LEFT JOIN ln_account_type p
                                ON a.prodcode = p.prodcode AND a.instid = p.instid
                        WHERE     a.tmp_statuscode > 0
                            AND a.tmp_statuscode < 5
                            AND q.txncode = 'ln902042'
                            AND a.tmp_clscode = COALESCE (q.clscode, a.tmp_clscode)
                            AND a.instid = :instid
                    GROUP BY q.acntno1,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Хуримтлагдсан нэмэгдүүлсэн хүүний үлдэгдэл */
                    SELECT q.acntno1 AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (COALESCE (a.tmp_acrfint, 0)) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN GP_inst_qual q
                                ON a.tmp_prodcode = q.prodcode AND a.instid = q.instid
                                AND q.statusid = 1
                            LEFT JOIN ln_account_type p
                                ON a.prodcode = p.prodcode AND a.instid = p.instid
                        WHERE     a.tmp_statuscode > 0
                            AND a.tmp_statuscode < 5
                            AND q.txncode = 'ln902043'
                            AND a.tmp_clscode = COALESCE (q.clscode, a.tmp_clscode)
                            AND a.instid = :instid
                    GROUP BY q.acntno1,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Балансын гадуурх дансны үлдэгдэл */
                    SELECT t.gl AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.tmp_currentbal)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ia_ct_account a
                            INNER JOIN ia_ct_account_type t
                                ON a.typecode = t.typecode AND a.instid = t.instid
                        WHERE a.instid = :instid
                    GROUP BY t.gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Балансын гадуурх дансны ширхэг. */
                    SELECT t.gl AS gl,
                            COALESCE (
                                CASE WHEN p.itemvalue = '1' THEN 'MNT' ELSE a.curcode END,
                                a.curcode) AS curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.tmp_currentbal)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ia_ct_account a
                            INNER JOIN ia_ct_account_type t
                                ON a.typecode = t.typecode AND a.instid = t.instid
                            LEFT JOIN GP_inst_gp p
                                ON     UPPER (p.itemname) = 'CMACNTBALBASECUR'
                                    AND a.instid = p.instid
                        WHERE     a.tmp_currentcount <> 0
                            AND t.balancetype IN (1, 2)
                            AND a.instid = :instid
                    GROUP BY t.gl,
                            COALESCE (
                                CASE WHEN p.itemvalue = '1' THEN 'MNT' ELSE a.curcode END,
                                a.curcode),
                            a.segcode,
                            a.brchno,
                            a.instid
                    UNION ALL
                    /* Кассын үлдэгдэл */
                    SELECT l.gl AS gl,
                            a.curcode,
                            '00' AS segcode,
                            l.brchno,
                            SUM (a.bal) AS balance,
                            a.instid
                        FROM ca_cash_bal a
                            LEFT JOIN ca_cash_list l
                                ON l.acntcode = a.acntcode AND a.instid = l.instid
                        WHERE a.instid = :instid
                    GROUP BY l.gl,
                            a.curcode,
                            l.brchno,
                            a.instid
                    UNION ALL
                    /* Зээлийн данс дээрх Балансын гадуурх үндсэн хүүний үлдэгдэл */
                    SELECT t.autoctbtype AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.ctacntno)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type t
                                ON a.prodcode = t.prodcode AND a.instid = t.instid AND t.autoctbtype is not null AND t.autoctbtype != ''
                        WHERE a.instid = :instid
                    GROUP BY t.autoctbtype,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
					UNION ALL
                    /* Зээлийн данс дээрх Балансын гадуурх ком хүүний үлдэгдэл */
					SELECT t.autoctctype AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.ctcomacntno)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type t
                                ON a.prodcode = t.prodcode AND a.instid = t.instid AND t.autoctctype is not null AND t.autoctctype != ''
                        WHERE a.instid = :instid
                    GROUP BY t.autoctctype,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
					UNION ALL
                     /* Зээлийн данс дээрх Балансын гадуурх нэм хүүний үлдэгдэл */
					SELECT t.autoctftype AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.ctfineacntno)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type t
                                ON a.prodcode = t.prodcode AND a.instid = t.instid AND t.autoctftype is not null AND t.autoctftype != ''
                        WHERE a.instid = :instid
                    GROUP BY t.autoctftype,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
					UNION ALL
                    /* Зээлийн данс дээрх Балансын гадуурх шугамын үүргийн дүн */
					SELECT t.autoctltype AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.ctlineacntno) * (-1) AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type t
                                ON a.prodcode = t.prodcode AND a.instid = t.instid AND t.autoctltype is not null AND t.autoctltype != ''
                        WHERE a.instid = :instid
                    GROUP BY t.autoctltype,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
					UNION ALL
                    /* Зээлийн данс дээрх Балансын гадуурх худ зээл үүргийн дүн */
					SELECT t.cecttype AS gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            SUM (a.cectacntno)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ln_account a
                            INNER JOIN ln_account_type t
                                ON a.prodcode = t.prodcode AND a.instid = t.instid AND t.cecttype is not null AND t.cecttype != ''
                        WHERE a.instid = :instid
                    GROUP BY t.cecttype,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
					UNION ALL
                    /* Зээлийн данс дээрх Балансын гадуурх барьц хөрөнгийн дүн */
					SELECT t.ctgl AS gl,
                            coalesce(a.costcurcode, 'MNT') as curcode,
                            c.segcode,
                            coalesce(a.brchno, c.brchno) as brchno,
                            SUM (a.ctacntno)
                            --  * (-1) /*ЕД зөрүүтэй байгаа тул түр хаав*/
                             AS balance,
                            a.instid
                        FROM ln_mor a
                            INNER JOIN ln_mor_type t
                                ON a.prodcode = t.prodcode AND a.instid = t.instid AND t.ctgl is not null AND t.ctgl != ''
							INNER JOIN vw_cr_cust_lists c
                                ON a.custno = c.custno AND a.instid = c.instid
                        WHERE a.instid = :instid
                    GROUP BY t.ctgl,
                            a.costcurcode,
                            c.segcode,
                            coalesce(a.brchno, c.brchno),
                            a.instid
					UNION ALL
                    /* Хорогдуулалт-Дансны үлдэгдэл*/
					SELECT
                            p.gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            -1 * SUM(a.currentbal) AS balance,
                            a.instid
                        FROM ia_de_account a
                        LEFT JOIN ia_de_account_type p
                            ON a.prodcode = p.prodcode AND a.instid = p.instid
                        WHERE a.instid = :instid
                    GROUP BY p.gl,
                            a.curcode,
                            a.segcode,
                            a.brchno,
                            a.instid
            ) b
                    LEFT JOIN GP_inst_cur c
                    ON c.curcode = b.curcode AND c.instid = b.instid AND c.statusid = 1
            WHERE b.gl <> '' OR b.balance <> 0
            GROUP BY b.gl,
                    coalesce(b.segcode, '00'),
                    b.brchno,
                    b.curcode,
                    c.gl,
                    b.gl || coalesce(b.segcode, '00'),
                    b.instid
    ";

        $results = DB::select(DB::raw($query), [
            'instid' => $instid,
            'txndate' => $csysdate
        ]);

        return $results;
    }
    /**
     * Поустинг
     */
    public function PostingSelect($sysdate, $lastitem, $instid)
    {
        $query = DB::table('tr_journal')
            ->selectRaw("
                        jrno,
                        jritemno,
                        tellerno,
                        brchno,
                        txncode,
                        corr,
                        txndate,
                        postdate,
                        txnamount * CASE SIGN WHEN '+' THEN -1 ELSE 1 END AS txnamount,
                        curcode,
                        currate,
                        gl,
                        acntbrchno,
                        segcode,
                        retailacntno,
                        retailacntmod,
                        parenttxncode,
                        txndesc,
                        contacntmod,
                        contacntno,
                        contcurcode,
                        conttxnamount AS conttxnamount,
                        contcurrate,
                        contgl,
                        sign,
                        racntprodcode,
                        clscode,
                        baseamount * CASE SIGN WHEN '+' THEN -1 ELSE 1 END AS baseamount,
                        COALESCE(unitcode, '0000') AS unitcode,
                        txntype,
                        mark,
                        txnjritemno,
                        sourcecode
                        ")
            ->where('txndate', $sysdate)
            ->where('instid', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query = $query->where(function ($q) use ($lastitem) {
                $q->where('jrno', '>', $lastitem->acntno ?? 0)
                    ->orWhere(function ($qq) use ($lastitem) {
                        $qq->where('jrno', $lastitem->acntno ?? 0)
                            ->where('jritemno', '>', $lastitem->acntbrchno ?? 0);
                    });
            });
        }
        $results = $query->orderBy('jrno')->orderBy('jritemno')->get();
        return $results;
    }

    public function createEodList()
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;

        $txndate = CoreService::getEodSysdate($instid);
        $eodison = GPInstSeq::where('instid', $instid)
            ->where('seqid', 'EODISON')->where('seqno', 1)->first();
        if ($eodison) {
            $bluestep = AdEodLog::where('instid', $instid)
                ->where('function', 'ad800098')
                ->orderBy('eoddate', 'desc')->first();
            if ($bluestep && !empty($bluestep->enddate)) {
                $endstep = AdEodLog::where('instid', $instid)
                    ->where('function', 'ad800118')
                    ->orderBy('eoddate', 'desc')->first();
                if ($endstep && empty($endstep->enddate)) {
                    $tmpdate = new Carbon($txndate);
                    $txndate = $tmpdate->subDay();
                }
            }
        }

        $gpeodstep = AdEodLog::where('instid', $instid)
            ->where('eoddate', $txndate)
            ->orderBy('stepno', 'asc')
            ->first();
        /**
         * Өдөр бүр, runmonth runday, сар, улирал, хагас жил, жилийн эцсийг тооцох.
         */
        if (!$gpeodstep) {
            $eodsteps = GPInstEodSteps::where('instid', $instid)
                ->where('statusid', 1)->orderBy('orderno', 'asc')->get();
            $carbntxndate = new Carbon($txndate);
            $insertDatas = [];
            foreach ($eodsteps as $key => $step) {
                $iscreate = false;
                switch ($step->runfreq) {
                    case 'D':
                        $iscreate = true;
                        break;
                    case 'M':
                        $lastDayOfMonth = new Carbon($txndate);
                        if ($carbntxndate->diffInDays($lastDayOfMonth->endOfMonth()) == 0) {
                            $iscreate = true;
                        }
                        break;
                    case 'Q':
                        $lastDayOfNextQuarter  = new Carbon($txndate);
                        $lastDayOfNextQuarter = new Carbon($txndate);
                        if ($carbntxndate->diffInDays($lastDayOfNextQuarter->endOfQuarter()) == 0) {
                            $iscreate = true;
                        }
                        break;
                    case 'H':
                        // Get the last day of the first half-year (June 30th)
                        $firstHalfYear = $carbntxndate->copy()->month(6)->endOfMonth();

                        // Get the last day of the second half-year (December 31st)
                        $secondHalfYear = $carbntxndate->copy()->month(12)->endOfMonth();
                        if (
                            $carbntxndate->diffInDays($firstHalfYear->endOfQuarter()) == 0
                            || $carbntxndate->diffInDays($secondHalfYear->endOfQuarter()) == 0
                        ) {
                            $iscreate = true;
                        }
                        break;
                    case 'Y':
                        if ($carbntxndate->diffInDays($carbntxndate->copy()->endOfYear()) == 0) {
                            $iscreate = true;
                        }
                        break;
                    default:
                        # code...
                        break;
                }

                if (!empty($step->runday)) {
                    if ($step->runday == $carbntxndate->day) {
                        if (
                            (!empty($step->runmonth)
                                && $carbntxndate->month == $step->runmonth)
                            || $step->runmonth == 0
                        ) {
                            $iscreate = true;
                        } else {
                            $iscreate = false;
                        }
                    } else {
                        $iscreate = false;
                    }
                }

                if ($iscreate) {
                    $insertDatas[] = [
                        'stepno' => $key,
                        'eoddate' => $txndate,
                        'name' => $step->name,
                        'name2' => $step->name2,
                        'statusid' => 9,
                        'stepdesc' => $step->stepdesc,
                        'controller' => $step->controller,
                        'function' => $step->function,
                        'exturl' => $step->exturl,
                        'useexturl' => 0,
                        'sqlscript' => $step->sqlscript,
                        'runmonth' => $step->runmonth,
                        'runday' => $step->runday,
                        'startdate' => null,
                        'enddate' => null,
                        'sendsms' => $step->sendsms,
                        'sendemail' => $step->sendemail,
                        'orderno' => $step->orderno,
                        'instid' => $instid,
                        'created_by' => $userid,
                        'created_at' => Carbon::now()
                    ];
                }
            }
            AdEodLog::insert($insertDatas);
        }
    }
}
