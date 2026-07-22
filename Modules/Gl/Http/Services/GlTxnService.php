<?php

namespace Modules\Gl\Http\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gl\Entities\GlAccount;
use Modules\Gl\Entities\GlBalance;
use Modules\Gl\Entities\GlDailyBal;
use Modules\Gl\Entities\GlTransaction;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\FinTxnEntity;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Controllers\TxnCoreController;

class GlTxnService extends TxnCoreController
{

    public function initAcntNew($acntCode)
    {
        try {
            $mainAcnt = GlAccount::where('acntno', $acntCode)
                ->where('instid', $this->getCurInstId())
                ->where('statusid', 1)
                ->first();
            if (empty($mainAcnt)) {
                $this->error('RC000034', ['mainacntno' => $acntCode]);
            }
        } catch (QueryException $ex) {
            $this->error('RC000051');
        }
        return $mainAcnt;
    }

    public function doTxn(TxnJrnlEntity $p, TxnItemEntity $jrItem)
    {
        if ($p->getTxnAmount() == 0) {
            return $jrItem;
        }
        $p->setInstid($this->getCurInstId());
        $glDate = CoreService::getGlDate($p->getInstid());
        $mainAcnt = $this->initAcntNew($p->getTxnAcntCode());
        if (empty($p->getTxndate())) {
            $p->setTxndate($glDate);
        }
        $p->setUserid(auth()->user()->id);
        $p->setCheckrate(0);
        $txndate = new Carbon($p->getTxndate());
        if ($txndate && $glDate && Carbon::parse($txndate)->greaterThan(Carbon::parse($glDate))) {
            $this->error('Ирээдүйн огноогоор гүйлгээ хийх боломжгүй!');
        }

        $changeobalyear = false;
        $changeobalmonthcount = 0;
        if ($txndate->year + 1 == Carbon::parse($glDate)->year) {
            $changeobalyear = true;
        } elseif ($txndate->year == Carbon::parse($glDate)->year) {
            $changeobalyear = false;
        } else {
            $this->error('1-с дээш жилийн өмнөх огноогоор гүйлгээ хийх боломжгүй!');
        }

        $p = $this->updateJrnlEntry($p, $mainAcnt, null, null, null);
        $p = $this->validateEntry($p, false, false, false, false);
        $changefield = 'dt';
        $p->setTxnAmount(round($p->getTxnAmount(), 2));

        if ($p->getTxnAmount() == 0) {
            return $jrItem;
        }
        if ($p->getTxnAmount() < 0) {
            $changefield = 'ct';
        }
        $day = $txndate->day;
        $month = $txndate->month;
        if ($day < 10) {
            $day = '0' . $day;
        }
        if ($month < 10) {
            $month = '0' . $month;
        }

        $gbbal = GlBalance::where('account', $p->getTxnAcntCode())
            ->where('branch', $p->getAcntbrchno())
            ->where('unit', '0000')
            ->where('currency', $p->getCurCode())
            ->where('year', $txndate->year)
            ->where('instid', $p->getInstid())->first();
        if (empty($gbbal)) {

            $beforeobal = GlBalance::select(
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
                trunc(COALESCE(dt13, 0), 2) + trunc(COALESCE(ct13, 0), 2) AS totalsum')
            )
                ->where('account', $p->getTxnAcntCode())
                ->where('branch', $p->getAcntbrchno())
                ->where('unit', '0000')
                ->where('currency', $p->getCurCode())
                ->where('year', $txndate->year - 1)
                ->where('instid', $p->getInstid())->first();

            GlBalance::create([
                'account' => $p->getTxnAcntCode(),
                'branch' => $p->getAcntbrchno(),
                'unit' => '0000',
                'currency' => $p->getCurCode(),
                'year' => $txndate->year,
                'instid' => $p->getInstid(),
                'obal' => empty($beforeobal) ? 0 : customTruncate($beforeobal->totalsum, 2),
                ($changefield . $month) => $p->getTxnAmount(),
                'updated_by' => $p->getUserid(),
                'created_by' => $p->getUserid(),
            ]);
            if ($changeobalyear) {
                $gbbalobal = GlBalance::where('account', $p->getTxnAcntCode())
                    ->where('branch', $p->getAcntbrchno())
                    ->where('unit', '0000')
                    ->where('currency', $p->getCurCode())
                    ->where('year', Carbon::parse($glDate)->year)
                    ->where('instid', $p->getInstid())->first();
                if (empty($gbbalobal)) {

                    $beforeobal1 = GlBalance::select(
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
                    trunc(COALESCE(dt13, 0), 2) + trunc(COALESCE(ct13, 0), 2) AS totalsum')
                    )
                        ->where('account', $p->getTxnAcntCode())
                        ->where('branch', $p->getAcntbrchno())
                        ->where('unit', '0000')
                        ->where('currency', $p->getCurCode())
                        ->where('year',  Carbon::parse($glDate)->year - 1)
                        ->where('instid', $p->getInstid())->first();

                    GlBalance::create([
                        'account' => $p->getTxnAcntCode(),
                        'branch' => $p->getAcntbrchno(),
                        'unit' => '0000',
                        'currency' => $p->getCurCode(),
                        'year' =>  Carbon::parse($glDate)->year,
                        'instid' => $p->getInstid(),
                        'obal' => empty($beforeobal1) ? 0 : (customTruncate($beforeobal1->totalsum, 2)),
                        'updated_by' => $p->getUserid(),
                        'created_by' => $p->getUserid(),
                    ]);
                } else {
                    GlBalance::where('account', $p->getTxnAcntCode())
                        ->where('branch', $p->getAcntbrchno())
                        ->where('unit', '0000')
                        ->where('currency', $p->getCurCode())
                        ->where('year', Carbon::parse($glDate)->year)
                        ->where('instid', $p->getInstid())
                        ->update([
                            'obal' => DB::raw('trunc(obal + (' . $p->getTxnAmount() . '), 2)'),
                            'updated_by' => $p->getUserid()
                        ]);
                }
            }
            if (!empty($beforeobal) && round($beforeobal->totalsum, 2) != 0) {
                $p1 = new FinTxnEntity();
                $jritem1 = new TxnItemEntity();
                $p1->setJrno("BB" . CoreService::getGlNextJrno());
                $p1->setTxndate($p->getTxndate());
                $p1->setAcntbrchno($p->getAcntbrchno());
                $p1->setCurCode($p->getCurCode());
                $p1->setTxnAcntCode($p->getTxnAcntCode());
                $p1->setTxnAmount(customTruncate($beforeobal->totalsum, 2));
                $p1->setTxnDesc('Оны эхлэл үлдэгдэл суулгав');
                $p1->setCorr($p->getCorr());
                $p1->setPostdate($p->getPostdate());
                $p1->setInstid($p->getInstid());
                $p1->setIsCloseBalance($p->getIsCloseBalance());
                $this->insertGlTxnDb($p1, $jritem1);
            }
        } else {
            GlBalance::where('account', $p->getTxnAcntCode())
                ->where('branch', $p->getAcntbrchno())
                ->where('unit', '0000')
                ->where('currency', $p->getCurCode())
                ->where('year', $txndate->year)
                ->where('instid', $p->getInstid())
                ->update([
                    ($changefield . $month) => DB::raw('trunc('.($changefield . $month) . ' + (' . $p->getTxnAmount() . '), 2 )'),
                    'updated_by' => $p->getUserid()
                ]);
            if ($changeobalyear) {
                GlBalance::where('account', $p->getTxnAcntCode())
                    ->where('branch', $p->getAcntbrchno())
                    ->where('unit', '0000')
                    ->where('currency', $p->getCurCode())
                    ->where('year', Carbon::parse($glDate)->year)
                    ->where('instid', $p->getInstid())
                    ->update([
                        'obal' => DB::raw('trunc(obal + (' . $p->getTxnAmount() . '), 2)'),
                        'updated_by' => $p->getUserid()
                    ]);
            }
        }


        $gldailybal = GlDailyBal::where('account', $p->getTxnAcntCode())
            ->where('branch', $p->getAcntbrchno())
            ->where('unit', '0000')
            ->where('currency', $p->getCurCode())
            ->where('year', $txndate->year)
            ->where('period', $txndate->month)
            ->where('instid', $p->getInstid())->first();
        if (empty($gldailybal)) {
            $sumfield = 'obal ';
            for ($i = 0; $i < 31; $i++) {
                $monthind = $i + 1;
                if ($monthind < 10) {
                    $monthind = '0' . $monthind;
                }
                $sumfield = $sumfield . " + trunc(COALESCE (dt$monthind, 0), 2) + trunc(COALESCE (ct$monthind, 0), 2) ";
            }
            $beforemonth = $txndate->month;
            $beforeyear = $txndate->year;
            if ($txndate->month == 1) {
                $beforemonth = 12;
                $beforeyear = $beforeyear - 1;
            } else {
                $beforemonth = $beforemonth - 1;
            }
            $gldailynextbal = GlDailyBal::select(
                DB::raw($sumfield . ' AS sumtotal')
            )
                ->where('account', $p->getTxnAcntCode())
                ->where('branch', $p->getAcntbrchno())
                ->where('unit', '0000')
                ->where('currency', $p->getCurCode())
                ->where('year', $beforeyear)
                ->where('period', $beforemonth)
                ->where('instid', $p->getInstid())->first();

            GlDailyBal::create([
                'account' => $p->getTxnAcntCode(),
                'branch' => $p->getAcntbrchno(),
                'unit' => '0000',
                'currency' => $p->getCurCode(),
                'year' => $txndate->year,
                'period' => $txndate->month,
                'instid' => $p->getInstid(),
                'obal' => empty($gldailynextbal) ? 0 : customTruncate($gldailynextbal->sumtotal, 2),
                ($changefield . $day) => $p->getTxnAmount(),
                'updated_by' => $p->getUserid(),
                'created_by' => $p->getUserid(),
            ]);
        } else {
            GlDailyBal::where('account', $p->getTxnAcntCode())
                ->where('branch', $p->getAcntbrchno())
                ->where('unit', '0000')
                ->where('currency', $p->getCurCode())
                ->where('year', $txndate->year)
                ->where('period', $txndate->month)
                ->where('instid', $p->getInstid())->update([
                    ($changefield . $day) => DB::raw('trunc(' . ($changefield . $day) . ' + (' . $p->getTxnAmount() . ') ,2)'),
                    'updated_by' => $p->getUserid()
                ]);
        }

        $diffmonths = Carbon::parse($glDate)->diffInMonths($txndate) + 5;
        for ($ii = 1; $ii <= $diffmonths; $ii++) {
            $changedate = Carbon::parse($txndate)->addMonths($ii);
            $beforemonthdate = $changedate->copy()->subMonth();
            if (
                $changedate->year > Carbon::parse($glDate)->year
                || ($changedate->year == Carbon::parse($glDate)->year
                    && $changedate->month > Carbon::parse($glDate)->month)
            ) {
                break;
            }
            $gldailybal = GlDailyBal::where('account', $p->getTxnAcntCode())
                ->where('branch', $p->getAcntbrchno())
                ->where('unit', '0000')
                ->where('currency', $p->getCurCode())
                ->where('year', $changedate->year)
                ->where('period', $changedate->month)
                ->where('instid', $p->getInstid())->first();

            if (empty($gldailybal)) {
                $sumfield = 'obal ';
                for ($i = 0; $i < 31; $i++) {
                    $monthind = $i + 1;
                    if ($monthind < 10) {
                        $monthind = '0' . $monthind;
                    }
                    $sumfield = $sumfield . " + trunc(COALESCE (dt$monthind, 0), 2) + trunc(COALESCE (ct$monthind, 0), 2) ";
                }
                $beforemonth = $beforemonthdate->month;
                $beforeyear = $beforemonthdate->year;

                $gldailynextbal = GlDailyBal::select(
                    DB::raw($sumfield . ' AS sumtotal')
                )
                    ->where('account', $p->getTxnAcntCode())
                    ->where('branch', $p->getAcntbrchno())
                    ->where('unit', '0000')
                    ->where('currency', $p->getCurCode())
                    ->where('year', $beforeyear)
                    ->where('period', $beforemonth)
                    ->where('instid', $p->getInstid())->first();

                GlDailyBal::create([
                    'account' => $p->getTxnAcntCode(),
                    'branch' => $p->getAcntbrchno(),
                    'unit' => '0000',
                    'currency' => $p->getCurCode(),
                    'year' => $changedate->year,
                    'period' => $changedate->month,
                    'instid' => $p->getInstid(),
                    'obal' => empty($gldailynextbal) ? 0 : customTruncate($gldailynextbal->sumtotal, 2),
                    // ($changefield . $day) => $p->getTxnAmount(),
                    'updated_by' => $p->getUserid(),
                    'created_by' => $p->getUserid(),
                ]);
            } else {
                GlDailyBal::where('account', $p->getTxnAcntCode())
                    ->where('branch', $p->getAcntbrchno())
                    ->where('unit', '0000')
                    ->where('currency', $p->getCurCode())
                    ->where('year', $changedate->year)
                    ->where('period', $changedate->month)
                    ->where('instid', $p->getInstid())->update([
                        'obal' => DB::raw('trunc(obal + ' . $p->getTxnAmount().', 2)'),
                        // ($changefield . $day) => DB::raw(($changefield . $day) . ' + (' . $p->getTxnAmount() . ')'),
                        'updated_by' => $p->getUserid()
                    ]);
            }
        }

        // dd($p);
        $jrItem = $this->insertGlTxn($p->getFinTxnEntry('MAIN'), $jrItem);
        return $jrItem;
    }

    public function insertGlTxn(FinTxnEntity $p, TxnItemEntity $jrItem)
    {
        $jrItem = $this->insertGlTxnDb($p, $jrItem);
        return $jrItem;
    }

    public function insertGlTxnDb(FinTxnEntity $p, TxnItemEntity $jrItem)
    {
        $jrItem->setTxnpreview($p);
        if ($p->getIsPreview() != 1) {
            $txndate = new Carbon($p->getTxndate());
            GlTransaction::create([
                'journal' => $p->getJrno(),
                'entry' => $jrItem->getJritemno(),
                'year' => $txndate->year,
                'period' => $txndate->month,
                'day' => $txndate->day,
                'branch' => $p->getAcntbrchno(),
                'unit' => '0000',
                'currency' => $p->getCurCode(),
                'account' => $p->getTxnAcntCode(),
                'amount' => $p->getTxnAmount(),
                'description' => $p->getTxnDesc(),
                'correctoin' => $p->getCorr(),
                'statusid' => 1,
                'postdate' => $p->getPostdate(),
                'tellerno' => $this->getCurUserId(),
                'txndate' => $p->getTxndate(),
                'isclosebalance' => empty($p->getIsCloseBalance()) ? 0 : $p->getIsCloseBalance(),
                'instid' => $p->getInstid(),
                'created_by' => $this->getCurUserId(),
                'updated_by' => $this->getCurUserId(),
            ]);
        }
        // $jrItem->addMainjritemno(AccountTypeEnum::gl);
        $jrItem->setJritemno($jrItem->getJritemno() + 1);
        return $jrItem;
    }
}
