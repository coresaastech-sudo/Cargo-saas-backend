<?php

namespace Modules\Ad\Http\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ca\Entities\CaCashBal;
use Modules\Ca\Entities\CaCashBalHist;
use Modules\Ia\Entities\IaAccount;
use Modules\Ia\Entities\IaAccountHist;
use Modules\Ia\Entities\IaDeAccount;
use Modules\Ia\Entities\IaDeAccountHist;
use Modules\Ia\Entities\IaRecPay;
use Modules\Ia\Entities\IaRecPayHist;

class IaEodService
{

    /**
     * Дотоодын дансны үлдэгдэл түр хадгалах ad800054
     */
    public function CreateTmpIaBals($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate);

        $sql = IaAccount::select([
            'acntno',
            'currentbal',
            'statusid',
            'brchno',
            'typecode',
        ])
            ->where(function ($query) {
                $query->whereRaw('COALESCE(tmp_currentbal::varchar, \'0\') <> COALESCE(currentbal::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_statusid::varchar, \'0\') <> COALESCE(statusid::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_brchno::varchar, \'0\') <> COALESCE(brchno::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_typecode::varchar, \'0\') <> COALESCE(typecode::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_clscode::varchar, \'0\') <> COALESCE(clscode::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_clscodetrm::varchar, \'0\') <> COALESCE(clscodetrm::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_clscodeqlt::varchar, \'0\') <> COALESCE(clscodeqlt::varchar, \'0\')');
            })
            ->where('instid', '=', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('acntno', 'ASC')->get();
        return $results;
    }
    /**
     * Хорогдуулалтын дансны үлдэгдэл түр хадгалах ad800133
     */
    public function CreateTmpIaDeBals($sysdate, $lastitem, $instid)
    {
        $query = IaDeAccount::select([
            'acntno',
            'brchno',
            'irr',
            'currentbal',
            'totaldeprbal',
            'depramount'
        ])
            ->where(function ($q) {
                $q->whereRaw('tmp_irr IS DISTINCT FROM irr')
                    ->orWhereRaw('tmp_currentbal IS DISTINCT FROM currentbal')
                    ->orWhereRaw('tmp_totaldeprbal IS DISTINCT FROM totaldeprbal')
                    ->orWhereRaw('tmp_depramount IS DISTINCT FROM depramount');
            })
            ->where('instid', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query->where('acntno', '>', $lastitem->acntno);
        }

        return $query->orderBy('acntno', 'ASC')->get();
    }
    /**
     * Авлагын үлдэгдэл түр хадгалах ad800137
     */
    public function CreateTmpIaRecBals($sysdate, $lastitem, $instid)
    {
        $query = IaRecPay::select([
            'recpayno',
            'brchno',
            'amount',
            'balance',
            'insurancepaidamount',
            'statusid',
            'clscode',
            'clscodetrm',
            'clscodeqlt',
        ])
            ->where(function ($q) {
                $q->whereRaw('tmp_clscode IS DISTINCT FROM clscode')
                    ->orWhereRaw('tmp_clscodetrm IS DISTINCT FROM clscodetrm')
                    ->orWhereRaw('tmp_clscodeqlt IS DISTINCT FROM clscodeqlt')
                    ->orWhereRaw('tmp_amount IS DISTINCT FROM amount')
                    ->orWhereRaw('tmp_balance IS DISTINCT FROM balance')
                    ->orWhereRaw('tmp_insurancepaidamount IS DISTINCT FROM insurancepaidamount')
                    ->orWhereRaw('tmp_statusid IS DISTINCT FROM statusid');
            })
            ->where('instid', $instid);

        if ($lastitem && $lastitem->acntno) {
            $query->where('recpayno', '>=', $lastitem->acntno);
        }

        return $query->orderBy('recpayno', 'ASC')->get();
    }

    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800120
     */
    public function IaAcntHistDel($sysdate, $lastitem, $instid)
    {
        IaAccountHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад зээлийн дансны мэдээлэл авах ad800120
     */
    public function IaAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = IaAccount::select(
            'acntno',
            DB::raw("'$sysdate' as txndate"),
            'brchno',
            'segcode',
            'typecode',
            'name',
            'name2',
            'statusid',
            'catcode',
            'currentbal',
            DB::raw('COALESCE (openeddate, created_at) AS openeddate'),
            'closeddate',
            'bankcode',
            'vostroacntno',
            'curcode',
            'reserved',
            'unitcode',
            'hide',
            'lasttellertxndate',
            'txndef',
            'risk',
            'tellerfunc',
            'transitacntno',
            'mainvostroacnt',
            'dpacntno',
            'instid',
            'tmp_currentbal',
            'tmp_statusid',
            'tmp_brchno',
            'tmp_typecode',
            'created_by',
            DB::raw("'$caldate' AS created_at"),
            'startdate',
            'enddate',
            'termbasis',
            'termlen',
            'intrate',
            'clscode',
            'clscodetrm',
            'clscodeqlt',
            'autocls',
            'autoriskfund',
            'tmp_clscode',
            'tmp_clscodetrm',
            'tmp_clscodeqlt'
        )
            ->where('instid', $instid)->where('statusid', '!=', 0)->get();
        return $results;
    }

    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800132
     */
    public function DeAcntHistDel($sysdate, $lastitem, $instid)
    {
        IaDeAccountHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад зээлийн дансны мэдээлэл авах ad800132
     */
    public function DeAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = IaDeAccount::select(
            'acntno',
            DB::raw("'$sysdate' as txndate"),
            'prodcode',
            'typecode',
            'curcode',
            'irr',
            'activeschdid',
            'deprmethod',
            'deprfreq',
            'brchno',
            'segcode',
            'statusid',
            'created_date',
            'depr_enddate',
            'linked_acntno',
            'linked_custno',
            'linked_acntmod',
            'linked_acntprodcode',
            'crtschd',
            'currentbal',
            'totaldeprbal',
            'depramount',
            'instid',
            'created_by',
            DB::raw("'$caldate' AS created_at"),
            'tmp_irr',
            'tmp_currentbal',
            'tmp_totaldeprbal',
            'tmp_depramount'
        )
            ->where('instid', $instid)->where('statusid', '!=', 0)->get();
        return $results;
    }


    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800134
     */
    public function CaAcntHistDel($sysdate, $lastitem, $instid)
    {
        CaCashBalHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад зээлийн дансны мэдээлэл авах ad800134
     */
    public function CaAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = CaCashBal::select(
            'acntcode',
            DB::raw("'$sysdate' as txndate"),
            'sbal',
            'bal',
            'ctbal',
            'dtbal',
            'curcode',
            'sdate',
            'userid',
            'instid',
            'statusid',
            'created_by',
            DB::raw("'$caldate' AS created_at")
        )
            ->where('instid', $instid)->where('statusid', '!=', 0)->get();
        return $results;
    }
    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800138
     */
    public function RecPayHistDel($sysdate, $lastitem, $instid)
    {
        IaRecPayHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад авлагын мэдээлэл авах ad800138
     */
    public function RecPayAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = IaRecPay::select(
            'recpayno',
            DB::raw("'$sysdate' as txndate"),
            'custno',
            'type',
            'amount',
            'curcode',
            'startdate',
            'enddate',
            'balance',
            'is_insurance',
            'insurancepaidamount',
            'retailacntno',
            'tacnttype',
            'tacntno',
            'txndesc',
            'brchno',
            'clscode',
            'clscodetrm',
            'clscodeqlt',
            'autocls',
            'autorecrf',
            'tmp_clscode',
            'tmp_clscodetrm',
            'tmp_clscodeqlt',
            'tmp_amount',
            'tmp_balance',
            'tmp_insurancepaidamount',
            'tmp_statusid',
            'statusid',
            'instid',
            'created_by',
            DB::raw("'$caldate' AS created_at")
        )
            ->where('instid', $instid)->where('statusid', '!=', 0)->get();
        return $results;
    }

    /**
     * Орлого зарлагын үлдэгдэлтэй дансны жагсаалт авах ad800092
     */
    public function IaAcntIncomeExpense($sysdate, $lastitem, $instid, $userid)
    {
        $sql = IaAccount::select(
            'acntno',
            'currentbal',
            'ia_account.curcode',
            'brchno',
            'ia_account.typecode',
            'tmp_currentbal',
            'ia_account.statusid',
            'ia_account.instid',
            'gl'
        )
            ->join('ia_account_type', function ($join) {
                $join->on('ia_account_type.typecode', '=', 'ia_account.typecode')
                    ->on('ia_account_type.instid', '=', 'ia_account.instid');
            })
            ->where('ia_account.instid', $instid)
            ->where('ia_account.statusid', '!=', 0)
            ->where('ia_account.currentbal', '!=', 0)
            ->whereIn('ia_account_type.acntchar', ['E', 'R']);

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('ia_account.acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('ia_account.acntno', 'ASC')->get();

        return $results;
    }

    /**
     * Өмчлөх бусад хөрөнгийн ангилал шилжүүлэх жагсаалт авах ad800135
     */
    public function IaOfClsChangeList($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT
                        a.acntno,
                        a.brchno,
                        a.newclscodetrm,
                        a.clscodeqlt,
                        a.clscode,
                        a.autocls,
                        a.instid
                    FROM (
                        SELECT
                            b.acntno,
                            b.brchno,
                            cl.value::int AS newclscodetrm,
                            1 AS clscodeqlt,
                            b.clscode,
                            b.autocls,
                            b.instid
                        FROM (
                            SELECT
                                b.acntno,
                                b.brchno,
                                b.currentbal,
                                b.curcode,
                                :sysdate - b.enddate AS days,
                                (
                                    SELECT MAX(cl.value_add1::int)
                                    FROM GP_const cl
                                    WHERE cl.parent_code = 'clscode'
                                    AND cl.value_add1::int <= :sysdate - b.enddate
                                    AND b.enddate < :sysdate
                                ) AS clsday,
                                (
                                    SELECT MIN(cl.value_add1::int)
                                    FROM GP_const cl
                                    WHERE cl.parent_code = 'clscode'
                                ) AS clsdaymin,
                                b.clscode,
                                b.autocls,
                                b.instid
                            FROM vw_ia_acnt_list b
                            WHERE b.statusid = 4
                            AND b.instid = :instid
                            AND b.acntchar = 'A'
                            AND b.enddate IS NOT NULL
                            AND b.enddate < :sysdate
                            AND b.currentbal < 0
                        ) b
                        LEFT JOIN GP_const cl
                            ON cl.parent_code = 'clscode'
                            AND COALESCE(b.clsday, b.clsdaymin) = cl.value_add1::int
                    ) a
                    WHERE a.newclscodetrm <> a.clscode
                    AND a.instid = :instid
                    AND a.autocls = 1
                ";


        $bindings = [
            'instid' => $instid,
            'sysdate' => $sysdate,
        ];

        if ($lastitem && $lastitem->acntno) {
            $sql .= " AND a.acntno > :last_acntno ";
            $bindings['last_acntno'] = $lastitem->acntno;
        }

        $sql .= " ORDER BY a.acntno ASC ";

        return DB::select($sql, $bindings);
    }
    /**
     * Авлага ангилал шилжүүлэх жагсаалт авах ad800126
     */
    public function IaRecClsChangeList($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT * FROM
                        (
                            SELECT
                                b.recpayno,
                                b.brchno,
                                cl.value::int newclscodetrm,
                                1 clscodeqlt,
                                b.clscode,
                                b.autocls,
                                b.instid
                            FROM
                                (
                                    SELECT
                                        b.recpayno,
                                        b.brchno,
                                        b.enddate,
                                        b.balance,
                                        b.curcode,
                                        :sysdate - b.enddate AS days,
                                        (
                                            SELECT
                                                MAX (cl.value_add1::int) clsday
                                            FROM
                                                GP_const cl
                                            WHERE
                                                cl.parent_code = 'clscode'
                                                AND cl.value_add1::int <= :sysdate - b.enddate
                                                AND b.enddate < :sysdate
                                        ) AS clsday,
                                        (
                                            SELECT
                                                MIN (cl.value_add1::int) clsday
                                            FROM
                                                GP_const cl
                                            WHERE
                                                cl.parent_code = 'clscode'
                                        ) AS clsdaymin,
                                        b.clscode,
                                        b.autocls,
                                        b.instid
                                    FROM
                                        ia_rec_pay b
                                        LEFT JOIN ia_account ba ON ba.instid = b.instid and ba.acntno = b.retailacntno
                                    WHERE
                                        b.statusid = 1
                                        AND b.instid = :instid
                                        AND b.type = 'R'
                                        AND b.enddate < :sysdate
                                        AND b.balance > 0
                                ) b
                                LEFT JOIN GP_const cl ON cl.parent_code = 'clscode'
                                AND CASE
                                    WHEN b.clsday IS NULL THEN b.clsdaymin
                                    ELSE b.clsday
                                END = cl.value_add1::int
                        ) a
                    WHERE
                        a.newclscodetrm <> a.clscode and a.instid = :instid and a.autocls = 1

                ";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and a.recpayno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by a.recpayno ASC ";

        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'sysdate' => $sysdate
        ]);
        return $results;
    }
    /**
     * Авлагын авто төлөлтийн гүйлгээний жагсаалт авах ad800125
     */
    public function IaRecPaymentList($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT * from (
                SELECT
                case when m.tacnttype = 'DP' then d.acntno else b.acntno end  contacntno
                , m.tacnttype
                , m.retailacntno
                , m.curcode
                , case when m.tacnttype = 'DP' then
                            case when d.currentbal > m.balance then m.balance
                            else d.currentbal end
                       else
                            case when b.currentbal > m.balance then m.balance
                            else b.currentbal end
                  end AS txnamount
                , m.txndesc
                , m.type
                , case when m.tacnttype = 'DP' then d.brchno else b.brchno end  brchno
                , m.recpayno
                from ia_rec_pay m
                left join dp_account d on d.instid = m.instid and m.tacntno = d.acntno
                left join ia_account b on b.instid = m.instid and m.tacntno = b.acntno
                where m.instid = :instid
                and m.enddate <= :sysdate
                and m.balance > 0
                and m.type = 'R'
                and m.tacntno is not null
                and m.statusid = 1 ) m
                where m.txnamount > 0
                ";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and m.recpayno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by m.recpayno ASC ";

        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'sysdate' => $sysdate
        ]);
        return $results;
    }

    /**
     * Хорогдуулалтын гүйлгээний жагсаалт авах ad800129
     */
    public function IaDeprectionList($sysdate, $lastitem, $instid)
    {
        $is_eom = Carbon::parse($sysdate)->isLastOfMonth() ? 1 : 0; // 1 бол сарын эцэс 0 бол үгүй
        $day = Carbon::parse($sysdate)->day;
        $sql = "SELECT b.*
                    from (
                        select
                            a.txntype,
                            a.currentbal,
                            :sysdate as txndate,
                            case
                                when abs(a.currentbal) - abs(a.txnamount) < 0
                                    then abs(round(a.currentbal::numeric, 2))
                                    else round(a.txnamount::numeric, 2)
                            end as txnamount,
                            case
                                when abs(a.currentbal) - abs(a.txnamount) < 0
                                    then 1
                                    else 0
                            end as iserror,
                            a.curcode,
                            coalesce(a.segcode, '00') as segcode,
                            at.gl as gl,
                            at.contgl as contgl,
                            a.brchno as acntbrchno,
                            a.acntno as retailacntno,
                            a.typecode as racntprodcode,
                            a.prodcode,
                            a.instid
                        from (
                            -- 1-р union хэсэг a.deprmethod = 1 or (a.deprmethod = 2 and a.deprfreq = 'S')
                            select
                                a.currentbal,
                                case when a.currentbal > 0 then 0 else 1 end as txntype,
                                a.typecode, a.linked_acntprodcode, a.linked_acntmod,
                                a.acntno, a.curcode, a.brchno, a.segcode, a.instid, a.prodcode,
                                case
                                    when s.islast = 1 then
                                        case
                                            when a.currentbal > 0 then a.currentbal
                                            else -1 * a.currentbal
                                        end
                                    else
                                        case
                                            when a.deprfreq = 'D' then s.deprdailyamount
                                            else s.depramount
                                        end
                                end as txnamount
                            from ia_de_schd s
                            left join ia_de_account a
                                on s.acntno = a.acntno and s.schdid = a.activeschdid and s.instid = a.instid
                            where a.statusid = 1
                                and a.currentbal <> 0
                                and (a.deprmethod = 1 or (a.deprmethod = 2 and a.deprfreq = 'S'))
                                and s.deprday = :sysdate
								and s.instid = :instid
                            union all

                            -- 2-р union хэсэг a.deprmethod = 2
                            select
                                a.currentbal,
                                case when a.currentbal > 0 then 0 else 1 end as txntype,
                                a.typecode, a.linked_acntprodcode, a.linked_acntmod,
                                a.acntno, a.curcode, a.brchno, a.segcode, a.instid, a.prodcode,
                                case
                                    when a.depr_enddate - interval '1 day' <= :sysdate
                                        then case when a.currentbal > 0 then a.currentbal else -1 * a.currentbal end
                                    else a.depramount
                                end as txnamount
                            from ia_de_account a
                            where a.statusid = 1
                                and a.currentbal <> 0
                                and a.deprmethod = 2
                                and a.deprfreq = 'D'
                                and a.instid = :instid

                            union all

                            -- 3-р union хэсэг deprmethod = 3
                            select
                                a.currentbal,
                                case when a.currentbal > 0 then 0 else 1 end as txntype,
                                a.typecode, a.linked_acntprodcode, a.linked_acntmod,
                                a.acntno, a.curcode, a.brchno, a.segcode, a.instid, a.prodcode,
                                case
                                    when a.depr_enddate - interval '1 day' <= :sysdate
                                        then case when a.currentbal > 0 then a.currentbal else -1 * a.currentbal end
                                    else a.depramount
                                end as txnamount
                            from ia_de_account a
                            left join ia_de_account_type at on a.prodcode = at.prodcode and a.instid = at.instid
                            join ia_de_type_prodlink pl
                                on a.prodcode = pl.deprodcode and a.instid = pl.instid
                            and pl.prodcode = a.linked_acntprodcode
                            and pl.prodmodule = a.linked_acntmod and pl.statusid = 1
                            where a.instid = :instid
                            	and a.statusid = 1
                                and a.currentbal <> 0
                                and at.deprmethod = 3
                                and (
                                    a.deprfreq = 'D'
                                    or (at.deprfreq = 'E'
                                        and :is_eom = 1
                                    )
                                    or (
                                        at.deprfreq = 'M'
                                        and (
                                            case
                                                when :is_eom = 1 then
                                                    case
                                                        when at.deprday >= :dday then 1 else 0
                                                    end
                                                else
                                                    case
                                                        when at.deprday = :dday then 1 else 0
                                                    end
                                            end
                                        ) = 1
                                    )
                                )

                            union all

                                -- 4-р union хэсэг LN үед deprmethod = 4
                            select
                                a.currentbal,
                                case when a.currentbal > 0 then 0 else 1 end as txntype,
                                a.typecode, a.linked_acntprodcode, a.linked_acntmod,
                                a.acntno, a.curcode, a.brchno, a.segcode, a.instid, a.prodcode,
                                ((a.currentbal + a.totaldeprbal) * (100 - (100 * ln.princbal /
                                case when ln.advamount = 0 then ln.approvamount else ln.advamount end
                                )) / 100) - a.totaldeprbal as txnamount
                            from ia_de_account a
                            left join ia_de_account_type at on a.prodcode = at.prodcode and a.instid = at.instid
                            join ia_de_type_prodlink pl
                                on a.prodcode = pl.deprodcode and a.instid = pl.instid
                            and pl.prodcode = a.linked_acntprodcode
                            and pl.prodmodule = a.linked_acntmod and pl.statusid = 1
                            join ln_account ln on a.instid = ln.instid and a.linked_acntmod = 'LN' and a.linked_acntno = ln.acntno
                            where a.instid = :instid
                            	and a.statusid = 1
                                and a.currentbal <> 0
                                and at.deprmethod = 4
                                and ((a.currentbal + a.totaldeprbal) * (100 - (100 * ln.princbal /
                                case when ln.advamount = 0 then ln.approvamount else ln.advamount end
                                )) / 100) - a.totaldeprbal > 0.01
                                and (
                                    a.deprfreq = 'D'
                                    or (at.deprfreq = 'E'
                                        and :is_eom = 1
                                    )
                                    or (
                                        at.deprfreq = 'M'
                                        and (
                                            case
                                                when :is_eom = 1 then
                                                    case
                                                        when at.deprday >= :dday then 1 else 0
                                                    end
                                                else
                                                    case
                                                        when at.deprday = :dday then 1 else 0
                                                    end
                                            end
                                        ) = 1
                                    )
                                )
                            union all

                                -- 5-р union хэсэг DP үед deprmethod = 4

                            select
                                    a.currentbal,
                                    case when a.currentbal > 0 then 0 else 1 end as txntype,
                                    a.typecode, a.linked_acntprodcode, a.linked_acntmod,
                                    a.acntno, a.curcode, a.brchno, a.segcode, a.instid, a.prodcode,
                                    CASE
                                    WHEN COALESCE(inv.amount, 0) = 0 THEN 0
                                    ELSE
                                        (
                                        a.currentbal
                                        - (a.currentbal + a.totaldeprbal) * dp.currentbal / NULLIF(inv.amount, 0)
                                        )
                                        * (CASE WHEN a.currentbal > 0 THEN 1 ELSE -1 END)
                                    END AS txnamount
                                from ia_de_account a
                                left join ia_de_account_type at on a.prodcode = at.prodcode and a.instid = at.instid
                                join ia_de_type_prodlink pl
                                    on a.prodcode = pl.deprodcode and a.instid = pl.instid
                                and pl.prodcode = a.linked_acntprodcode
                                and pl.prodmodule = a.linked_acntmod and pl.statusid = 1
                                join dp_account dp on a.instid = dp.instid and a.linked_acntmod = 'DP' and a.linked_acntno = dp.acntno
                                join dp_inv_account inv on a.instid = inv.instid and dp.acntno = inv.invacntno and inv.statusid = 1
                                where a.instid = :instid
                                    and a.statusid = 1
                                    and a.currentbal <> 0
                                    and at.deprmethod = 4
                                    and
                                    CASE WHEN inv.amount = 0 THEN 0
                                        ELSE ((a.currentbal + a.totaldeprbal) * (100 - (100 * dp.currentbal / inv.amount)) / 100) - a.totaldeprbal
                                    END <> 0
                                    and (
                                        a.deprfreq = 'D'
                                        or (at.deprfreq = 'E'
                                            and :is_eom = 1
                                        )
                                        or (
                                            at.deprfreq = 'M'
                                            and (
                                                case
                                                    when :is_eom = 1 then
                                                        case
                                                            when at.deprday >= :dday then 1 else 0
                                                        end
                                                    else
                                                        case
                                                            when at.deprday = :dday then 1 else 0
                                                        end
                                                end
                                            ) = 1
                                        )
                                    )

                        ) a
                        left join ia_de_account_type at on at.prodcode = a.prodcode and a.instid = at.instid and at.statusid = 1
                        -- зөвшөөрөгдсөн бүтээгдэхүүн → холбоотой бүтээгдэхүүн хослол дээр л гүйлгээ үүсгэнэ
                        left join ia_de_type_prodlink pl
                            on at.prodcode = pl.deprodcode and pl.statusid = 1
                        and pl.prodcode = a.linked_acntprodcode
                        and pl.prodmodule = a.linked_acntmod
                        and a.instid = pl.instid
                        where a.txnamount <> 0 and a.instid = :instid
                    ) b
                ";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " where b.instid = :instid and b.retailacntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by b.retailacntno ASC ";

        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'sysdate' => $sysdate,
            'is_eom' => $is_eom,
            'dday' => $day
        ]);
        return $results;
    }

    /**
     * Өдрийн хорогдуулах дүн тодорхойлох жагсаалт авах ad800130
     * Арга2 бөгөөд давтамж нь өдрөөр байх данснуудыг авчирах
     */
    public function IaDeprAmountList($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT
                    a.acntno,
                    a.brchno,
                    s1.deprdailyamount,
                    a.depramount,
                    s.instid
                from ia_de_schd s
                left join ia_de_account a
                    on s.acntno = a.acntno
                        and s.schdid = a.activeschdid
                        and s.instid = a.instid
                left join ia_de_schd s1
                    on s.acntno = s1.acntno
                        and s.schdid = s1.schdid
                        and s1.statusid = 1
                        and s1.itemno = s.itemno + 1
                            and s.instid = s1.instid
                where s.instid = :instid
                and s.statusid = 1
                and a.statusid = 1
                and a.currentbal <> 0
                and a.deprmethod = 2
                and a.deprfreq = 'D'
                and a.linked_acntmod = 'LN'
                and s.deprday = :sysdate
                and s1.deprdailyamount IS NOT NULL
                ";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and a.acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by a.acntno ASC ";

        $results = DB::select(DB::raw($sql), [
            'instid' => $instid,
            'sysdate' => $sysdate
        ]);
        return $results;
    }
    /**
     * Хорогдуулалтын дансны хуваарь үүсгэх жагсаалт авах ad800128
     * Хуваарь үүсгэх шаардлагатай дансуудыг олж авчирах
     */
    public function IaDeprSchdAcntList($sysdate, $lastitem, $instid)
    {
        $sql = "SELECT
                    d.acntno,
                    at.name,
                    d.depramount,
                    d.currentbal + d.totaldeprbal as begbal,
                    d.currentbal,
                    d.totaldeprbal,
                    d.prodcode,
                    at.contgl,
                    at.deprfreq,
                    at.deprmethod,
                    at.deprday,
                    expgl,
                    at.gl,
                    ocapgl,
                    revgl,
                    at.typecode,
                    d.brchno,
                    d.activeschdid,
                    d.linked_acntno,
                    d.linked_acntprodcode,
                    d.linked_acntmod,
                    d.curcode,
                    d.statusid
                from ia_de_account d
                join ia_de_account_type at on d.prodcode = at.prodcode and d.instid = at.instid and at.module = 'LN'
                join ia_de_type_prodlink pl
                    on d.prodcode = pl.deprodcode
                        and pl.instid = d.instid
                        and pl.prodcode = d.linked_acntprodcode
                        and pl.prodmodule = d.linked_acntmod
                        and pl.prodmodule = 'LN'
                where d.crtschd = 1 and d.instid = :instid and d.linked_acntmod = 'LN'
                ";

        if ($lastitem && $lastitem->acntno) {
            $sql = $sql . " and d.acntno >= '" . $lastitem->acntno . "' ";
        }
        $sql = $sql . " order by d.acntno ASC ";

        $results = DB::select(DB::raw($sql), [
            'instid' => $instid
        ]);
        return $results;
    }
}
