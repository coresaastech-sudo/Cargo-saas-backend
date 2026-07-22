<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdDividentEquityChange;
use Modules\Ad\Entities\AdDividentProfit;
use Modules\Ad\Entities\AdDividentProfitDetail;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Http\Services\IaTxnService;

class AdDividentController extends Controller
{
    public function ad090000(Request $request)
    {
        $validated = $this->validateMe($request, [
            'startdate' => 'nullable|date_format:Y-m-d',
            'enddate' => 'nullable|date_format:Y-m-d',
            'prodcode' => 'nullable|max:10',
        ]);

        $instid = (int) auth()->user()->instid;
        $txndate = new Carbon(CoreService::getTxnDate($instid));
        $this->checkCreditUnion($instid);
        $prodcode = $this->resolveEquityProdcode($validated['prodcode'] ?? null, $instid);
        $startdate = $validated['startdate']
            ?? $txndate->copy()->subYears(2)->endOfYear()->format('Y-m-d');

        $enddate = $validated['enddate']
            ?? $txndate->copy()->subYear()->endOfYear()->format('Y-m-d');

        $pdo = DB::connection()->getPdo();
        $startdateSql = $pdo->quote($startdate);
        $enddateSql = $pdo->quote($enddate);
        $prodcodeSql = $pdo->quote($prodcode);

        $sql = "
            with end_hist as (
                select
                    dp.*
                from dp_account_hist dp
                where dp.instid = {$instid}
                    and dp.prodcode = {$prodcodeSql}
                    and dp.txndate = {$enddateSql}::date
            ),
            start_hist as (
                select
                    dps.*
                from dp_account_hist dps
                where dps.instid = {$instid}
                    and dps.prodcode = {$prodcodeSql}
                    and dps.txndate = {$startdateSql}::date
            ),
            base as (
                select
                    cr.lname || ' ' || cr.name as name,
                    cr.id1,
                    cr.txndate,
                    coalesce(dp.custno, dps.custno) as custno,
                    coalesce(dp.instid, dps.instid) as instid,
                    coalesce(dp.acntno, dps.acntno) as acntno,
                    coalesce(dps.currentbal, 0) as startbal,
                    coalesce(dp.currentbal, 0) as endbal,
                    case
                        when sum(coalesce(dp.currentbal, 0)) over () = 0 then 0
                        else coalesce(dp.currentbal, 0) / sum(coalesce(dp.currentbal, 0)) over () * 100
                    end as weight
                from end_hist dp
                full join start_hist dps
                    on dps.instid = dp.instid
                    and dps.acntno = dp.acntno
                join vw_cr_cust_lists cr
                    on cr.instid = coalesce(dp.instid, dps.instid)
                    and cr.custno = coalesce(dp.custno, dps.custno)
            ),
            add_txn as (
                select
                    t.instid,
                    t.acntno,
                    row_number() over (
                        partition by t.instid, t.acntno
                        order by t.txndate, t.jrno, t.jritemno
                    ) as rowno,
                    t.txndate as adddate,
                    abs(t.txnamount) as addamount
                from dp_txn t
                where t.instid = {$instid}
                    and t.prodcode = {$prodcodeSql}
                    and t.corr = 0
                    and t.txntype = 1
                    and abs(coalesce(t.txnamount, 0)) > 0
                    and t.txndate > {$startdateSql}::date
                    and t.txndate <= {$enddateSql}::date
            ),
            minus_txn as (
                select
                    t.instid,
                    t.acntno,
                    row_number() over (
                        partition by t.instid, t.acntno
                        order by t.txndate, t.jrno, t.jritemno
                    ) as rowno,
                    t.txndate as minusdate,
                    abs(t.txnamount) as minusamount
                from dp_txn t
                where t.instid = {$instid}
                    and t.prodcode = {$prodcodeSql}
                    and t.corr = 0
                    and t.txntype = 0
                    and abs(coalesce(t.txnamount, 0)) > 0
                    and t.txndate > {$startdateSql}::date
                    and t.txndate <= {$enddateSql}::date
            ),
            txn_rows as (
                select
                    coalesce(a.instid, m.instid) as instid,
                    coalesce(a.acntno, m.acntno) as acntno,
                    coalesce(a.rowno, m.rowno) as rowno,
                    a.addamount,
                    a.adddate,
                    m.minusamount,
                    m.minusdate
                from add_txn a
                full join minus_txn m
                    on m.instid = a.instid
                    and m.acntno = a.acntno
                    and m.rowno = a.rowno
            )
            select
                b.name as sortname,
                coalesce(t.rowno, 1) as rowno,
                b.name,
                b.id1,
                b.custno,
                b.txndate,
                b.acntno,
                round(b.startbal, 2)::double precision as startbal,
                round(t.addamount, 2)::double precision as addamount,
                t.adddate,
                round(t.minusamount, 2)::double precision as minusamount,
                t.minusdate,
                round(b.endbal, 2)::double precision as endbal,
                round(b.weight, 5)::double precision as weight
            from base b
            left join txn_rows t
                on t.instid = b.instid
                and t.acntno = b.acntno
        ";

        $data = $this->getGridDataWithoutPaging(
            $request,
            DB::query()->from(DB::raw("({$sql}) as dividend")),
            [
                ['field' => 'sortname', 'dir' => 'asc'],
                ['field' => 'rowno', 'dir' => 'asc'],
            ]
        );

        return $this->formatDividendNumbers($data);
    }

    public function ad090200(Request $request)
    {
        $rows = $request->input('data', $request->input('rows', $request->input('txns')));
        $request->merge(['data' => $rows]);

        $validated = $this->validateMe($request, [
            'startdate' => 'nullable|date_format:Y-m-d',
            'enddate' => 'nullable|date_format:Y-m-d',
            'prodcode' => 'nullable|max:10',
            'data' => 'required|array',
            'data.*.name' => 'nullable|max:200',
            'data.*.id1' => 'nullable|max:50',
            'data.*.custno' => 'nullable|max:20',
            'data.*.txndate' => 'nullable|date_format:Y-m-d',
            'data.*.acntno' => 'nullable|max:20',
            'data.*.startbal' => 'nullable|numeric',
            'data.*.addamount' => 'nullable|numeric',
            'data.*.adddate' => 'nullable|date_format:Y-m-d',
            'data.*.minusamount' => 'nullable|numeric',
            'data.*.minusdate' => 'nullable|date_format:Y-m-d',
            'data.*.endbal' => 'nullable|numeric',
            'data.*.weight' => 'nullable|numeric',
        ]);

        $instid = (int) auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = new Carbon(CoreService::getTxnDate($instid));
        $this->checkCreditUnion($instid);
        $prodcode = $this->resolveEquityProdcode($validated['prodcode'] ?? null, $instid);
        $startdate = $validated['startdate']
            ?? $txndate->copy()->subYears(2)->endOfYear()->format('Y-m-d');
        $enddate = $validated['enddate']
            ?? $txndate->copy()->subYear()->endOfYear()->format('Y-m-d');
        $this->assertDividendBatchEditable($instid, $startdate, $enddate, $prodcode);

        try {
            DB::beginTransaction();

            AdDividentEquityChange::where('instid', $instid)
                ->where('startdate', $startdate)
                ->where('enddate', $enddate)
                ->where('prodcode', $prodcode)
                ->where('statusid', 1)
                ->update([
                    'statusid' => -1,
                    'updated_by' => $userid,
                ]);

            foreach ($validated['data'] as $index => $row) {
                AdDividentEquityChange::create([
                    'startdate' => $startdate,
                    'enddate' => $enddate,
                    'prodcode' => $prodcode,
                    'rowno' => $index + 1,
                    'name' => $row['name'] ?? null,
                    'id1' => $row['id1'] ?? null,
                    'custno' => $row['custno'] ?? null,
                    'txndate' => $row['txndate'] ?? null,
                    'acntno' => $row['acntno'] ?? null,
                    'startbal' => $row['startbal'] ?? null,
                    'addamount' => $row['addamount'] ?? null,
                    'adddate' => $row['adddate'] ?? null,
                    'minusamount' => $row['minusamount'] ?? null,
                    'minusdate' => $row['minusdate'] ?? null,
                    'endbal' => $row['endbal'] ?? null,
                    'weight' => $row['weight'] ?? null,
                    'process_statusid' => 0,
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $userid,
                    'updated_by' => $userid,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return;
    }

    public function ad091000(Request $request)
    {
        $validated = $this->validateMe($request, [
            'startdate' => 'nullable|date_format:Y-m-d',
            'enddate' => 'nullable|date_format:Y-m-d',
            'dividendamount' => 'nullable|numeric',
            'summary' => 'nullable|in:0,1',
            'zeroignore' => 'nullable|in:0,1',
        ]);

        $instid = (int) auth()->user()->instid;
        $txndate = new Carbon(CoreService::getTxnDate($instid));
        $this->checkCreditUnion($instid);
        $startdate = $validated['startdate']
            ?? $txndate->copy()->subYears(2)->endOfYear()->format('Y-m-d');
        $enddate = $validated['enddate']
            ?? $txndate->copy()->subYear()->endOfYear()->format('Y-m-d');
        $dividendamount = (float) ($validated['dividendamount'] ?? 0);
        $isSummary = (int) ($validated['summary'] ?? 0) === 1;
        $zeroIgnore = (int) ($validated['zeroignore'] ?? 0) === 1;

        $pdo = DB::connection()->getPdo();
        $startdateSql = $pdo->quote($startdate);
        $enddateSql = $pdo->quote($enddate);
        $dividendamountSql = $pdo->quote((string) $dividendamount);
        $memberHavingSql = $zeroIgnore
            ? "having coalesce(max(endbal) filter (where endbal is not null), 0) <> 0"
            : "";
        $profitRowsSql = $isSummary
            ? "
                select
                    m.member_key,
                    m.sortrow,
                    1 as linetype,
                    0 as lineseq,
                    dense_rank() over (order by m.sortrow, m.member_key) as no,
                    m.name,
                    m.id1,
                    m.custno,
                    m.txndate,
                    m.acntno,
                    coalesce(m.startbal, 0) as startbal,
                    nullif(m.total_addamount, 0) as addamount,
                    null::date as adddate,
                    nullif(m.total_minusamount, 0) as minusamount,
                    null::date as minusdate,
                    m.endbal,
                    m.weight,
                    case
                        when m.txn_count > 0 then m.endbal
                        else coalesce(max(d.calc_balance), 0)
                    end as calc_balance,
                    avg(d.days) filter (where d.days is not null) as days,
                    coalesce(sum(d.day_amount), 0) as day_amount,
                    coalesce(sum(d.day_weight), 0) as day_weight,
                    avg(d.div_amount) as div_amount,
                    avg(d.div_percent) as div_percent,
                    avg(d.rate) filter (where d.rate is not null) as rate,
                    coalesce(sum(d.dividend), 0) as dividend,
                    coalesce(sum(d.taxamount), 0) as taxamount
                from member m
                join detail d
                    on d.member_key = m.member_key
                group by
                    m.member_key,
                    m.sortrow,
                    m.name,
                    m.id1,
                    m.custno,
                    m.txndate,
                    m.acntno,
                    m.startbal,
                    m.total_addamount,
                    m.total_minusamount,
                    m.endbal,
                    m.weight,
                    m.txn_count
            "
            : "
                select * from detail
                union all
                select * from summary
            ";

        $sql = "
            with saved as (
                select
                    e.*,
                    coalesce(e.custno, e.acntno, e.id1, e.name, e.id::text) as member_key
                from ad_divident_equity_change e
                where e.instid = {$instid}
                    and e.startdate = {$startdateSql}::date
                    and e.enddate = {$enddateSql}::date
                    and e.statusid = 1
            ),
            member as (
                select
                    member_key,
                    min(rowno) as sortrow,
                    max(name) filter (where name is not null) as name,
                    max(id1) filter (where id1 is not null) as id1,
                    max(custno) filter (where custno is not null) as custno,
                    max(txndate) filter (where txndate is not null) as txndate,
                    max(acntno) filter (where acntno is not null) as acntno,
                    max(startbal) filter (where startbal is not null) as startbal,
                    coalesce(max(endbal) filter (where endbal is not null), 0) as endbal,
                    coalesce(max(weight) filter (where weight is not null), 0) as weight,
                    sum(coalesce(addamount, 0)) as total_addamount,
                    sum(coalesce(minusamount, 0)) as total_minusamount,
                    count(*) filter (
                        where coalesce(addamount, 0) <> 0
                            or coalesce(minusamount, 0) <> 0
                    ) as txn_count
                from saved
                group by member_key
                {$memberHavingSql}
            ),
            txn as (
                select
                    s.member_key,
                    row_number() over (partition by s.member_key order by s.rowno, s.id) as txnrow,
                    s.addamount,
                    s.adddate,
                    s.minusamount,
                    s.minusdate
                from saved s
                where coalesce(s.addamount, 0) <> 0
                    or coalesce(s.minusamount, 0) <> 0
            ),
            lines as (
                select
                    m.member_key,
                    m.sortrow,
                    1 as linetype,
                    0 as lineseq,
                    dense_rank() over (order by m.sortrow, m.member_key) as no,
                    m.name,
                    m.id1,
                    m.custno,
                    m.txndate,
                    m.acntno,
                    coalesce(m.startbal, 0) as startbal,
                    null::numeric as addamount,
                    null::date as adddate,
                    null::numeric as minusamount,
                    null::date as minusdate,
                    m.endbal,
                    m.weight,
                    case
                        when m.total_minusamount > 0 then m.endbal
                        else coalesce(m.startbal, 0)
                    end as calc_balance,
                    ({$enddateSql}::date - {$startdateSql}::date) as days
                from member m

                union all

                select
                    m.member_key,
                    m.sortrow,
                    2 as linetype,
                    t.txnrow as lineseq,
                    null::bigint as no,
                    null::varchar as name,
                    null::varchar as id1,
                    m.custno,
                    null::date as txndate,
                    m.acntno,
                    null::numeric as startbal,
                    t.addamount,
                    t.adddate,
                    t.minusamount,
                    t.minusdate,
                    null::numeric as endbal,
                    null::numeric as weight,
                    case
                        when coalesce(t.addamount, 0) > 0 then t.addamount
                        else null
                    end as calc_balance,
                    case
                        when coalesce(t.addamount, 0) > 0 and t.adddate is not null
                        then ({$enddateSql}::date - t.adddate + 1)
                        else null
                    end as days
                from member m
                join txn t
                    on t.member_key = m.member_key
            ),
            calc as (
                select
                    l.*,
                    case
                        when coalesce(l.calc_balance, 0) > 0 and coalesce(l.days, 0) > 0
                        then l.calc_balance * l.days
                        else 0
                    end as day_amount
                from lines l
            ),
            calc_with_total as (
                select
                    c.*,
                    sum(c.day_amount) over () as total_day_amount
                from calc c
            ),
            detail as (
                select
                    c.*,
                    case
                        when c.total_day_amount = 0 then 0
                        else c.day_amount / c.total_day_amount
                    end as day_weight,
                    case
                        when c.total_day_amount = 0 then 0
                        else {$dividendamountSql}::numeric * c.day_amount / c.total_day_amount
                    end as div_amount,
                    case
                        when coalesce(c.calc_balance, 0) = 0 or c.total_day_amount = 0 then 0
                        else ({$dividendamountSql}::numeric * c.day_amount / c.total_day_amount) / c.calc_balance * 100
                    end as div_percent,
                    case
                        when coalesce(c.calc_balance, 0) = 0 or c.total_day_amount = 0 then null
                        else ({$dividendamountSql}::numeric * c.day_amount / c.total_day_amount) / c.calc_balance
                    end as rate,
                    case
                        when c.total_day_amount = 0 then 0
                        else {$dividendamountSql}::numeric * c.day_amount / c.total_day_amount
                    end as dividend,
                    case
                        when c.total_day_amount = 0 then 0
                        else {$dividendamountSql}::numeric * c.day_amount / c.total_day_amount * 0.1
                    end as taxamount
                from calc_with_total c
            ),
            summary as (
                select
                    m.member_key,
                    m.sortrow,
                    3 as linetype,
                    999999 as lineseq,
                    null::bigint as no,
                    null::varchar as name,
                    null::varchar as id1,
                    m.custno,
                    null::date as txndate,
                    m.acntno,
                    null::numeric as startbal,
                    null::numeric as addamount,
                    null::date as adddate,
                    null::numeric as minusamount,
                    null::date as minusdate,
                    null::numeric as endbal,
                    null::numeric as weight,
                    m.endbal as calc_balance,
                    null::integer as days,
                    0::numeric as day_amount,
                    0::numeric as total_day_amount,
                    0::numeric as day_weight,
                    0::numeric as div_amount,
                    0::numeric as div_percent,
                    null::numeric as rate,
                    coalesce(sum(d.dividend), 0) as dividend,
                    coalesce(sum(d.taxamount), 0) as taxamount
                from member m
                join detail d
                    on d.member_key = m.member_key
                where m.txn_count > 0
                group by m.member_key, m.sortrow, m.custno, m.acntno, m.endbal
            )
            select
                x.sortrow,
                x.linetype,
                x.lineseq,
                x.no,
                x.name,
                x.id1,
                x.custno,
                x.txndate,
                x.acntno,
                round(x.startbal, 2)::double precision as startbal,
                round(x.addamount, 2)::double precision as addamount,
                x.adddate,
                round(x.minusamount, 2)::double precision as minusamount,
                x.minusdate,
                round(x.endbal, 2)::double precision as endbal,
                round(x.weight, 5)::double precision as weight,
                round(x.calc_balance, 2)::double precision as calc_balance,
                x.days,
                round(x.day_amount, 2)::double precision as day_amount,
                round(x.day_weight, 8)::double precision as day_weight,
                round(x.div_amount, 2)::double precision as div_amount,
                round(x.div_percent, 5)::double precision as div_percent,
                round(x.rate, 3)::double precision as rate,
                round(x.dividend, 2)::double precision as dividend,
                round(x.taxamount, 2)::double precision as taxamount,
                round(x.dividend - x.taxamount, 2)::double precision as netamount
            from (
                {$profitRowsSql}
            ) x
        ";

        $data = $this->getGridDataWithoutPaging(
            $request,
            DB::query()->from(DB::raw("({$sql}) as dividend_profit")),
            [
                ['field' => 'sortrow', 'dir' => 'asc'],
                ['field' => 'linetype', 'dir' => 'asc'],
                ['field' => 'lineseq', 'dir' => 'asc'],
            ]
        );

        return $this->formatDividendProfitNumbers($data);
    }

    public function ad091200(Request $request)
    {
        $this->prepareDividendProfitRequest($request);
        $validated = $this->validateDividendProfitPayload($request, 'required');

        $this->saveDividendProfit($validated);

        return;
    }

    public function ad091300(Request $request)
    {
        $this->prepareDividendProfitRequest($request);
        $validated = $this->validateMe($request, [
            'id' => 'required|numeric',
            'from_acntno' => 'required|max:20',
            'txndesc' => 'required',
            'data' => 'required|array',
            'data.*.id' => 'required|numeric',
            'data.*.recievemethod' => 'nullable|in:bankaccount,payloan,equityadd,savingsadd',
            'data.*.recieve_acntno' => 'nullable|max:20',
            'data.*.bank_acntno' => 'nullable|max:50',
        ]);

        $instid = (int) auth()->user()->instid;
        $userid = auth()->user()->id;
        $this->checkCreditUnion($instid);

        $profit = AdDividentProfit::where('instid', $instid)
            ->where('statusid', 1)
            ->find($validated['id']);

        if (!$profit) {
            $this->error('RC000011');
        }

        if ((int) $profit->process_statusid === 1) {
            $this->error('RC000010', ['field' => 'completed dividend profit']);
        }

        try {
            DB::beginTransaction();

            foreach ($validated['data'] as $row) {
                if (empty($row['recievemethod']) || empty($row['recieve_acntno'])) {
                    continue;
                }

                $detail = AdDividentProfitDetail::where('profit_id', $profit->id)
                    ->where('id', $row['id'])
                    ->first();

                if (!$detail) {
                    $this->error('RC000011');
                }

                if ((int) $detail->process_statusid === 1) {
                    continue;
                }

                $detail->update([
                    'recievemethod' => $row['recievemethod'],
                    'recieve_acntno' => $row['recieve_acntno'],
                    'bank_acntno' => $row['bank_acntno'] ?? null,
                ]);

                $txnResult = $this->processDividendProfitDetailTxn(
                    $validated['from_acntno'],
                    $validated['txndesc'],
                    $detail->fresh()
                );

                $detail->update([
                    'process_statusid' => 1,
                    'completed_at' => now(),
                    'completed_by' => $userid,
                    'jrno' => $txnResult->getTxnJrno(),
                ]);
            }

            $detailCount = AdDividentProfitDetail::where('profit_id', $profit->id)->count();
            $incompleteCount = AdDividentProfitDetail::where('profit_id', $profit->id)
                ->where(function ($query) {
                    $query->whereNull('process_statusid')
                        ->orWhere('process_statusid', '<>', 1);
                })
                ->count();

            if ($detailCount > 0 && $incompleteCount === 0) {
                $completedAt = now();

                $profit->update([
                    'process_statusid' => 1,
                    'completed_at' => $completedAt,
                    'completed_by' => $userid,
                    'updated_by' => $userid,
                ]);

                AdDividentEquityChange::where('instid', $instid)
                    ->where('startdate', $profit->startdate)
                    ->where('enddate', $profit->enddate)
                    ->where('prodcode', $profit->prodcode)
                    ->where('statusid', 1)
                    ->update([
                        'process_statusid' => 1,
                        'completed_at' => $completedAt,
                        'completed_by' => $userid,
                        'updated_by' => $userid,
                    ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return;
    }

    public function ad092000(Request $request)
    {
        $instid = (int) auth()->user()->instid;
        $this->checkCreditUnion($instid);

        $detail = AdDividentProfitDetail::select(
            'profit_id',
            DB::raw('count(*) as detail_count'),
            DB::raw('coalesce(sum(dividend), 0) as total_dividend'),
            DB::raw('coalesce(sum(taxamount), 0) as total_taxamount'),
            DB::raw('coalesce(sum(netamount), 0) as total_netamount')
        )->groupBy('profit_id');

        $query = AdDividentProfit::query()
            ->leftJoinSub($detail, 'detail', function ($join) {
                $join->on('detail.profit_id', '=', 'ad_divident_profit.id');
            })
            ->where('ad_divident_profit.instid', $instid)
            ->where('ad_divident_profit.statusid', 1)
            ->select(
                'ad_divident_profit.id',
                'ad_divident_profit.startdate',
                'ad_divident_profit.enddate',
                'ad_divident_profit.prodcode',
                'ad_divident_profit.dividendamount',
                'ad_divident_profit.summary',
                'ad_divident_profit.zeroignore',
                'ad_divident_profit.process_statusid',
                'ad_divident_profit.completed_at',
                'ad_divident_profit.completed_by',
                'ad_divident_profit.created_at',
                'ad_divident_profit.created_by',
                DB::raw('coalesce(detail.detail_count, 0) as detail_count'),
                DB::raw('round(coalesce(detail.total_dividend, 0), 2)::double precision as total_dividend'),
                DB::raw('round(coalesce(detail.total_taxamount, 0), 2)::double precision as total_taxamount'),
                DB::raw('round(coalesce(detail.total_netamount, 0), 2)::double precision as total_netamount')
            );

        return $this->getGridData($request, $query, [
            ['field' => 'ad_divident_profit.created_at', 'dir' => 'desc'],
            ['field' => 'ad_divident_profit.id', 'dir' => 'desc'],
        ]);
    }

    public function ad092100(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required|numeric',
        ]);

        $instid = (int) auth()->user()->instid;
        $this->checkCreditUnion($instid);

        $profit = AdDividentProfit::where('instid', $instid)
            ->where('statusid', 1)
            ->find($validated['id']);

        if (!$profit) {
            $this->error('RC000011');
        }

        $query = AdDividentProfitDetail::where('profit_id', $profit->id);

        return $this->getGridDataWithoutPaging($request, $query, [
            ['field' => 'rowno', 'dir' => 'asc'],
            ['field' => 'id', 'dir' => 'asc'],
        ]);
    }

    public function ad092200(Request $request)
    {
        $rows = $request->input('data', $request->input('rows', $request->input('txns')));
        $request->merge(['data' => $rows]);

        $validated = $this->validateMe($request, [
            'id' => 'required|numeric',
            'data' => 'required|array',
            'data.*.id' => 'required|numeric',
            'data.*.recievemethod' => 'nullable|max:30',
            'data.*.recieve_acntno' => 'nullable|max:20',
            'data.*.bank_acntno' => 'nullable|max:50',
        ]);

        $instid = (int) auth()->user()->instid;
        $this->checkCreditUnion($instid);

        $profit = AdDividentProfit::where('instid', $instid)
            ->where('statusid', 1)
            ->find($validated['id']);

        if (!$profit) {
            $this->error('RC000011');
        }

        if ((int) $profit->process_statusid === 1) {
            $this->error('RC000010', ['field' => 'completed dividend profit']);
        }

        try {
            DB::beginTransaction();

            foreach ($validated['data'] as $row) {
                $detail = AdDividentProfitDetail::where('profit_id', $profit->id)
                    ->where('id', $row['id'])
                    ->first();

                if (!$detail) {
                    $this->error('RC000011');
                }

                $detail->update([
                    'recievemethod' => $row['recievemethod'] ?? null,
                    'recieve_acntno' => $row['recieve_acntno'] ?? null,
                    'bank_acntno' => $row['bank_acntno'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return;
    }


    private function prepareDividendProfitRequest(Request $request)
    {
        $rows = $request->input('data', $request->input('rows', $request->input('txns')));

        if (isset($rows)) {
            $request->merge(['data' => $rows]);
        }
    }

    private function validateDividendProfitPayload(Request $request, $dataRule)
    {
        return $this->validateMe($request, [
            'startdate' => 'nullable|date_format:Y-m-d',
            'enddate' => 'nullable|date_format:Y-m-d',
            'prodcode' => 'nullable|max:10',
            'dividendamount' => 'nullable|numeric',
            'summary' => 'nullable|in:0,1',
            'zeroignore' => 'nullable|in:0,1',
            'data' => $dataRule . '|array',
            'data.*.no' => 'nullable|numeric',
            'data.*.name' => 'nullable|max:200',
            'data.*.id1' => 'nullable|max:50',
            'data.*.custno' => 'nullable|max:20',
            'data.*.txndate' => 'nullable|date_format:Y-m-d',
            'data.*.acntno' => 'nullable|max:20',
            'data.*.startbal' => 'nullable|numeric',
            'data.*.addamount' => 'nullable|numeric',
            'data.*.adddate' => 'nullable|date_format:Y-m-d',
            'data.*.minusamount' => 'nullable|numeric',
            'data.*.minusdate' => 'nullable|date_format:Y-m-d',
            'data.*.endbal' => 'nullable|numeric',
            'data.*.weight' => 'nullable|numeric',
            'data.*.calc_balance' => 'nullable|numeric',
            'data.*.days' => 'nullable|numeric',
            'data.*.day_amount' => 'nullable|numeric',
            'data.*.day_weight' => 'nullable|numeric',
            'data.*.div_amount' => 'nullable|numeric',
            'data.*.div_percent' => 'nullable|numeric',
            'data.*.rate' => 'nullable|numeric',
            'data.*.dividend' => 'nullable|numeric',
            'data.*.taxamount' => 'nullable|numeric',
            'data.*.netamount' => 'nullable|numeric',
            'data.*.recievemethod' => 'nullable|max:30',
            'data.*.recieve_acntno' => 'nullable|max:20',
            'data.*.bank_acntno' => 'nullable|max:50',
        ]);
    }

    private function saveDividendProfit(array $validated)
    {
        $instid = (int) auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = new Carbon(CoreService::getTxnDate($instid));
        $this->checkCreditUnion($instid);
        $prodcode = $this->resolveEquityProdcode($validated['prodcode'] ?? null, $instid);
        $startdate = $validated['startdate']
            ?? $txndate->copy()->subYears(2)->endOfYear()->format('Y-m-d');
        $enddate = $validated['enddate']
            ?? $txndate->copy()->subYear()->endOfYear()->format('Y-m-d');
        $this->assertDividendBatchEditable($instid, $startdate, $enddate, $prodcode);

        try {
            DB::beginTransaction();

            AdDividentProfit::where('instid', $instid)
                ->where('startdate', $startdate)
                ->where('enddate', $enddate)
                ->where('prodcode', $prodcode)
                ->where('statusid', 1)
                ->update([
                    'statusid' => -1,
                    'updated_by' => $userid,
                ]);

            $profit = AdDividentProfit::create([
                'startdate' => $startdate,
                'enddate' => $enddate,
                'prodcode' => $prodcode,
                'dividendamount' => $validated['dividendamount'] ?? 0,
                'summary' => $validated['summary'] ?? 0,
                'zeroignore' => $validated['zeroignore'] ?? 0,
                'process_statusid' => 0,
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => $userid,
                'updated_by' => $userid,
            ]);

            foreach (($validated['data'] ?? []) as $index => $row) {
                AdDividentProfitDetail::create([
                    'profit_id' => $profit->id,
                    'rowno' => $index + 1,
                    'no' => $row['no'] ?? null,
                    'name' => $row['name'] ?? null,
                    'id1' => $row['id1'] ?? null,
                    'custno' => $row['custno'] ?? null,
                    'txndate' => $row['txndate'] ?? null,
                    'acntno' => $row['acntno'] ?? null,
                    'startbal' => $row['startbal'] ?? null,
                    'addamount' => $row['addamount'] ?? null,
                    'adddate' => $row['adddate'] ?? null,
                    'minusamount' => $row['minusamount'] ?? null,
                    'minusdate' => $row['minusdate'] ?? null,
                    'endbal' => $row['endbal'] ?? null,
                    'weight' => $row['weight'] ?? null,
                    'calc_balance' => $row['calc_balance'] ?? null,
                    'days' => $row['days'] ?? null,
                    'day_amount' => $row['day_amount'] ?? null,
                    'day_weight' => $row['day_weight'] ?? null,
                    'div_amount' => $row['div_amount'] ?? null,
                    'div_percent' => $row['div_percent'] ?? null,
                    'rate' => $row['rate'] ?? null,
                    'dividend' => $row['dividend'] ?? null,
                    'taxamount' => $row['taxamount'] ?? null,
                    'netamount' => $row['netamount'] ?? null,
                    'recievemethod' => $row['recievemethod'] ?? null,
                    'recieve_acntno' => $row['recieve_acntno'] ?? null,
                    'bank_acntno' => $row['bank_acntno'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $profit;
    }

    private function assertDividendBatchEditable($instid, $startdate, $enddate, $prodcode)
    {
        $completedEquity = AdDividentEquityChange::where('instid', $instid)
            ->where('startdate', $startdate)
            ->where('enddate', $enddate)
            ->where('prodcode', $prodcode)
            ->where('statusid', 1)
            ->where('process_statusid', 1)
            ->exists();

        $completedProfit = AdDividentProfit::where('instid', $instid)
            ->where('startdate', $startdate)
            ->where('enddate', $enddate)
            ->where('prodcode', $prodcode)
            ->where('statusid', 1)
            ->where('process_statusid', 1)
            ->exists();

        if ($completedEquity || $completedProfit) {
            $this->error('RC000010', ['field' => 'completed dividend calculation']);
        }
    }

    private function distributeDividendProfit(AdDividentProfit $profit)
    {
        // Journal posting should be wired here once dividend payable/tax account mapping is configured.
        $total = AdDividentProfitDetail::where('profit_id', $profit->id)
            ->selectRaw('count(*) as row_count, coalesce(sum(netamount), 0) as netamount')
            ->first();

        if (!$total || (int) $total->row_count === 0) {
            $this->error('RC000011');
        }

        if ((float) $total->netamount <= 0) {
            $this->error('RC000010', ['field' => 'netamount']);
        }

        return [
            'row_count' => (int) $total->row_count,
            'netamount' => round((float) $total->netamount, 2),
        ];
    }

    private function processDividendProfitDetailTxn($fromAcntno, $txndesc, AdDividentProfitDetail $detail)
    {
        $data = [
            'acntcurcode' => 'MNT',
            'acntno' => $fromAcntno,
            'contacntcurcode' => 'MNT',
            'contcurcode' => 'MNT',
            'contacntno' => $detail->recieve_acntno,
            'curcode' => 'MNT',
            'ispreview' => 0,
            'rate' => 1,
            'rtypecode' => '1',
            'txnamount' => $detail->netamount,
            'txndesc' => $txndesc,
        ];

        $service = new IaTxnService();
        $txnParam = $service->setParamToJrnEntity($data);

        if ($detail->recievemethod === 'bankaccount') {
            return $service->doInternalToInternal($txnParam);
        }

        if (in_array($detail->recievemethod, ['payloan', 'equityadd', 'savingsadd'])) {
            return $service->doInternalToDeposit($txnParam);
        }

        $this->error('RC000010', ['field' => 'recievemethod']);
    }

    private function getGridDataWithoutPaging($request, $query, $defaultOrderQuery = [], $mandotryFilters = [], $mandatoryAnyFields = [])
    {
        $validated = $this->validate($request, [
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
            'orders' => 'nullable|array',
            'orders.*.field' => 'required|max:60',
            'orders.*.dir' => 'nullable|max:5',
            'perPage' => 'nullable|numeric',
            'page' => 'nullable|numeric',
            'functions' => 'nullable|array',
            'functions.*.field' => 'required|max:60',
            'functions.*.cond' => 'required|max:60|in:max,min,avg,sum',
        ], [
            'filters.array' => 'VC000010',
            'filters.*.field.required' => 'VC000010',
            'filters.*.value.max' => 'VC000010',
            'filters.*.cond.max' => 'VC000010',
            'orders.array' => 'VC000011',
            'orders.*.field.required' => 'VC000011',
            'orders.*.field.max' => 'VC000011',
            'orders.*.dir.max' => 'VC000011',
            'perPage.numeric' => 'VC000012',
            'page.numeric' => 'VC000012',
            'functions.array' => ResponseCodeEnum::array,
            'functions.*.field.max' => ResponseCodeEnum::max,
            'functions.*.cond.max' => ResponseCodeEnum::max,
        ]);

        if (empty($validated['orders'])) {
            $validated['orders'] = $defaultOrderQuery;
        }

        $data = $this->applyFilters($query, @$validated['filters'], $mandotryFilters, $mandatoryAnyFields);
        $functiondatas = null;

        if (isset($request['functions']) && count($request['functions']) > 0) {
            $querySql = str_replace(array('?'), array('\'%s\''), $data->toSql());
            $querySql = vsprintf($querySql, $data->getBindings());
            $fieldquery = "";

            foreach ($request['functions'] as $key => $function) {
                $quote = "";
                if (!empty($fieldquery)) {
                    $quote = ",";
                }
                $fieldquery = $fieldquery . $quote . Str::upper($function['cond']) . "(" . $function['field'] . ") AS "
                    . $function['cond'] . "_" . $function['field'];
            }

            $tmpquery = "SELECT $fieldquery FROM ($querySql) a";
            $functiondatas = DB::select($tmpquery);
        }

        $data = $this->applyOrders($data, @$validated['orders'])->get();

        if (!empty($functiondatas)) {
            $result = [
                'data' => $data,
                'functions' => []
            ];

            foreach ($functiondatas[0] as $key => $functiondata) {
                $result['functions'][$key] = $functiondata ?? 0;
            }

            return $result;
        }

        return $data;
    }

    private function resolveEquityProdcode($validatedProdcode, $instid)
    {
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'prodEquity')->first();
        $equityProdcode = $gp->itemvalue ?? null;

        if (
            is_string($equityProdcode)
            && strtolower(trim($equityProdcode)) === 'none'
        ) {
            $equityProdcode = null;
        }

        if (
            is_string($validatedProdcode)
            && strtolower(trim($validatedProdcode)) === 'none'
        ) {
            $validatedProdcode = null;
        }

        if (empty($equityProdcode) && empty($validatedProdcode)) {
            $this->error("RC000198");
        }

        return $validatedProdcode ?: $equityProdcode;
    }

    private function checkCreditUnion($instid)
    {
        $inst = GPInstList::where('id', $instid)->first();
        if ($inst->inst_typeid != "04") {
            $this->error("RC000090");
        }
    }

    private function formatDividendNumbers($data)
    {
        $formatter = function ($item) {
            foreach (['startbal', 'addamount', 'minusamount', 'endbal', 'weight'] as $field) {
                if (isset($item->{$field}) && is_numeric($item->{$field})) {
                    $item->{$field} = round((float) $item->{$field}, $field == 'weight' ? 5 : 2);
                }
            }

            if (($item->rowno ?? 1) > 1) {
                foreach (['name', 'id1', 'txndate', 'startbal', 'endbal', 'weight'] as $field) {
                    $item->{$field} = null;
                }
            }

            unset($item->sortname, $item->rowno);

            return $item;
        };

        if (is_array($data) && isset($data['data'])) {
            if (method_exists($data['data'], 'setCollection')) {
                $data['data']->setCollection($data['data']->getCollection()->map($formatter));
            } else {
                $data['data'] = $data['data']->map($formatter);
            }

            return $data;
        }

        if (!method_exists($data, 'setCollection')) {
            return $data->map($formatter);
        }

        $data->setCollection($data->getCollection()->map($formatter));

        return $data;
    }

    private function formatDividendProfitNumbers($data)
    {
        $formatter = function ($item) {
            $scales = [
                'startbal' => 2,
                'addamount' => 2,
                'minusamount' => 2,
                'endbal' => 2,
                'weight' => 5,
                'calc_balance' => 2,
                'day_amount' => 2,
                'day_weight' => 8,
                'div_amount' => 2,
                'div_percent' => 5,
                'rate' => 3,
                'dividend' => 2,
                'taxamount' => 2,
                'netamount' => 2,
            ];

            foreach ($scales as $field => $scale) {
                if (isset($item->{$field}) && is_numeric($item->{$field})) {
                    $item->{$field} = round((float) $item->{$field}, $scale);
                }
            }

            if (isset($item->no) && is_numeric($item->no)) {
                $item->no = (int) $item->no;
            }

            if (isset($item->days) && is_numeric($item->days)) {
                $days = (float) $item->days;
                $item->days = floor($days) == $days ? (int) $days : round($days, 2);
            }

            unset($item->sortrow, $item->linetype, $item->lineseq);

            return $item;
        };

        if (is_array($data) && isset($data['data'])) {
            if (method_exists($data['data'], 'setCollection')) {
                $data['data']->setCollection($data['data']->getCollection()->map($formatter));
            } else {
                $data['data'] = $data['data']->map($formatter);
            }

            return $data;
        }

        if (!method_exists($data, 'setCollection')) {
            return $data->map($formatter);
        }

        $data->setCollection($data->getCollection()->map($formatter));

        return $data;
    }
}
