<?php

namespace Modules\Ad\Http\Controllers\Eod;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Dp\Entities\DpAccount;
use Modules\Dp\Entities\DpAccountType;
use Modules\Gp\Entities\GPInstFreqFeeJob;
use Modules\Gp\Entities\GPInstFeeType;
use Modules\Gp\Entities\GPInstFeeTypeSource;
use Modules\Gp\Entities\GPInstTxnType;
use Modules\Gp\Http\Services\CoreService;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Ad\Http\Services\DpEodService;
use Modules\Ca\Entities\CaCashList;
use Modules\Dp\Entities\DpAccountHist;
use Modules\Dp\Entities\DpAccountTypeFee;
use Modules\Dp\Entities\DpInvAccount;
use Modules\Dp\Entities\DpInvNrs;
use Modules\Dp\Entities\DpInvPackage;
use Modules\Dp\Entities\DpRollTemp;
use Modules\Gp\Entities\GPInstFeeTypeCur;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Enums\EodContinueResponseCodesEnum;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Controllers\TxnCoreController;
use Modules\Tr\Http\Services\DpHoldTxnService;
use Modules\Tr\Http\Services\DpTxnService;

class DpEodController extends CoreController
{

    /**
     * Идэвхгүй дансыг зогсоосон төлөвт оруулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800002($step)
    {

        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $results = DB::table('dp_account as da')
            ->leftJoin('dp_account_type as dt', function ($join) {
                $join->on('dt.instid', '=', 'da.instid')
                    ->on('dt.prodcode', '=', 'da.prodcode');
            })
            ->whereRaw("lasttellertxndate + (dormancestop || ' days')::INTERVAL < ?", [$txndate])
            ->where('dormancestop', '!=', 0)
            ->where('da.statusid', 5)
            ->select('da.acntno')
            ->get();

        foreach ($results as $key => $data) {
            DpAccount::where('instid', $instid)->where('acntno', $data->acntno)
                ->where('statusid', 5)->update([
                    'statusid' => 2,
                    'updated_by' => $userid
                ]);
        }

        $step->allcount = count($results);
        $step->succount = $step->allcount;
        return [
            'status' => 200
        ];
    }

    /**
     * Хаалтын Автомат шимтгэл
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800003($step)
    {

        $instid = auth()->user()->instid;
        $txndate = Carbon::parse(CoreService::getEodSysdate($instid));
        // $txndate->copy()->endOfMonth()->isSameDay($txndate);
        // $txndate = Carbon::parse('2023-12-31');
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800003',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        if ($txndate->copy()->endOfMonth()->isSameDay($txndate)) {
            $execfreq = ['M'];
            if ($txndate->copy()->endOfQuarter()->isSameDay($txndate)) {
                $execfreq[] = 'Q';
            }
            if ($txndate->copy()->endOfYear()->isSameDay($txndate)) {
                $execfreq[] = 'Y';
            }
            if (
                $txndate->copy()->endOfYear()->isSameDay($txndate)
                || ($txndate->day == 30 && $txndate->month == 6)
            ) {
                $execfreq[] = 'B';
            }
            // Log::debug($execfreq);
            $proctype = 'B';
            if ($txndate->copy()->endOfMonth()->isSameDay(Carbon::parse($step->eoddate))) {
                $proctype = 'E';
            }
            // Хаалтын авто шимтгэл
            $freqfees = GPInstFreqFeeJob::where('instid', $instid)
                ->whereIn('execfreq', $execfreq)
                ->where('proctype', $proctype)
                ->where('statusid', 1)
                ->get();
            $tmpFeeCodes = [];
            $rtypecodes = [];
            foreach ($freqfees as $freqfee) {
                $tmpFeeCodes[] = $freqfee->feecode;
                $rtypecodes[$freqfee->feecode] = $freqfee->rtypecode;
            }
            // Шимтгэл дээрх эх сурвалж таарч байгаа эсэхийг харах
            $GP_sources = GPInstFeeTypeSource::select('feecode')
                ->where('sourcecode', 1)
                ->whereIn('feecode', $tmpFeeCodes)
                ->where('instid', $instid)
                ->where('statusid', 1)->get();

            if (count($GP_sources) > 0) {
                $tmpFeeCodes = [];
                foreach ($GP_sources as $GP_source) {
                    $tmpFeeCodes[] = $GP_source->feecode;
                }
                $lastitem = $this->getLastEodStep($step);
                // Байгууллага дээр бүртгэлтэй шимтгэлүүдийг авах
                $feeInfos = GPInstFeeType::where('feecode', $tmpFeeCodes)
                    ->where('instid', $instid)
                    ->where('statusid', 1)->get();
                // Автомат шимтгэл авах данс
                $query = DpAccount::where('instid', $instid)
                    ->whereIn('prodcode', function ($query) use ($instid) {
                        $query->select('prodcode')
                            ->from(with(new DpAccountType)->getTable())
                            ->where('statusid', 1)
                            ->where('procflag', '!=', 'T')
                            ->where('instid', $instid);
                    })
                    ->whereIn('statusid', [1, 4]);

                if ($lastitem && $lastitem->acntno) {
                    $query = $query->where('acntno', '>=', $lastitem->acntno);
                }
                $accounts = $query->orderBy('acntno', 'ASC')->get();
                // Касс данс авах

                if (!$lastitem) {
                    $step->allcount = count($accounts);
                }

                $cashAcnt = CaCashList::where('userid', auth()->user()->id)
                    ->where('instid', $instid)
                    ->where('statusid', 1)->first();
                if (empty($cashAcnt)) {
                    $this->error('RC000039');
                }
                $trService = new TxnCoreController();
                foreach ($accounts as $racnt) {
                    try {
                        $eodlogs['acntno'] = $racnt->acntno;
                        $eodlogs['acntbrchno'] = $racnt->brchno;
                        $eodlogs['errtype'] = null;
                        $fee = [];
                        $actno = $racnt->acntno;
                        foreach ($feeInfos as $feeInfo) {
                            $checkFeeRange = false;
                            $feetxnType = GPInstTxnType::where('ACTION_CODE', $feeInfo->txncode)
                                ->where('instid', $instid)
                                ->where('statusid', 1)->first();
                            $feeamount = 0;
                            $curcode = '';
                            $prodcode = '';
                            $acntmod = '';
                            $contacntno = $feetxnType->acntno2;

                            if ($feetxnType->acnttype2 == '00') {
                                $contacntno = $cashAcnt->acntcode;
                                $feetxnType->acnttype2 = AccountTypeEnum::gl;
                            } else if ($feetxnType->acnttype2 == 'SP') {
                                $account = $trService->getSuspAcntno($instid, $feetxnType->acntno2);
                                if (!empty($account)) {
                                    $contacntno = $account->acntno;
                                    $feetxnType->acnttype2 = Str::upper($account->acnttype);
                                }
                            }
                            $feetxnType->acnttype1 = AccountTypeEnum::dp;
                            $curcode = $racnt->curcode;
                            $prodcode = $racnt->prodcode;
                            $acntmod = AccountTypeEnum::dp;

                            $productFee = null;
                            // process code болон Бүтээгдэхүүн дээрх шимтгэлүүдийг авах
                            switch ($acntmod) {
                                case AccountTypeEnum::dp:
                                    $productFee = DpAccountTypeFee::where('prodcode', $prodcode)
                                        ->where('feecode', $feeInfo->feecode)
                                        ->where('statusid', 1)
                                        ->where('instid', $instid)->first();
                                    break;
                                case AccountTypeEnum::ln:
                                    // Зээлийн гүйлгээ хийгдсэний дараа энд шимтгэлээ олж авчирна.
                                    break;
                                default:
                                    # code...
                                    break;
                            }
                            if (empty($productFee)) {
                                break;
                            }

                            $GPinstfeecur = GPInstFeeTypeCur::where('feecode', $feeInfo->feecode)
                                ->where(function ($query) use ($curcode) {
                                    $query->where('curcode', '=', $curcode)
                                        ->orWhereNull('curcode');
                                })
                                ->where('instid', $instid)
                                ->where('statusid', 1)
                                ->orderBy('curcode', 'ASC')->first();

                            if (empty($GPinstfeecur)) {
                                break;
                            }
                            if (empty($GPinstfeecur->curcode)) {
                                $GPinstfeecur->curcode = $feeInfo->curcode;
                            }

                            switch ($GPinstfeecur->calcmeth) {
                                case 2:
                                    $feeamount = $GPinstfeecur->flatrate;
                                    break;
                                case 4:
                                    $feeamount = 0;
                                    break;
                                default:
                                    # code...
                                    break;
                            }

                            if (nullOrZero($feeamount)) {
                                break;
                            }

                            $fee[] = [
                                // minfee, maxfee шалгах эсэх
                                'checkfeerange' => $checkFeeRange,
                                'calcmeth' => $GPinstfeecur->calcmeth,
                                // Шимтгэлийн дүн эсвэл хувиар байна.
                                'contamount' => $feeamount,
                                'feecode' => $GPinstfeecur->feecode,
                                'rtypecode' => $rtypecodes[$GPinstfeecur->feecode],
                                'txncode' => $feeInfo->txncode,
                                'curcode' => $curcode,
                                'contcurcode' => $GPinstfeecur->curcode,
                                // Шимтгэлийг аль салбарт тооцох эсэх
                                'brchapply' => $feeInfo->brchapply,
                                // Суутгах арга
                                'collmeth' => $feeInfo->collmeth,
                                // Шимтгэлийн төрөл
                                'feetype' => $feeInfo->feetype,
                                'txndesc' => $feeInfo->name,

                                'acnttype' => $feetxnType->acnttype1,
                                'contacnttype' => $feetxnType->acnttype2,
                                'acntno' => $actno,
                                'contacntno' => $contacntno,
                                'debittxnamount' => 0
                            ];
                        }

                        if (count($fee) > 0) {
                            $p = new TxnJrnlEntity();
                            $p->setSourcecode(1);
                            $p->setAcntbrchno($racnt->brchno);
                            $p->setFeeInfos($fee);
                            $p->setContAcntbrchno(auth()->user()->brchno);
                            $jrItem = new TxnItemEntity();
                            $trService->doFeeTxn($p, $jrItem);
                        }
                        $step->succount = $step->succount + 1;
                    } catch (MeException $ex) {
                        $eodlogs['errtype'] = 'A';
                        $eodlogs['errdesc'] = $ex->getMessage();
                        if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                            $eodlogs['errtype'] = 'D';
                            throw new Exception($ex->getMessage());
                        }
                    } catch (\Throwable $th) {
                        $eodlogs['errtype'] = 'D';
                        $eodlogs['errdesc'] = $th->getMessage();
                        throw $th;
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
        }
    }

    /**
     * Идэвхтэй хадгаламжийн бүтээгдэхүүн солих
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800006($step) {}


    /**
     * Ашиглаагүй улайлтын эрхийн өөрчлөлтийг балансын гадуурх дансанд хийх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800009($step) {}

    /**
     * Дебит хүүг балансын гадуур автомат гаргах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800011($step) {}

    /**
     * Дундаж үлдэгдлээс тооцдог дансны үлдэгдэл хуримтлуулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800018($step)
    {
        // Бараг л ашиглагдахгүй гэсэн
    }

    /**
     * Депозит кредит хүүний хувь зөв эсэхийг шалгах (бүтээгдэхүүний түвшинд тодорхойлогдсон)
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800019($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $datas = $dpservice->defineDPCrIntRate(CoreService::getEodSysdate($instid), $lastitem, $instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800019',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                DpAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'crintrate' => $data->intratenew
                    ]);
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Депозит кредит хүүний хувь зөв эсэхийг шалгах (дансны түвшинд тодорхойлогдсон)
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800020($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $datas = $dpservice->defineDPCrIntRateAcnt(CoreService::getEodSysdate($instid), $lastitem, $instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800020',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                DpAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'crintrate' => $data->intratenew
                    ]);
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Депозит кредит өдрийн хүү тооцоолох
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800022($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800022',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $dpservice->calcDPCrDailyInt($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                DpAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        // 'crdailyint' => $data->newacr + $data->crroundint,
                        'crint2acr' => $data->newacr,
                        // 'crroundint' =>  $data->newacr + $data->crroundint - ($data->newacr + $data->crroundint),
                        'drdailyint' => 0,
                        'drint2acr' => $data->procflag == 'C' ? ($data->drint2acr ?? 0) : 0,
                        'drroundint' => 0,
                        'drfinedailyint' => 0,
                        'drfineroundint' =>  0
                    ]);
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Сараар хүү хуримтлуулдаг ба хугацаа нь дуусаж байгаа хадгаламжийн хүү тооцоолох
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800023($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800023',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $dpservice->calcDPCrMOMInt($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                DpAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'crint2acr' => $data->newcrintacr + $data->crroundint,
                        'crroundint' =>  $data->newcrintacr + $data->crroundint - ($data->newcrintacr + $data->crroundint),
                        'totalbalperiod' => 0
                    ]);
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Депозит кредит хүү хуримтлуулах гүйлгээ үүсгэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800028($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800028',
            'instid' => $instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $dpservice->CreateDPCrAcrJrl($txndate, $lastitem, $instid);
        // Log::debug($lastitem);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $service = new DpTxnService();
                $service->loanAccrualPrincipal($data->acntno, 'dp901041', true, $txndate);
                $step->succount = $step->succount + 1;
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                }
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Ашиглахгүй байгаа
     */
    // public function ad800029($step)
    // {
    //     $instid = auth()->user()->instid;
    //     $query = "UPDATE dp_account AS a
    //     SET crint2cap = t.crint2cap + t.crint2acr + coalesce (t.bonus, 0),
    //         crint2acr = 0,
    //         bonus = 0
    //    FROM (SELECT a.crint2cap, a.crint2acr, a.bonus, a.instid, a.acntno
    //            FROM dp_account a
    //                 INNER JOIN dp_account_type p
    //                    ON a.prodcode = p.prodcode AND a.instid = p.instid
    //           WHERE     a.currentbal > 0
    //                 AND a.statusid > 2
    //                 AND a.crint2acr + coalesce (a.bonus, 0) <> 0) t
    //   WHERE a.acntno = t.acntno and a.instid = ?";
    //     DB::statement($query, [$instid]);
    // }

    /**
     * Депозит дансны үлдэгдэл түр хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800052($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800052',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CreateTmpDPBals($txndate, $lastitem, $instid);
        // Log::debug([count($datas), $datas]);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                DpAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'tmp_bal' =>  DB::raw('currentbal'),
                        'tmp_crint2cap' =>  DB::raw('crint2cap + cradjint'),
                        'tmp_statuscode' =>  DB::raw('statusid'),
                        'tmp_prodcode' =>  DB::raw('prodcode'),
                        'tmp_brchno' =>  DB::raw('brchno'),
                        'tmp_crintrate' =>  DB::raw('crintrate'),
                        'tmp_termstartdate' =>  DB::raw('termstartdate'),
                        'tmp_termexpdate' =>  DB::raw('termexpdate'),
                        'tmp_drint2cap' =>  $data->drint2cap,
                        'tmp_drcasint2cap' =>  DB::raw('drcasint2cap'),
                        'tmp_drfine2cap' =>  DB::raw('drfine2cap'),
                        'tmp_drcom2cap' =>  DB::raw('drcom2cap'),
                        'tmp_odclscode' =>  DB::raw('odclscode'),
                        'tmp_drint2acr' =>  $data->drint2acr,
                        'tmp_drcasint2acr' =>  DB::raw('drcasint2acr + drcasintadj'),
                        'tmp_drcom2acr' =>  DB::raw('drcom2acr + drcomadjint'),
                        'tmp_drfine2acr' =>  DB::raw('drfine2acr + drfineadjint'),
                        'tmp_drcasbalance' =>  DB::raw('drcasbalance'),
                        'tmp_taxamount' =>  DB::raw('taxamount'),
                        'tmp_crcaptotal2' =>  DB::raw('crcaptotal2'),
                        'tmp_odclscodetrm' =>  DB::raw('odclscodetrm'),
                        'tmp_odclscodeqlt' =>  DB::raw('odclscodeqlt'),
                        'updated_by' => $userid
                    ]);
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Хугацаа нь дуусах хадгаламжийн хүүний капитализэшн хийх
     * Дансанд өдөр тохируулсан хадгаламжийн хүүний капитализэшн хийх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800066($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new DpEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800066',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->capIntDPOnTerm($txndate, $lastitem, $instid);
        // Log::debug('ad800066');
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $txndesc = "Хугацаат хүү / үр шим кап хийв. (SOD) ";
        $service = new DpTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();
                $p->setTxnAcntCode($data->acntno);
                $p->setCurCode($data->curcode);
                $p->setTxnDesc($txndesc);
                $p->setSourcecode(1);
                $p->setTxnAmount(0);
                $p->setInstid(CoreService::getCurInstId());
                $p->setPostdate(getNow());
                $p->setUserid(CoreService::getCurUserId());
                $p->setTxncode('dp901051');
                $p->setTxndate($txndate);
                $service->doCapInt($p);
                $step->succount = $step->succount + 1;
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                }
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Депозит кредит хүү капитализэшн хийх ad800067-71
     * $runfreq, 'P'- өдөр бүр ажиллана хэрэв хугацааны эцэст гэсэн тохиргоо байвал хийнэ,
     *  'M'- сарын эцэст, 'Q'- улирлын эцэст, 'B'- хагас жилийн эцэст, 'Ү'- жилийн эцэст
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800067($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $dpservice->doCapCrInt($step, $lastitem, 'ad800067', 'P');
    }

    public function ad800068($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $dpservice->doCapCrInt($step, $lastitem, 'ad800068', 'M');
    }

    public function ad800069($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $dpservice->doCapCrInt($step, $lastitem, 'ad800069', 'Q');
    }

    public function ad800070($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $dpservice->doCapCrInt($step, $lastitem, 'ad800070', 'B');
    }

    public function ad800071($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $dpservice->doCapCrInt($step, $lastitem, 'ad800071', 'Y');
    }

    /**
     * Эх үүсвэрийн хуваарийн дагуу хүү капитализэшн хийж төлбөр шилжүүлэх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800072($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $user = auth()->user();
        $txndate = CoreService::getEodSysdate($user->instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800072',
            'instid' => $user->instid,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $datas = $dpservice->capIntDPInvNrs($txndate, $lastitem, $user->instid);
        // Log::debug('ad800072');
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $txndesc = "Эх үүсвэрийн хуваарьт хүү / үр шим кап хийв. (SOD) ";
        $service = new DpTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();
                $p->setTxnAcntCode($data->acntno);
                $p->setCurCode($data->curcode);
                $p->setTxnDesc($txndesc);
                $p->setSourcecode(1);
                $p->setTxnAmount(0);
                $p->setInstid(CoreService::getCurInstId());
                $p->setPostdate(getNow());
                $p->setUserid(CoreService::getCurUserId());
                $p->setTxncode('dp901051');
                $p->setTxndate($txndate);
                if ($data->crint2cap + $data->cradjint > 0.01) {
                    $service->doCapInt($p);
                    $step->succount = $step->succount + 1;
                } else {
                    $checkinv = DpInvAccount::where('instid', $user->instid)
                        ->where('invacntno', $data->acntno)
                        ->where('statusid', 1)->first();
                    if (!empty($checkinv)) {
                        // dp901021
                        $invRecAcnt = DpInvPackage::where('instid', $user->instid)
                            ->where('id', $checkinv->package_id)
                            ->where('statusid', 1)->first();
                        if (empty($invRecAcnt)) {
                            $this->error('RC000031');
                        } else {
                            $invnrs = DpInvNrs::where('instid', $user->instid)
                                ->where('acntno',  $data->acntno)
                                ->where('statusid', 1)
                                ->where('payday', '>=', $p->getTxndate())
                                ->orderBy('payday')->first();
                            if ($invnrs) {
                                if (empty($jrItem)) {
                                    $jrItem = new TxnItemEntity();
                                }
                                $invPayAmount = $checkinv->repaytype == 6
                                    ? round($data->crint2cap + $data->cradjint, 2)
                                    : round($invnrs->payamount, 2);
                                if ($invPayAmount <= 0) {
                                    continue;
                                }
                                $tP = new TxnJrnlEntity();
                                $tP->setTxnAcntCode($p->getTxnAcntCode());
                                $tP->setContAcntCode($invRecAcnt->invsuspacntno);
                                $tP->setTxnAmount($invPayAmount);
                                $tP->setJrno($p->getJrno());
                                $tP->setCurCode($data->curcode);
                                $tP->setInstid($p->getInstid());
                                $tP->setTxnDesc($p->getTxnAcntCode() . ' Эх үүсвэрийн дансны хуваарьт төлбөр шилжүүлэв.');
                                $tP->setParenttxncode('dp901051');
                                $tP->setTxndate($p->getTxndate());
                                $service->doNonCashToDpCreditTxn($tP, $jrItem);
                            }
                        }
                    }
                }
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                }
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Хугацаа нь дуусаж байгаа депозит бүтээгдэхүүний төлвийг өөрчлөх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800101($step)
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800101',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        try {
            $step->allcount = DpAccountType::where('instid', $instid)->where('enddate', $txndate)
                ->update([
                    'updated_by' => $userid,
                    'statusid' => 0
                ]);
            $step->succount = $step->allcount;
        } catch (\Throwable $th) {
            $eodlogs['errtype'] = 'D';
            $eodlogs['errdesc'] = $th->getMessage();
            throw $th;
        } finally {
            if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                if (strlen($eodlogs['errdesc']) > 2000) {
                    $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                }
                AdEodLogDetail::create($eodlogs);
            }
        }
    }
    /**
     * Хугацаа нь дуусаж байгаа хадгаламжийн бүтээгдэхүүн солих дансдын жагсаалт
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800102($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getTxnDate($instid);
        $isextend = false;
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800102',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $dpservice->termEndSavingsAcnts($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new DpTxnService();
        foreach ($datas as $data) {
            try {
                DB::beginTransaction();
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();

                $tmpdata = [
                    'acntno' => $data->acntno,
                    'newcrcapacnt' => $data->newcrcapacnt,
                    'newcrcapacntmod' => $data->newcrcapacntmod,
                    'newcrcapmethod' => $data->newcrcapmethod,
                    'newcrintrate' => $data->newcrintrate,
                    'newcrintrateacnt' => $data->newcrintrateacnt,
                    'newprodcode' => $data->newprodcode,
                    'newtermbasis' => $data->newtermbasis,
                    'newtermexpdate' => $data->newtermexpdate,
                    'newtermlen' => $data->newtermlen,
                    'newtermnextbasis' => $data->newtermnextbasis,
                    'newtermnextcarcapacntmod' => $data->newtermnextcarcapacntmod,
                    'newtermnextcrcapacnt' => $data->newtermnextcrcapacnt,
                    'newtermnextcrcapmethod' => $data->newtermnextcrcapmethod,
                    'newtermnextcrintrate' => $data->newtermnextcrintrate,
                    'newtermnextcrintrateacnt' => $data->newtermnextcrintrateacnt,
                    'newtermnextedate' => $data->newnexttermexpdate,
                    'newtermnextlen' => $data->newtermnextlen,
                    'newtermnextprodcode' => $data->newtermnextprodcode,
                    'newtermnextsdate' => $data->newtermnextstartdate,
                    'newtermstartdate' => $data->newtermstartdate,
                    'rolldate' => $txndate,
                    'rollstr' => $data->rollstr,
                    'termcurrentcycle' => $data->termcurrentcycle,
                    'termcyclecount' => $data->termcyclecount,
                    'trantype' => $data->trantype,
                    'newdrint2cap' => $data->newdrint2cap,
                    'newdradjint' => $data->newdradjint,
                    'newcrintratechg' => $data->newcrintratechg,
                    'newcrintrateupd' => $data->newcrintrateupd,
                    'brchno' => $data->brchno,
                    'newtermminlen' => $data->newtermminlen,
                    'newtermmaxlen' => $data->newtermmaxlen,
                    'newcrintminrate' => $data->newcrintminrate,
                    'newcrintmaxrate' => $data->newcrintmaxrate,
                    'newprocflag' => $data->newprocflag,
                    'curcode' => $data->curcode,
                    'newtempprodcode' => $data->newtempprodcode,
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $userid,
                    'updated_by' => $userid,
                ];
                $trantype = $data->trantype;
                //Бүтээгдэхүүн идэвхгүй төлөвт байгаа бол хугацаа сунгахгүй автоматаар солих хугацаагүй хадгаламжийн бүтээгдэхүүнрүү шилжүүлнэ
                if ($data->newprodstatus == 0) {
                    $tmpdata['newprodcode'] = $data->newtempprodcode;
                    $tmpdata['procflag'] = $data->tempprodprocflag;
                    $tmpdata['newcrintrate'] = $data->newtempcrintminrate;
                    $isextend = false;
                } else if ($data->pausedtermextend == 1 && ($data->trantype == "TO_TERMDEP" || $data->trantype == "ROLL" || $data->trantype == "LAST_ROLL")) {
                    DpRollTemp::create($tmpdata); // шилжих параметрүүдийг хадгална
                    $tmpdata['newprodcode'] = $data->newtempprodcode; // түр шилжих хугацаагүй хад. бүтээгдэхүүн
                    $tmpdata['procflag'] = $data->tempprodprocflag;
                    $tmpdata['newcrintrate'] = $data->newtempcrintminrate;
                    $isextend = false;
                } else {
                    $tmpdata['newprodcode'] = $data->newprodcode;
                    switch ($trantype) {
                        case 'TO_TERMDEP':
                            if ($data->newprodcode == $data->prodcode) {
                                //Хадгаламжийн хугацаа сунгах
                                $isextend = true;
                            } else {
                                //Хадгаламжийн бүтээгдэхүүн солих
                                $isextend = false;
                            }
                            break;

                        case 'TO_SAVING':
                            //Хадгаламжийн бүтээгдэхүүн солих
                            $isextend = false;
                            break;
                        case 'ROLL':
                            if ($data->newprodcode == $data->prodcode) {
                                //Хадгаламжийн хугацаа сунгах
                                $isextend = true;
                            } else {
                                //Хадгаламжийн бүтээгдэхүүн солих
                                $isextend = false;
                            }
                            break;
                        case 'LAST_ROLL':
                            if ($data->newprodcode == $data->prodcode) {
                                //Хадгаламжийн хугацаа сунгах
                                $isextend = true;
                            } else {
                                //Хадгаламжийн бүтээгдэхүүн солих
                                $isextend = false;
                            }
                            break;

                        default:
                            # code...
                            break;
                    }
                }
                if ($isextend) {
                    $dpservice->extendTermDp($data, $txndate, $instid, $userid);
                    // dptxn-д бичилт хийх
                    $p->setRate($p->getRate() ?? 1);
                    $p->setContRate(1);
                    $p->setTxnAmount(0);
                    $p->setContAmount($p->getTxnAmount());
                    $p->setIntbal(0);
                    $p->setTxncode('dp901031');
                    $p->setIscash($p->getIscash() ?? 0);
                    $p->setSourcecode(1);
                    $p->setTxnAcntCode($data->acntno);
                    $p->setCurCode($data->curcode);
                    $p->setCustno($data->custno);
                    $p->setProdcode($data->newprodcode);
                    $p->setAcntbal($data->currentbal);
                    $p->setAcntbrchno($data->brchno);
                    $p->setClscode($data->odclscode);
                    $p->setTxnDesc('Хадгаламжийн хугацаа сунгав. (BOD)');
                    $p->setInstid($instid);
                    $p->setJrno(CoreService::getNextJrno());
                    $p->setJritemno(0);
                    $p->setTxndate($txndate);
                    $p->setTxntype(5);
                    $p->setParenttxncode($p->getTxncode());
                    $p->setX(5);
                    $p->setChid(1);
                    $p->setPostdate(getNow());
                    $p->setUserid($userid);
                    $p->setIsPreview(0);
                    $jritem = new TxnItemEntity();
                    $jritem->initMainjritemno(AccountTypeEnum::dp);
                    $service->insertDpTxnDb($p->getFinTxnEntry('MAIN'), $jritem);
                } else {
                    // Данснуудын хугацааг сунгалгүй хугацаагүй хадгаламжийн бүтээгдэхүүн рүү шилжүүлнэ.
                    $p->setProdcode($tmpdata['newprodcode']);
                    $p->setTxncode('dp901031');
                    $p->setTxnAcntCode($tmpdata['acntno']);
                    $p->setCurCode($tmpdata['curcode']);
                    $p->setTermBasis($tmpdata['newtermbasis']);
                    $p->setTermLen($tmpdata['newtermlen']);
                    $p->setTermStartDate($tmpdata['newtermstartdate']);
                    $p->setTermEndDate($tmpdata['newtermexpdate']);
                    $p->setTxnDesc('Хугацаа нь дуусаж буй хадгаламжийн бүтээгдэхүүн солив (BOD)');
                    $p->setContCurCode($tmpdata['curcode']);
                    $p->setSourcecode(1);
                    $p->setTxnAmount(0);
                    $p->setInstid($instid);
                    $p->setPostdate(getNow());
                    $p->setUserid($userid);
                    $p->setTxndate($txndate);
                    $p->setIntRate($tmpdata['newcrintrate']);
                    $service->doDpChangeAcntProdcodeParams($p)->jsonSerialize();
                }
                $step->succount = $step->succount + 1;
                DB::commit();
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                }
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    DB::rollBack();
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Түр хугацаагүй хадгаламжруу шилжсэн данснуудыг хугацаат хадгаламжруу шилжүүлэх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800113($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getTxnDate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800113',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = DpRollTemp::where('instid', $instid)->where('rolldate', $txndate)->orderby('acntno')->get();

        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new DpTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();
                $p->setTxncode('dp901031');
                $p->setProdcode($data->newprodcode);
                $p->setTxnAcntCode($data->acntno);
                $p->setCurCode($data->curcode);
                $p->setTermBasis($data->newtermbasis);
                $p->setTermLen($data->newtermlen);
                $p->setTermStartDate($data->newtermstartdate);
                $p->setTermEndDate($data->newtermexpdate);
                $p->setTxnDesc('Хугацаа сунгаж буй хадгаламжийн бүтээгдэхүүн солив (BOD)');
                $p->setSourcecode(1);
                $p->setTxnAmount(0);
                $p->setRate(1);
                $p->setInstid($instid);
                $p->setPostdate(getNow());
                $p->setUserid($userid);
                $p->setTxndate($txndate);
                $service->doDpChangeAcntProdcodeParams($p)->jsonSerialize();
                //Анхны дараагын хугацааны мэдээллийг тавих
                if ($data->termcurrentcycle > 0) {
                    DpAccount::where('instid', $instid)->where('acntno', $data->acntno)->update([
                        'termnextprodcode' => $data->newtermnextprodcode,
                        'termnextlen' => $data->newtermnextlen,
                        'termnextsdate' => $data->newtermnextsdate,
                        'termnextedate' => $data->newtermnextedate,
                        'termcurrentcycle' => $data->termcurrentcycle * 1 - 1
                    ]);
                }
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Хугацаа нь дуусаж байгаа битүүмжээс дансыг чөлөөлөх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800109($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800109',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->unHoldDpAcnts($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new DpHoldTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();
                $p->setTxncode('dp800003');
                $p->setTxnAcntCode($data->acntno);
                $p->setOrgJrno($data->jrno);
                $p->setTxnDesc('Битүүмжийн хугацаа дуусаж чөлөөлөв (BOD)');
                $p->setSourcecode(1);
                $p->setTxnAmount(0);
                $p->setRate(1);
                $p->setInstid($instid);
                $p->setPostdate(getNow());
                $p->setUserid($userid);
                $p->setTxndate($txndate);
                $service->doUnHoldAcntTxn($p)->jsonSerialize();
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Дансныг түр идэвхгүй төлөвт оруулах
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800115($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800115',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->DormantAcnts($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                DpAccount::where('instid', $instid)->where('acntno', $data->acntno)
                    ->update([
                        'prevstatus' => $data->oldstatuscode,
                        'statusid' => $data->status,
                        'updated_by' => $userid
                    ]);
                $step->succount = $step->succount + 1;
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
            } finally {
                if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Өмнөх өдрийн үлдэгдэл хадгалах функц
     * Үүнийг зөвхөн 31ны өдөр л дуудаж ажиллуулна. тэнцүү хоногт сарын хүү тооцлолд хэрэг болж байгаа юм.
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800015($step)
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800015',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $date = Carbon::createFromFormat('Y-m-d', $txndate);
        $day = $date->day;
        try {
            if ($day == 31) {
                $datas = DpAccount::where('instid', $instid)->where('statusid', '>', 2)->update([
                    'prevbal' => DB::raw('tmp_bal'),
                    'prevdrcasbalance' => DB::raw('tmp_drcasbalance'),
                    'updated_by' => $userid
                ]);
                $step->allcount = $datas;
                $step->succount = $datas;
            }
        } catch (MeException $ex) {
            $eodlogs['errtype'] = 'A';
            $eodlogs['errdesc'] = $ex->getMessage();
            if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                $eodlogs['errtype'] = 'D';
                throw new Exception($ex->getMessage());
            }
        } catch (\Throwable $th) {
            $eodlogs['errtype'] = 'D';
            $eodlogs['errdesc'] = $th->getMessage();
            throw $th;
        } finally {
            if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                if (strlen($eodlogs['errdesc']) > 2000) {
                    $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                }
                AdEodLogDetail::create($eodlogs);
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Сарын эцэст хуримтлуулах кредит хүү тооцох
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800024($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new DpEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800024',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CalcDPCrEOMIntAcnts($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['errtype'] = null;
                DpAccount::where('instid', $instid)->where('acntno', $data->acntno)
                    ->update([
                        'crint2acr' => ($data->newcrintacr ?? 0),
                        'totalbalperiod' => 0,
                        'updated_by' => $userid
                    ]);
            } catch (\Throwable $th) {
                $eodlogs['errtype'] = 'D';
                $eodlogs['errdesc'] = $th->getMessage();
                throw $th;
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
     * Сарын дундаж, макс, минимум үлдэгдэл цэвэрлэх
     * @return void
     */
    public function ad800114($step)
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800114',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        try {
            $datas = DpAccount::where('instid', $instid)->where('statusid', '>', 2)
                ->update([
                    'totalbalperiod' => 0,
                    'totaldayperiod' => 0,
                    'minbalance' => DB::raw('currentbal'),
                    'maxbalance' => DB::raw('currentbal'),
                    'updated_by' => $userid
                ]);
            $step->allcount = $datas;
            $step->succount = $datas;
        } catch (\Throwable $th) {
            $eodlogs['errtype'] = 'D';
            $eodlogs['errdesc'] = $th->getMessage();
            throw $th;
        } finally {
            if (isset($eodlogs['errtype']) && !empty($eodlogs['errtype'])) {
                if (strlen($eodlogs['errdesc']) > 2000) {
                    $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                }
                AdEodLogDetail::create($eodlogs);
            }
        }
    }

    /**
     * Депозитийн түүх хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800119($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new DpEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800119',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        try {
            DB::beginTransaction();
            $service->DpAcntHistDel($txndate, $lastitem, $instid);
            $datas = $service->DpAcntHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            $chunks = array_chunk($datas->toArray(), 25);
            foreach ($chunks as $chunk) {
                DpAccountHist::insert($chunk);
            }
            $step->allcount = count($datas);
            $step->succount = count($datas);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $eodlogs['errtype'] = 'D';
            $eodlogs['errdesc'] = $th->getMessage();
            throw $th;
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
