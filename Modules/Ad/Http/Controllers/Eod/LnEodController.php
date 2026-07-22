<?php

namespace Modules\Ad\Http\Controllers\Eod;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Ad\Entities\AdResAccountBal;
use Modules\Ad\Entities\Views\VwAdResAccountBalCalc;
use Modules\Ad\Http\Services\LnEodService;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstCur;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Enums\EodContinueResponseCodesEnum;
use Modules\Gp\Enums\LnStatusCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\EBarimtJob;
use Modules\Ln\Entities\LnAccount;
use Modules\Ln\Entities\LnAccountDue;
use Modules\Ln\Entities\LnAccountMor;
use Modules\Ln\Entities\LnAccountType;
use Modules\Ln\Entities\LnMor;
use Modules\Ln\Entities\LnMorHist;
use Modules\Ln\Entities\LnMorHistMonthly;
use Modules\Ln\Entities\LnNrs;
use Modules\Tr\Entities\FinTxnEntity;
use Modules\Tr\Entities\LnTxn;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Services\DpHoldTxnService;
use Modules\Tr\Http\Services\IaTxnService;
use Modules\Tr\Http\Services\LnTxnService;
use TypeError;

class LnEodController extends CoreController
{
    private function getFineLabel($instid)
    {
        $fineLabel = trim((string) CoreService::getInstGp($instid, 'FINE_LABEL'));

        return $fineLabel !== '' ? $fineLabel : 'Нэмэгдүүлсэн хүү';
    }

    /**
     * Зээлийн эргэн төлөлт
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800004($step)
    {
        $lastitem = $this->getLastEodStep($step);

        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $serviceeod = new LnEodService();
        $txndate = CoreService::getEodSysdate($instid);
        $accounts = $serviceeod->txnLnRepayAcnt($txndate, $lastitem, $instid);

        $service = new LnTxnService();
        if (!$lastitem) {
            $step->allcount = count($accounts);
        }
        foreach ($accounts as $account) {
            $p = new TxnJrnlEntity();
            if (!empty($account->repayrtypecode)) {
                $p->setRtypecode($account->repayrtypecode);
            } else {
                $p->setRtypecode(1);
            };
            $p->setTxnAcntCode($account->acntno);
            $p->setCurCode($account->curcode);
            $p->setContAcntCode($account->repayacntno);
            $p->setTrancurcode($account->curcode);
            $p->setContCurCode($account->curcode1);
            $p->setAddparams(['CONTACNTTYPE' => AccountTypeEnum::dp]);
            $p->setTxncode('ln902011');
            $p->setTxnDesc('Зээлийн эргэн төлөлт. EOD');
            $p->setSourcecode(1);
            $p->setInstid(CoreService::getCurInstId());
            $p->setPostdate(getNow());
            $p->setUserid(CoreService::getCurUserId());
            $p->setTxndate(CoreService::getEodSysdate($p->getInstid()));
            // Log::debug(['Зээлийн эргэн төлөлт - TXNDATE', $p->getTxndate()]);
            $p->setContCurCode($account->curcode1);
            try {
                $_CurCode1 = $account->curcode1; //DP
                $_CurCode2 = $account->curcode; // LN
                $_RTypeCode = $account->repayrtypecode;

                $requiredamount = $account->requiredamount;
                $availableamount = $account->availableamount;

                $autorepayamount = $account->autorepayamount;
                $dueprinc = $account->dueprinc;
                $capbint = $account->capbint;
                $capfint = $account->capfint;
                $capcint = $account->capcint;
                $fineint2cap = $account->fineint2cap;
                $adjfint2cap = $account->adjfint2cap;
                $ctcurrentbal = $account->ctcurrentbal;

                $baseint2cap = $account->baseint2cap;
                $adjbint2cap = $account->adjbint2cap;
                $bctcurrentbal = $account->bctcurrentbal;

                $comint2cap = $account->comint2cap;
                $adjcint2cap = $account->adjcint2cap;
                $comctcurrentbal = $account->comctcurrentbal;

                if ($autorepayamount == 1) {
                    $requiredamount = ($dueprinc > 0 ? $dueprinc : 0)
                        + ($capbint > 0 ? $capbint : 0) + ($capfint > 0 ? $capfint : 0)
                        + ($capcint > 0 ? $capcint : 0);
                    switch (CoreService::getInstGp($instid, 'IntCapBeforeLNPayment')) {
                        case '1':
                            $requiredamount = $requiredamount + $fineint2cap + $adjfint2cap + $ctcurrentbal;
                            break;
                        case '2':
                            $requiredamount = $requiredamount + $fineint2cap + $adjfint2cap
                                + $ctcurrentbal + $baseint2cap + $adjbint2cap + $bctcurrentbal
                                + $comint2cap + $adjcint2cap + $comctcurrentbal;
                            break;

                        default:
                            # code...
                            break;
                    }
                } else if ($autorepayamount == 0) {
                    if ((($account->requiredamount * 1) + $dueprinc) > 0) {
                        $requiredamount = $account->requiredamount;
                    } else {
                        $requiredamount = ($account->requiredamount * 1) + $dueprinc;
                    }
                }

                $_CurRate1 = 1;
                $_CurRate2 = 1;

                if ($_CurCode1 != $_CurCode2) {
                    $_CurRate1 = $service->getRate($_CurCode1, $p->getRtypecode(), $p->getTxndate(), 'BUY');
                    $_CurRate2 = $service->getRate($_CurCode2, $p->getRtypecode(), $p->getTxndate(), 'SELL');
                    // Зээлийн валютын ханшаар хэд болж байгааг олох
                    if (round($_CurRate2, 2) != 0) {
                        $availableamount = convertAmt($availableamount, $_CurRate1, $_CurRate2);
                    } else {
                        $availableamount = 0;
                    }
                }

                if ($availableamount < $requiredamount) {
                    $requiredamount = $availableamount;
                }

                $eodlogs = [
                    'eoddate' => $step->eoddate,
                    'stepno' => $step->stepno,
                    'orderno' => $step->orderno,
                    'acntno' => $account->acntno,
                    'acntbrchno' => $account->brchno,
                    'orderno' => $step->orderno,
                    'ACTION_CODE' => $p->getTxncode(),
                    'instid' => $instid,
                    'created_by' => $userid,
                    'updated_by' => $userid,
                ];
                if ($requiredamount > 0) {
                    $p->setTxnAmount($requiredamount);
                    $txndata = $service->loanPaymentTxn($p)->jsonSerialize();
                    $step->succount = $step->succount + 1;
                    try {
                        EBarimtJob::dispatch('ln902011', $txndata, auth()->user())->onQueue("sendVAT");
                    } catch (Exception $ex) {
                        Log::error($ex);
                    }
                }
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

        return [
            'status' => 200
        ];
    }

    /**
     * Зээлийн дефаулт дүн, огноо, хугацаа хэтэрсэн хоног шинэчлэх
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800005($step)
    {

        $step->stepdesc = 'Нийт- ' . 0 . ', Хийгдсэн- ' . 0;
        $step->statusid = 1;
        return [
            'status' => 500
        ];
    }

    /**
     * Зээлийн дансны ангилал шилжүүлэх
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800007($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800007',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $clscodes = GPInstConst::where('parent_code', 'clscode')
            ->where('statusid', 1)
            ->where('instid', $instid)
            ->orderBy('value', 'DESC')->get();
        if (count($clscodes) == 0) {
            $clscodes = GPInstConst::where('parent_code', 'clscode')
                ->where('statusid', 1)
                ->where('instid', 1)
                ->orderBy('value', 'DESC')->get();
        }
        if (count($clscodes) == 0) {
            throw new MeException('Ангилалын бүртгэл олдсонгүй');
        }
        $loanClsNotAutoDecrease = 0;
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'loanClsNotAutoDecrease')->first();
        if (!empty($gp)) {
            $loanClsNotAutoDecrease = $gp->itemvalue;
        }
        // Log::debug($clscodes);
        $datas = $lnservice->getClsAccount($txndate, $lastitem, $instid);
        $datas = array_values(array_filter($datas, function ($account) use ($lnservice, $clscodes) {
            switch (+$account->autoclstype) {
                case 0:
                    $account->newclscode = $lnservice->getNewCls($account->duedays, $clscodes);
                    break;
                case 1:
                    $account->newclscode = $account->clscode;
                    break;
                default:
                    $account->newclscode = $account->clscode;
                    break;
            }
            return $account->newclscode != $account->clscode;
        }));
        $datas = array_values(array_filter($datas, function ($account) use ($loanClsNotAutoDecrease, $txndate, $instid) {
            if ($account->newclscode * 1 >= $account->clscode * 1) {
                return true;
            }

            if ($loanClsNotAutoDecrease == 1) {
                return false;
            }

            if ($loanClsNotAutoDecrease != 2) {
                return true;
            }

            $checkMonths = [
                Carbon::parse($txndate)->subMonth(1)->format('Y-m'),
                Carbon::parse($txndate)->subMonth(2)->format('Y-m'),
                Carbon::parse($txndate)->subMonth(3)->format('Y-m'),
            ];

            foreach ($checkMonths as $month) {
                $monthStart = Carbon::parse($month . '-01')->startOfMonth();
                $monthEnd   = Carbon::parse($month . '-01')->endOfMonth();

                $payamounttxnsum = LnTxn::where('acntno', $account->acntno)
                    ->where('instid', $instid)
                    ->where('txntype', 1)
                    ->where('corr', 0)
                    ->whereBetween('txndate', [$monthStart, $monthEnd])
                    ->sum('txnamount');

                $payamountschsum = LnNrs::where('acntno', $account->acntno)
                    ->where('instid', $instid)
                    ->where('statusid', 1)
                    ->whereBetween('payday', [$monthStart, $monthEnd])
                    ->sum('payamount');

                if ($payamounttxnsum == 0 || $payamounttxnsum < $payamountschsum) {
                    return false;
                }
            }

            return true;
        }));
        $txndesc = "Дансны ангилал солив. (EOD) ";
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $key => $account) {
            $acntno = $account->acntno;
            $curcode = $account->curcode;
            $newclscode = $account->newclscode;
            if ($newclscode != $account->clscode) {
                /**
                 * Ангилал муудах үед ($newclscode * 1 < $account->clscode * 1) үүний эсрэг тохиолдыг шалгаж байгаа учир шууд муудна.
                 * Ангилал сайжрах нөхцөл нь $account->capbint нь 0 юмуу 0-с бага үед сайжрах бөгөөд тохиргооноос хамааралтай байна.
                 */
                // if (!($account->capbint > 0 && $newclscode * 1 < $account->clscode * 1)) {
                $istxn = true;
                if ($newclscode * 1 < $account->clscode * 1) {
                    if ($loanClsNotAutoDecrease == 1) {
                        $istxn = false;
                    } else if ($loanClsNotAutoDecrease == 2) {
                        $checkMonths = [
                            Carbon::parse($txndate)->subMonth(1)->format('Y-m'),
                            Carbon::parse($txndate)->subMonth(2)->format('Y-m'),
                            Carbon::parse($txndate)->subMonth(3)->format('Y-m'),
                        ];

                        foreach ($checkMonths as $month) {
                            $monthStart = Carbon::parse($month . '-01')->startOfMonth();
                            $monthEnd   = Carbon::parse($month . '-01')->endOfMonth();

                            // Тухайн сарын Txn төлбөрийн нийлбэр
                            $payamounttxnsum = LnTxn::where('acntno', $acntno)
                                ->where('instid', $instid)
                                ->where('txntype', 1)
                                ->where('corr', 0)
                                ->whereBetween('txndate', [$monthStart, $monthEnd])
                                ->sum('txnamount');

                            // Тухайн сарын Schedule төлөлтийн нийлбэр
                            $payamountschsum = LnNrs::where('acntno', $acntno)
                                ->where('instid', $instid)
                                ->where('statusid', 1)
                                ->whereBetween('payday', [$monthStart, $monthEnd])
                                ->sum('payamount');

                            // Хэрвээ тухайн сард төлбөр дутсан бол
                            if ($payamounttxnsum == 0 || $payamounttxnsum < $payamountschsum) {
                                $istxn = false;
                                break;
                            }
                        }
                    }
                }

                if ($istxn) {
                    $p = new TxnJrnlEntity();
                    $p->setTxnAcntCode($acntno);
                    $p->setCurCode($curcode);
                    $p->setClscode($newclscode);
                    $p->setTxnDesc($txndesc);
                    $p->setTxncode('ln902081');
                    $p->setSourcecode(1);
                    $p->setTxnAmount(0);
                    $p->setRate(1);
                    $p->setInstid($instid);
                    $p->setPostdate(getNow());
                    $p->setUserid($userid);
                    $p->setTxndate($txndate);

                    $txnservice = new LnTxnService();
                    try {
                        $eodlogs['acntno'] = $account->acntno;
                        $eodlogs['acntbrchno'] = $account->brchno;
                        $eodlogs['errtype'] = null;

                        $resp = $txnservice->doLnChangeClsAcntParams($p);
                        if (!empty($resp->getTxnJrno())) {
                            $step->succount = $step->succount + 1;
                        }
                    } catch (MeException $ex) {
                        $eodlogs['errtype'] = 'A';
                        $eodlogs['errdesc'] = $ex->getMessage();
                        if ($ex->getCode() == 'RC000046') {
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
                // }
            }
        }
    }
    /**
     * Зээлийн шугамын өөрчлөлтийг балансын гадуурх дансанд хийх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800008($step)
    {

        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $txnservice = new LnTxnService();
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800008',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $lnservice->CreditLineTxn($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        // Log::debug($datas);
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $tP = new TxnJrnlEntity();
                $tP->setTxnAcntCode($data->acntno);
                $tP->setCurCode($data->curcode);
                $tP->setTxnDesc('Зээлийн шугамын үүрэг тэнцэлийн гадуур залруулав. (EOD)');
                $tP->setSourcecode(1);
                $tP->setRate(1);
                $tP->setInstid($instid);
                $tP->setPostdate(getNow());
                $tP->setUserid($userid);
                $tP->setTxncode('ln800014');
                $resp = $txnservice->doTxnLineAndCollCt($tP);
                if (!empty($resp->getTxnJrno())) {
                    $step->succount = $step->succount + 1;
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
    }

    /**
     * Зээлийн хүүг балансын гадуур автомат гаргах
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800010($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $txnservice = new LnTxnService();
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800010',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $gp = CoreService::getInstGp($instid, 'MoveOutOffBalance');
        if ($gp == 'A') {
            $datas = $lnservice->MoveOutOffBalance($txndate, $lastitem, $instid, $gp);

            if (!$lastitem) {
                $step->allcount = count($datas);
            }
            // Log::debug($datas);
            foreach ($datas as $data) {
                try {
                    $eodlogs['acntno'] = $data->acntno;
                    $eodlogs['acntbrchno'] = $data->brchno;
                    $eodlogs['errtype'] = null;
                    if ($data->autoconttype == 0) {
                        //Case LNProd.AutoContType
                        //0:	// ангилалаас хамаарах
                        //Хэрэв LNAcnt.ClsCode>=LNProd.AutoContCls
                        //    Хэрэв LNAcnt.StatusCode=3 & LNAcnt.IntStopType=0 үед
                        //        үед балансын гадуур хүү гарах процесс ажиллана
                        //үгүй бол
                        //    Хэрэв LNAcnt.StatusCode=3 & LNAcnt.IntStopType=1 үед
                        //        үед балансын гадуур хүүг буцааж оруулах процесс ажиллана
                        if ($data->clscode >= $data->autocontcls) {
                            if ($data->intstoptype == 0) {
                                //хүү зогсоох гүйлгээ дуудах
                                $pT = new TxnJrnlEntity();
                                $pT->setTxncode('ln800005');
                                $pT->setIntStopType(1);
                                $pT->setTxnAcntCode($data->acntno);
                                $pT->setTxnAmount(0);
                                $pT->setInstid($instid);
                                $pT->setCurCode($data->curcode);
                                $pT->setTxndate($txndate);
                                $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                                $pT->setPromo("CT");
                                $resp = $txnservice->doStopInt2Cap($pT);
                                if (!empty($resp->getTxnJrno())) {
                                    $step->succount = $step->succount + 1;
                                }
                            }
                        } else {
                            if ($data->intstoptype == 1 && $data->statusid == 3) {
                                //хүү оруулах гүйлгээ дуудах
                                $pT = new TxnJrnlEntity();
                                $pT->setTxncode('ln800006');
                                $pT->setTxnAcntCode($data->acntno);
                                $pT->setTxnAmount(0);
                                $pT->setInstid($instid);
                                $pT->setCurCode($data->curcode);
                                $pT->setTxndate($txndate);
                                $pT->setTxnDesc('Хүүг балансын дотуур хэвийн төлөвт оруулав. (EOD)');
                                $pT->setPromo("CT");
                                $resp = $txnservice->doStopInt2Cap($pT);
                                if (!empty($resp->getTxnJrno())) {
                                    $step->succount = $step->succount + 1;
                                }
                            }
                        }
                    } else if ($data->autoconttype == 1) {
                        switch ($data->autocontdueopt) {
                            case 0:
                                if (
                                    $data->autocontduedays > 0 &&
                                    ($data->duedays >= $data->autocontduedays ||
                                        $data->bintduedays >= $data->autocontduedays ||
                                        $data->cintduedays >= $data->autocontduedays)
                                ) {
                                    if ($data->intstoptype == 0) {
                                        $pT = new TxnJrnlEntity();
                                        $pT->setTxncode('ln800005');
                                        $pT->setIntStopType(1);
                                        $pT->setTxnAcntCode($data->acntno);
                                        $pT->setTxnAmount(0);
                                        $pT->setInstid($instid);
                                        $pT->setCurCode($data->curcode);
                                        $pT->setTxndate($txndate);
                                        $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                                        $pT->setPromo("CT");
                                        $resp = $txnservice->doStopInt2Cap($pT);
                                        if (!empty($resp->getTxnJrno())) {
                                            $step->succount = $step->succount + 1;
                                        }
                                    }
                                } else {
                                    if ($data->intstoptype == 1 && $data->statusid == 3) {
                                        //хүү оруулах гүйлгээ дуудах
                                        $pT = new TxnJrnlEntity();
                                        $pT->setTxncode('ln800006');
                                        $pT->setTxnAcntCode($data->acntno);
                                        $pT->setTxnAmount(0);
                                        $pT->setInstid($instid);
                                        $pT->setCurCode($data->curcode);
                                        $pT->setTxndate($txndate);
                                        $pT->setTxnDesc('Хүүг балансын дотуур хэвийн төлөвт оруулав. (EOD)');
                                        $pT->setPromo("CT");
                                        $resp = $txnservice->doStopInt2Cap($pT);
                                        if (!empty($resp->getTxnJrno())) {
                                            $step->succount = $step->succount + 1;
                                        }
                                    }
                                }
                                break;
                            case 1:
                                if ($data->duedays >= $data->autocontduedays) {
                                    if ($data->intstoptype == 0) {
                                        $pT = new TxnJrnlEntity();
                                        $pT->setTxncode('ln800005');
                                        $pT->setIntStopType(1);
                                        $pT->setTxnAcntCode($data->acntno);
                                        $pT->setTxnAmount(0);
                                        $pT->setInstid($instid);
                                        $pT->setCurCode($data->curcode);
                                        $pT->setTxndate($txndate);
                                        $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                                        $pT->setPromo("CT");
                                        $resp = $txnservice->doStopInt2Cap($pT);
                                        if (!empty($resp->getTxnJrno())) {
                                            $step->succount = $step->succount + 1;
                                        }
                                    }
                                } else {
                                    if ($data->intstoptype == 1 && $data->statusid == 3) {
                                        //хүү оруулах гүйлгээ дуудах
                                        $pT = new TxnJrnlEntity();
                                        $pT->setTxncode('ln800006');
                                        $pT->setTxnAcntCode($data->acntno);
                                        $pT->setTxnAmount(0);
                                        $pT->setInstid($instid);
                                        $pT->setCurCode($data->curcode);
                                        $pT->setTxndate($txndate);
                                        $pT->setTxnDesc('Хүүг балансын дотуур хэвийн төлөвт оруулав. (EOD)');
                                        $pT->setPromo("CT");
                                        $resp = $txnservice->doStopInt2Cap($pT);
                                        if (!empty($resp->getTxnJrno())) {
                                            $step->succount = $step->succount + 1;
                                        }
                                    }
                                }
                                break;
                            case 2:
                                if (
                                    $data->bintduedays >= $data->autocontduedays ||
                                    $data->cintduedays >= $data->autocontduedays
                                ) {
                                    if ($data->intstoptype == 0) {
                                        $pT = new TxnJrnlEntity();
                                        $pT->setTxncode('ln800005');
                                        $pT->setIntStopType(1);
                                        $pT->setTxnAcntCode($data->acntno);
                                        $pT->setTxnAmount(0);
                                        $pT->setInstid($instid);
                                        $pT->setCurCode($data->curcode);
                                        $pT->setTxndate($txndate);
                                        $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                                        $pT->setPromo("CT");
                                        $resp = $txnservice->doStopInt2Cap($pT);
                                        if (!empty($resp->getTxnJrno())) {
                                            $step->succount = $step->succount + 1;
                                        }
                                    }
                                } else {
                                    if ($data->intstoptype == 1 && $data->statusid == 3) {
                                        //хүү оруулах гүйлгээ дуудах
                                        $pT = new TxnJrnlEntity();
                                        $pT->setTxncode('ln800006');
                                        $pT->setTxnAcntCode($data->acntno);
                                        $pT->setTxnAmount(0);
                                        $pT->setInstid($instid);
                                        $pT->setCurCode($data->curcode);
                                        $pT->setTxndate($txndate);
                                        $pT->setTxnDesc('Хүүг балансын дотуур хэвийн төлөвт оруулав. (EOD)');
                                        $pT->setPromo("CT");
                                        $resp = $txnservice->doStopInt2Cap($pT);
                                        if (!empty($resp->getTxnJrno())) {
                                            $step->succount = $step->succount + 1;
                                        }
                                    }
                                }
                                break;
                            default:
                                # code...
                                break;
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
        } else if ($gp == 'Y') {
            $datas = $lnservice->MoveOutOffBalance($txndate, $lastitem, $instid, $gp);

            if (!$lastitem) {
                $step->allcount = count($datas);
            }
            foreach ($datas as $data) {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                try {
                    if (round($data->capbint, 2) != 0 && $data->baseint2cap + $data->capbint > 0 && round($data->ctacntno, 2) == 0) {
                        //Үндсэн хүү гаргах гүйлгээ дуудах
                        $pT = new TxnJrnlEntity();
                        $pT->setTxncode('ln800005');
                        $pT->setIntStopType(1);
                        $pT->setTxnAcntCode($data->acntno);
                        $pT->setTxnAmount(0);
                        $pT->setInstid($instid);
                        $pT->setCurCode($data->curcode);
                        $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                        $pT->setPromo("CT");
                        $resp = $txnservice->doStopInt2Cap($pT);
                        if (!empty($resp->getTxnJrno())) {
                            $step->succount = $step->succount + 1;
                        }
                    }
                    if (round($data->capcint, 2) != 0 && $data->comint2cap + $data->capcint > 0 && round($data->ctcomacntno, 2) == 0) {
                        //Үндсэн хүү гаргах гүйлгээ дуудах
                        $pT = new TxnJrnlEntity();
                        $pT->setTxncode('ln800005');
                        $pT->setIntStopType(1);
                        $pT->setTxnAcntCode($data->acntno);
                        $pT->setTxnAmount(0);
                        $pT->setInstid($instid);
                        $pT->setCurCode($data->curcode);
                        $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                        $pT->setPromo("CT");
                        $resp = $txnservice->doStopInt2Cap($pT);
                        if (!empty($resp->getTxnJrno())) {
                            $step->succount = $step->succount + 1;
                        }
                    }
                    if (round($data->capfint, 2) != 0 && $data->fineint2cap + $data->capfint > 0 && round($data->ctfineacntno, 2) == 0) {
                        //Үндсэн хүү гаргах гүйлгээ дуудах
                        $pT = new TxnJrnlEntity();
                        $pT->setTxncode('ln800005');
                        $pT->setIntStopType(1);
                        $pT->setTxnAcntCode($data->acntno);
                        $pT->setTxnAmount(0);
                        $pT->setInstid($instid);
                        $pT->setCurCode($data->curcode);
                        $pT->setTxnDesc('Хүү зогсоож тэнцлийн гадуур гаргав. (EOD)');
                        $pT->setPromo("CT");
                        $resp = $txnservice->doStopInt2Cap($pT);
                        if (!empty($resp->getTxnJrno())) {
                            $step->succount = $step->succount + 1;
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
        }
    }
    /**
     * Барьцаа хөрөнгийн үнэлгээний өөрчлөлтийг тэнцлийн гадуур тусгах
     * ad800013
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800013($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $txnservice = new LnTxnService();
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800013',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $lnservice->moveMortgageCostCT($txndate, $lastitem, $instid);

        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        // Log::debug($datas);
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                if ($data->lnstatusid != 0) {
                    $tP = new TxnJrnlEntity();
                    $tP->setTxnAcntCode($data->acntno);
                    $tP->setCurCode($data->curcode);
                    $tP->setTxnDesc('Барьцаа хөрөнгийн үнэлгээ тэнцэлийн гадуур залруулав. (EOD)');
                    $tP->setSourcecode(1);
                    $tP->setRate(1);
                    $tP->setInstid($instid);
                    $tP->setPostdate(getNow());
                    $tP->setUserid($userid);
                    $tP->setTxncode('ln800015');
                    $resp = $txnservice->doTxnLineAndCollCt($tP);
                    if (!empty($resp->getTxnJrno())) {
                        $step->succount = $step->succount + 1;
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
    }

    /**
     * Зээлээс чөлөөлсөн болон Зээлийн данс хаагдсан Барьцаа хөрөнгийн үнэлгээний өөрчлөлтийг тэнцлийн гадуур тусгах
     * ad800014
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800014($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $txnservice = new LnTxnService();
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800014',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $lnservice->removeMortgageCostCT($txndate, $lastitem, $instid);

        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        // Log::debug($datas);
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $tP = new TxnJrnlEntity();
                $tP->setTxnAcntCode($data->acntno);
                $tP->setCurCode($data->curcode);
                $tP->setTxnDesc('Барьцаа хөрөнгийн үнэлгээ тэнцэлийн гадуур чөлөөлөв. (EOD)');
                $tP->setSourcecode(1);
                $tP->setRate(1);
                $tP->setInstid($instid);
                $tP->setPostdate(getNow());
                $tP->setUserid($userid);
                $tP->setTxncode('ln800015');
                $resp = $txnservice->doTxnCollRemoveCt($tP, $data);
                if (!empty($resp->getTxnJrno())) {
                    $step->succount = $step->succount + 1;
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
    }
    /**
     * Эрсдлийн санг авто байгуулах алхам
     * ad800125
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800125($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800125',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        try {
            $isdo = GPInstGp::where('instid', $instid)
                ->where('itemname', 'LONAUTORES')->where('itemvalue', 'Y')->first();
            if (!empty($isdo)) {
                $datas = VwAdResAccountBalCalc::where('instid', $instid)->orderBy('acntno')->get();

                if (!$lastitem) {
                    $step->allcount = count($datas);
                }

                foreach ($datas as $data) {
                    try {
                        $eodlogs['acntno'] = $data->acntno;
                        $eodlogs['acntbrchno'] = $data->brchno;
                        $eodlogs['errtype'] = null;
                        $accountBal = AdResAccountBal::where('acntno', $data->acntno)
                            ->where('acnttype', $data->resacnttype ?? 'LN')
                            ->where('instid', $instid)
                            ->where('statusid', 0)
                            ->first();
                        if (empty($data->res_acntno)) {
                            $this->error("RC000065");
                        }
                        if ($accountBal) {
                            AdResAccountBal::where('acntno', $data->acntno)
                                ->where('acnttype', $data->resacnttype ?? 'LN')
                                ->where('instid', $instid)
                                ->where('statusid', 0)->update([
                                    'acntno' => $data->acntno,
                                    'acnttype' => $data->resacnttype ?? 'LN',
                                    'balance' => $data->princbal,
                                    'clscode' => $data->clscode,
                                    'resdate' => $txndate,
                                    'resbal' => $data->newresbal,
                                    'rescur' => $data->curcode,
                                    'res_acntno' => $data->res_acntno,
                                    'res_acnttype' => $data->res_acnttype,
                                    'cont_acntno' => $data->cont_acntno,
                                    'cont_acnttype' => $data->cont_acnttype,
                                    'amount' => $data->amount,
                                    'rescls' => $data->rescls,
                                    'errordesc' => null,
                                    'statusid' => 0,
                                    'updated_by' => $userid,
                                ]);
                        } else {
                            $accountBal = AdResAccountBal::create([
                                'acntno' => $data->acntno,
                                'acnttype' => $data->resacnttype ?? 'LN',
                                'balance' => $data->princbal,
                                'clscode' => $data->clscode,
                                'resdate' => $txndate,
                                'resbal' => $data->newresbal,
                                'rescur' => $data->curcode,
                                'res_acntno' => $data->res_acntno,
                                'res_acnttype' => $data->res_acnttype,
                                'cont_acntno' => $data->cont_acntno,
                                'cont_acnttype' => $data->cont_acnttype,
                                'amount' => $data->amount,
                                'rescls' => $data->rescls,
                                'errordesc' => null,
                                'statusid' => 0,
                                'instid' => $instid,
                                'created_by' => $userid,
                                'updated_by' => $userid,
                            ]);
                        }
                        $accountBal = AdResAccountBal::where('acntno', $data->acntno)
                            ->where('acnttype', $data->resacnttype ?? 'LN')
                            ->where('instid', $instid)
                            ->where('statusid', 0)
                            ->first();
                        $p = new TxnJrnlEntity();
                        if ($accountBal->amount < 0) {
                            $p->setTxnAcntCode($accountBal->res_acntno);
                            $p->setContAcntCode($accountBal->cont_acntno);
                            $p->setTxnDesc('Эрсдэлийн сангийн буцаалт (EOM)' . $accountBal->acntno . ' ' . $accountBal->rescls . '->' . $accountBal->clscode);
                            $p->setTxnAmount($accountBal->amount * -1);
                        } else {
                            $p->setTxnAcntCode($accountBal->cont_acntno);
                            $p->setContAcntCode($accountBal->res_acntno);
                            $p->setTxnDesc('Эрсдэлийн сангийн гүйлгээ (EOM)' . $accountBal->acntno . ' ' . $accountBal->rescls . '->' . $accountBal->clscode);
                            $p->setTxnAmount($accountBal->amount);
                        }
                        $p->setTxndate(CoreService::getTxnDate($instid));
                        $p->setInstid($instid);
                        $p->setSourcecode('2');
                        $p->setPostdate(getNow());
                        $p->setUserid($userid);
                        $txnservice = new IaTxnService();
                        $txnservice->doInternalToInternal(clone $p);
                        $accountBal->statusid = 1;
                    } catch (\Throwable $th) {
                        if ($accountBal) {
                            $accountBal->statusid = 3;
                            $accountBal->errordesc = $th->getMessage();
                        }
                        Log::error($th);
                        //throw $th;
                    } finally {
                        if ($accountBal) {
                            $accountBal->save();
                        }
                    }

                    $step->succount = $step->succount + 1;
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

    /**
     * Зээлийн хүүний хувь шинэчлэх (Үлдэгдлийн дүрмээр)
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800036($step)
    {
        $step->stepdesc = "Алхам хийгдээгүй байна.";
    }

    /**
     * Зээлийн хүүний хувь шинэчлэх (Хугацааны дүрмээр)
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800037($step)
    {
        $step->stepdesc = "Алхам хийгдээгүй байна.";
    }

    /**
     * Зээлийн өдрийн хүү тооцоолох
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800038($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800038',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->calcLNDailyRate($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                LnAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        // 'tmp_baseintdaily' => $data->newbaseint,
                        'baseintdaily' => DB::raw('trunc(' . $data->newbaseint . ' + coalesce(baseroundint, 0), 2)'),
                        // 'baseintdaily' => $data->newbaseint,
                        // 'baseroundint' => DB::raw($data->newbaseint . ' + coalesce(baseroundint, 0) - trunc(' . $data->newbaseint . ' + coalesce(baseroundint, 0), 2)'),
                        // 'baseroundint' => 0,
                        'finecomintdaily' => DB::raw('trunc(' . $data->newfinecomint . ' * ' . $data->fine_multp . ' + coalesce(finecomroundint, 0), 2)'),
                        // 'finecomintdaily' => $data->newfinecomint * $data->fine_multp,
                        'finecomroundint' => DB::raw($data->newfinecomint . ' * ' . $data->fine_multp . ' + coalesce(finecomroundint, 0) - trunc(' . $data->newfinecomint . ' * ' . $data->fine_multp . ' + coalesce(finecomroundint, 0), 2)'),
                        // 'finecomroundint' => 0,
                        'comamount' => $data->newcomamount,
                        'dueamount' => $data->newdueamount,
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
    }

    /**
     * Зээлийн шатлалтай өдрийн хүү тооцоолох
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800039($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800039',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->calcLNDailyRateTier($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        // $fine_multp = " 1 ";
        // $multp = " 1 ";
        $sysdate = new Carbon($txndate);

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                LnAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'tmp_comintdaily' => DB::raw($data->newcomint . ' * ' . $data->multp),
                        'tmp_fineintdaily' => DB::raw($data->newfineint . ' * ' . $data->fine_multp),
                        'comintdaily' => DB::raw('Trunc(' . $data->newcomint . ' * ' . $data->multp . ' + COALESCE(comroundint, 0), 2)'),
                        // 'comroundint' => DB::raw(
                        //     'COALESCE(' . $data->newcomint . ', 0) * ' . $data->multp . ' + COALESCE(comroundint, 0) - Trunc(COALESCE(' . $data->newcomint . ', 0) * ' . $data->multp . ' + COALESCE(comroundint, 0), 2)'
                        // ),
                        'fineintdaily' => DB::raw('Trunc(COALESCE(' . $data->newfineint . ', 0) * ' . $data->fine_multp . ' + COALESCE(fineroundint, 0), 2)'),
                        // 'fineroundint' => DB::raw(
                        //     'COALESCE(' . $data->newfineint . ', 0) * ' . $data->fine_multp . ' + COALESCE(fineroundint, 0) - Trunc(COALESCE(' . $data->newfineint . ', 0) * ' . $data->fine_multp . ' + COALESCE(fineroundint, 0))'
                        // ),
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
    }

    /**
     * Зээлийн нэмэгдүүлсэн хүүний доод хэмжээг шалгах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800040($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800040',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->calcLNDailyRateFine($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $sysdate = new Carbon($txndate);
                $updatedata = [];
                if ($sysdate->day == 31) {
                    $updatedata = [
                        'fineintdaily' => DB::raw('CASE WHEN fineintdaily < CASE WHEN ' . $data->intdayoption . ' = 0 THEN ' . $data->minfine . ' ELSE fineintdaily - 1 END THEN ' . $data->minfine . ' ELSE fineintdaily END '),
                        'tmp_fineintdaily' => DB::raw('CASE WHEN fineintdaily < CASE WHEN ' . $data->intdayoption . ' = 0 THEN ' . $data->minfine . ' ELSE fineintdaily - 1 END THEN ' . $data->minfine . ' ELSE tmp_fineintdaily END '),
                        'finecomintdaily' => DB::raw('CASE WHEN finecomintdaily < CASE WHEN ' . $data->intdayoption . ' = 0 THEN ' . $data->minfine . ' ELSE fineintdaily - 1 END THEN ' . $data->minfine . ' ELSE finecomintdaily END '),
                    ];
                } else {
                    $updatedata = [
                        'fineintdaily' => DB::raw('CASE WHEN fineintdaily < ' . $data->minfine . ' THEN ' . $data->minfine . ' ELSE fineintdaily END'),
                        'tmp_fineintdaily' => DB::raw('CASE WHEN fineintdaily < ' . $data->minfine . ' THEN ' . $data->minfine . ' ELSE tmp_fineintdaily END'),
                        'finecomintdaily' => DB::raw('CASE WHEN finecomintdaily < ' . $data->minfine . ' THEN ' . $data->minfine . ' ELSE finecomintdaily END'),
                    ];
                }
                LnAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update($updatedata);
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
     * Зээлийн үндсэн хүү хуримтлуулах гүйлгээ үүсгэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800041($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800041',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->createLNBaseAcrJrl($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;

                $service = new LnTxnService();
                $service->loanAccrualPrincipal($data->acntno, 'ln902041', true, $txndate);
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

    /**
     * Зээлийн комитмэнт хүү хуримтлуулах гүйлгээ үүсгэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800043($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800043',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->createLNComAcrJrl($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;

                $service = new LnTxnService();
                $service->loanAccrualPrincipal($data->acntno, 'ln902042', true, $txndate);
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

    /**
     * Зээлийн нэмэгдүүлсэн хүү хуримтлуулах гүйлгээ үүсгэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800045($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800045',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->createLNFineAcrJrl($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;

                $service = new LnTxnService();
                $service->loanAccrualPrincipal($data->acntno, 'ln902043', true, $txndate);
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

    /**
     * Зээлийн нэмэгдүүлсэн хүүний хуримтлагдсан дүнг шинэчлэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800046($step)
    {
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $lnservice->updateLNFineInt2Cap($txndate, $instid);
    }

    /**
     * Зээлийн дансны үлдэгдэл түр хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800056($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800056',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CreateTmpLNBals($txndate, $lastitem, $instid);
        // Log::debug([count($datas), $datas]);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                LnAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'tmp_princbal' => DB::raw('princbal'),
                        'tmp_capbint' => DB::raw('capbint'),
                        'tmp_capbinte' => DB::raw('capbinte'),
                        'tmp_capcint' => DB::raw('capcint'),
                        'tmp_capfint' => DB::raw('capfint'),
                        'tmp_acrbint' => DB::raw('baseint2cap + adjbint2cap'),
                        'tmp_acrcint' => DB::raw('comint2cap + adjcint2cap'),
                        'tmp_acrfint' => DB::raw('fineint2cap + adjfint2cap'),
                        'tmp_statuscode' => DB::raw('statusid'),
                        'tmp_prodcode' => DB::raw('prodcode'),
                        'tmp_brchno' => DB::raw('brchno'),
                        'tmp_clscode' => DB::raw('clscode'),
                        'tmp_theorbal' => DB::raw('theorbal'),
                        'tmp_begdate' => DB::raw('begdate'),
                        'tmp_enddate' => DB::raw('enddate'),
                        'tmp_dueprinc' => DB::raw('dueprinc'),
                        'tmp_dueint' => DB::raw('dueint'),
                        'tmp_duecom' => DB::raw('duecom'),
                        'tmp_arreardate' => DB::raw('arreardate'),
                        'tmp_arreardateint' => DB::raw('arreardateint'),
                        'tmp_arreardatecom' => DB::raw('arreardatecom'),
                        'tmp_redrawlimit' => DB::raw('redrawlimit'),
                        'tmp_dueamount' => DB::raw('dueamount'),
                        'tmp_clscodetrm' => DB::raw('clscodetrm'),
                        'tmp_clscodeqlt' => DB::raw('clscodeqlt'),
                        'tmp_fineintdaily' => DB::raw('fineintdaily + fineroundint'),
                        'tmp_comintdaily' => DB::raw('comintdaily + comroundint'),
                        'tmp_baseintdaily' => DB::raw('baseintdaily + baseroundint'),
                        'tmp_ctacntno' => DB::raw('ctacntno'),
                        'tmp_ctcomacntno' => DB::raw('ctcomacntno'),
                        'tmp_ctfineacntno' => DB::raw('ctfineacntno'),
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
     * Зээлийн хүү балансын гадуур хуримтлуулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800060($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800060',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $lnservice->accruLNBaseIntCT($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new LnTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $service->loanAccrualCt($data->acntno, 'ln802041', $txndate)->jsonSerialize();
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
     * Зээлийн нэмэгдүүлсэн хүү балансын гадуур хуримтлуулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800061($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800061',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $lnservice->accruLNFineIntCT($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new LnTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $service->loanAccrualCt($data->acntno, 'ln802043', $txndate)->jsonSerialize();
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
     * Зээлийн комитмэнт хүү балансын гадуур хуримтлуулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800062($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800062',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        $datas = $lnservice->accruLNComIntCT($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new LnTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $service->loanAccrualCt($data->acntno, 'ln802042', $txndate)->jsonSerialize();
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
     * Зээлийн хүү капитализэшн хийх хуваарийн дагуу
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800081($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getTxnDate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800081',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $lnservice->capLnNrsAcnts($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new LnTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();
                $p->setTxnAcntCode($data->acntno);
                $p->setCurCode($data->curcode);
                $p->setSourcecode(1);
                $p->setTxndate($txndate);
                $p->setTxnAmount(0);
                $p->setTxncode('ln902051');
                $p->setInstid($instid);
                $p->setTxnDesc('Хуваарийн дагуу зээлийн үндсэн хүү кап хийв. (SOD)');
                $p1 = clone $p;
                $service->doCapInt($p1)->jsonSerialize();
                $p->setTxncode('ln902052');
                $p->setTxnDesc('Хуваарийн дагуу зээлийн ком хүү кап хийв. (SOD)');
                $p1 = clone $p;
                $service->doCapInt($p1)->jsonSerialize();
                $p->setTxncode('ln902053');
                $p->setTxnDesc('Хуваарийн дагуу зээлийн ' . $this->getFineLabel($instid) . ' кап хийв. (SOD)');
                $p1 = clone $p;
                $service->doCapInt($p1)->jsonSerialize();
                $step->succount = $step->succount + 1;
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();

                if ($ex->getCode() == 'RC000046') {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                } else {
                    Log::debug($ex);
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
    /**
     * Зээлийн хүү капитализэшн хийх сарын эцэст
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800082($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getTxnDate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800082',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $lnservice->capLnEOMAcnts($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $service = new LnTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $p = new TxnJrnlEntity();
                $p->setTxnAcntCode($data->acntno);
                $p->setCurCode($data->curcode);
                $p->setSourcecode(1);
                $p->setTxnAmount(0);
                $p->setTxncode('ln902051');
                $p->setInstid($instid);
                $p->setTxnDesc('Хуваарийн дагуу зээлийн үндсэн хүү кап хийв. (SOD)');
                $service->doCapInt($p)->jsonSerialize();
                $p->setTxncode('ln902052');
                $p->setTxnDesc('Хуваарийн дагуу зээлийн ком хүү кап хийв. (SOD)');
                $service->doCapInt($p)->jsonSerialize();
                $p->setTxncode('ln902053');
                $p->setTxnDesc('Хуваарийн дагуу зээлийн ' . $this->getFineLabel($instid) . ' кап хийв. (SOD)');
                $service->doCapInt($p)->jsonSerialize();
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
    /**
     * Зээлийн түүх хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800091($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800091',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->LnAcntHistDel($txndate, $lastitem, $instid);
            $datascount = $service->LnAcntHistAdd($txndate, $lastitem, $instid, $userid);
            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;

            $step->allcount = $datascount;
            $step->succount = $datascount;
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

    /**
     * Хугацаа нь дуусаж байгаа зээлийн бүтээгдэхүүний төлвийг өөрчлөх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800103($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800103',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        try {
            $step->allcount = LnAccountType::where('instid', $instid)->where('enddate', $txndate)
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
            if (isset($eodlogs['errtype'])) {
                if (strlen($eodlogs['errdesc']) > 2000) {
                    $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                }
                AdEodLogDetail::create($eodlogs);
            }
        }
    }
    /**
     * Зээлийн өр шинэчлэх
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800105($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $allcount = 0;
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800105',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {

            //1. Зээлийн өрийн огноо шинэчилэн тогтоох
            $allcount = LnAccount::where('instid', $instid)
                ->whereIn('statusid', [3, 4, 8])
                ->where(function ($query) {
                    $query->whereRaw('ctacntno + dueint <= 0')
                        ->orWhereRaw('ctcomacntno + duecom <= 0')
                        ->orWhereRaw('dueprinc <= 0');
                })
                ->update([
                    'updated_by' => $userid,
                    'arreardate' => DB::raw("CASE WHEN dueprinc > 0 THEN arreardate ELSE NULL END"),
                    'arreardateint' => DB::raw("CASE WHEN ctacntno + dueint > 0 THEN arreardateint ELSE NULL END"),
                    'arreardatecom' => DB::raw("CASE WHEN ctcomacntno + duecom > 0 THEN arreardatecom ELSE NULL END"),
                ]);

            $step->allcount =   $allcount;
            //2. онолын үлдэгдэл шинэчилэн тогтоох
            $allcount = LnAccount::where('instid', $instid)->where('enddate', $txndate)->where('redrawlimit', '<>', 0)
                ->update([
                    'updated_by' => $userid,
                    'theorbal' => 0
                ]);
            $step->allcount = $step->allcount +  $allcount;
            $lnaccountdata = LnAccount::select([
                'ln_account.acntno',
                'ln_account.princbal',
                'ln_account.nexttheorbal',
                DB::raw('COALESCE(ln_account_type.minprincdueamount, 0) as minprincdueamount'),
            ])
                ->leftJoin('ln_account_type', function ($join) {
                    $join->on('ln_account.instid', '=', 'ln_account_type.instid')
                        ->on('ln_account.prodcode', '=', 'ln_account_type.prodcode');
                })
                ->where('ln_account.instid', $instid)->where('ln_account.nextpayday', $txndate)->get();
            $lnservice = new LnTxnService();
            $jrItem = new TxnItemEntity();
            foreach ($lnaccountdata as $key => $lnaccount) {
                $p = new FinTxnEntity();
                $p->setTxnAcntCode($lnaccount->acntno);
                $p->setJrno(0);
                $p->setTxndate($txndate);
                $p->setInstid($instid);
                $accountduesamount = LnAccountDue::where('acntno', $p->getTxnAcntCode())
                    ->where('instid', $p->getInstid())
                    ->where('statusid', 1)
                    ->where('duebal', '>', 0)
                    ->where('duetype', 'P')
                    ->sum('duebal');
                if (empty($accountduesamount)) {
                    $accountduesamount = 0;
                }
                $calculateddueamount = $lnaccount->princbal - $lnaccount->nexttheorbal;
                $dueamount = $calculateddueamount - $accountduesamount;
                $p->setTxnAmount($dueamount);
                if ($p->getTxnAmount() > 0) {
                    $lnservice->createLnAccountDue($p, $jrItem, 'P');
                }
            }
            //3. Зээлийн өр шинэчилэн тогтоох
            $allcount = DB::update(
                "UPDATE ln_account
                    SET updated_by = :userid,
                        theorbal = COALESCE(ln_account.nexttheorbal, 0),
                        dueprinc = CASE
                            WHEN (ln_account.princbal - COALESCE(ln_account.nexttheorbal, 0)) > 0
                            THEN ln_account.princbal - COALESCE(ln_account.nexttheorbal, 0)
                            ELSE 0
                        END,
                        arreardate = CASE
                            WHEN (ln_account.princbal - COALESCE(ln_account.nexttheorbal, 0)) > 0
                            THEN COALESCE(ln_account.arreardate, :txndate_value::date)
                            ELSE NULL
                        END
                    FROM ln_account_type
                    WHERE ln_account.instid = :instid
                        AND ln_account.nextpayday = :txndate_filter::date
                        AND ln_account_type.instid = ln_account.instid
                        AND ln_account_type.prodcode = ln_account.prodcode",
                [
                    'userid' => $userid,
                    'txndate_value' => $txndate,
                    'txndate_filter' => $txndate,
                    'instid' => $instid,
                ]
            );
            $step->allcount = $step->allcount +  $allcount;
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
     * Дараагийн төлбөр хийх өдөр, онолын үлдэгдлийг шинэчлэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800107($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800107',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $service->NextPayDaySelect($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $nextPayDay = !empty($data->nextpayday)
                    ? Carbon::parse($data->nextpayday)
                    : null;
                LnAccount::where('instid', $instid)->where('acntno', $data->acntno)
                    ->update([
                        'nextcapday'   => $nextPayDay ? $nextPayDay->copy()->subDay() : null,
                        'nextpayday'   => $nextPayDay,
                        'nexttheorbal' => $data->nexttheorbal,
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
    }
    /**
     * Зээлийн шугамын лимитийн хуваарьт өдөр шугамын лимит өөрчлөх. Онолын үлд мөн өөрчилнө
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800108($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800108',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        //LimitSchedule – Шугамын лимит ашиглана" гэснийг шалгах
        $gp = GPInstGp::where('instid', $instid)->where('itemname', 'LimitSchedule')->first();

        if ($gp->itemvalue == '1') {
            $datas = $lnservice->NextRedrawLimitAcnts($txndate, $lastitem, $instid);
            // Log::debug([count($datas), $datas]);
            if (!$lastitem) {
                $step->allcount = count($datas);
            }
            $service = new LnTxnService();
            foreach ($datas as $data) {
                try {
                    $eodlogs['acntno'] = $data->acntno;
                    $eodlogs['acntbrchno'] = $data->brchno;
                    $eodlogs['errtype'] = null;
                    $p = new TxnJrnlEntity();
                    $p->setTxncode('ln800008');
                    $p->setTxnAcntCode($data->acntno);
                    $p->setCurCode($data->curcode);
                    $p->setRedRawLimit($data->linelimit - $data->redrawlimit);
                    $p->setTxnDesc('Шугаман зээлийн лимит өөрчлөв (EOD)');
                    $p->setSourcecode(1);
                    $p->setTxnAmount(0);
                    $p->setRate(1);
                    $p->setInstid($instid);
                    $p->setPostdate(getNow());
                    $p->setUserid($userid);
                    $p->setTxndate($txndate);
                    $service->doChangeAcntParamTxn($p)->jsonSerialize();
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
        }
    }
    /**
     * Хадгаламж барьцаалсан зээлээс битүүмжилсэн дүн болон барьцаа хөрөнгийн дүнг өөрчлөх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800116($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $dpcur = '';
        $lncur = '';
        $currate1 = 1;
        $currate2 = 1;
        $txnamountdp = 0;
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800116',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $lnservice->AdjustDepMorLonAcnts($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        $service = new DpHoldTxnService();
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->dpacntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                //Дүн тооцоолох
                $dpcur = $data->dpcurcode;
                $lncur = $data->lncurcode;
                $currate1 = 1;
                $currate2 = 1;
                $formula = '$LoanBal + round($LoanBal * $IntRate / 100 / $YearDays * $LoanDays, 2)';
                $formula = $data->depmorloanformula ?? $formula;
                //$LoanBal = round($lnamt * ($costLnRate / $costDpRate), 2);
                $LoanBal = round($data->princbal, 2);
                $IntRate = $data->intrate;
                $YearDays = $data->yeardays;
                $begDate = Carbon::parse($data->begdate);
                $endDate = Carbon::parse($data->enddate);
                $LoanDays = $endDate->diffInDays($begDate);
                try {
                    $holdamt = eval('return ' . $formula . ';');
                } catch (\Throwable $th) {
                    $this->error('RC000147', [
                        'prodcode' => $data->prodcode
                    ]);
                }
                $isclosedln = false;
                if ($holdamt == 0 && ($data->lnacntstatusid == LnStatusCodeEnum::closed
                    || $data->lnacntstatusid == LnStatusCodeEnum::soldclosed)) {
                    $holdamt = $data->holdamount;
                    $isclosedln = true;
                }
                $txnamountdp = $holdamt;
                if ($dpcur <> $lncur) {
                    $dprate = GPInstCur::where('instid', $instid)
                        ->where('statusid', 1)
                        ->where('curcode', $dpcur)->first();
                    $currate1 = $dprate->avgrate;
                    $lnrate = GPInstCur::where('instid', $instid)
                        ->where('statusid', 1)
                        ->where('curcode', $lncur)->first();
                    $currate2 = $lnrate->avgrate;
                    $txnamountdp =  $txnamountdp * $currate2 / $currate1;
                }
                if ($txnamountdp > 0) {
                    DB::beginTransaction();
                    // Битүүмжээс чөлөөлөх
                    $p = new TxnJrnlEntity();
                    $p->setTxncode('dp800003');
                    $p->setTxnAcntCode($data->dpacntno);
                    $p->setOrgjrno($data->jrno);
                    $p->setCurCode($data->dpcurcode);
                    $p->setTxnDesc('Автомат битүүмж чөлөөлөв (BOD)');
                    $p->setSourcecode(1);
                    $p->setTxnAmount(0);
                    $service->doUnHoldAcntTxn($p)->jsonSerialize();
                    if (!$isclosedln) {
                        // Битүүмж үүсгэх
                        $p->setTxncode('dp800002');
                        $p->setTxnDesc('Автомат битүүмж үүсгэв (BOD)');
                        $p->setTxnAmount($txnamountdp);
                        $p->setHoldInittype(1);
                        $p->setHoldtype(2);
                        $p->setMorLoanAcnt($data->lnacntno);
                        $service->doHoldAcntTxn($p)->jsonSerialize();
                    }
                    $step->succount = $step->succount + 1;
                    DB::commit();
                }
            } catch (MeException $ex) {
                DB::rollBack();
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    throw new Exception($ex->getMessage());
                }
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
    /**
     * Барьцаа хөрөнгийн зээлд үүрэг хүлээж буй дүнг бууруулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800123($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $princbal = 0;
        $newamtmor = 0;
        $newamtlon = 0;
        $newpermor = 0;
        $newperlon = 0;
        $newobamount = 0;
        $newobpercent = 0;
        $acntmorid = 0;
        $morno = 0;
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800123',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $lnservice->AdjustMorObAmt($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                DB::beginTransaction();
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = null;
                $eodlogs['errtype'] = null;
                //Дүн тооцоолох
                $txnamount = $data->txnamount;
                $amtmor = $data->amtmor;
                $amtlon = $data->amtlon;
                $obamount = $data->obamount;
                $princbal = $data->princbal;
                $costamount = $data->costamount;
                $acntmorid = $data->id;
                $morno = $data->morno;
                $newobamount = 0;
                $newobpercent = 0;
                $newamtmor = 0;
                $newamtlon = 0;
                $newpermor = 0;
                $newperlon = 0;
                if ($costamount == 0) {
                    $this->error('RC000236', ['acntno' => $data->acntno]);
                }
                // "Барьцаа хөрөнгийн үнэлгээний дүн 0 байна."

                if ($princbal <= 0) {
                    $newpermor = 0;
                    $newamtmor = 0;
                    $newamtlon = 0;
                    $newperlon = 0;
                    $newobamount = $obamount - $txnamount;
                    $newobpercent = $newobamount * 100 / $costamount;
                    if ($newobamount < 0) {
                        $newobamount = 0;
                        $newobpercent = 0;
                    }
                    if ($newobpercent > 100) {
                        $newobamount =  $costamount;
                        $newobpercent = 100;
                    }
                } else {
                    if ($amtlon - $txnamount >= 0) {
                        //Утгууд тооцоолох
                        // $newpermor = $princbal * 100 / $costamount;
                        // $newamtmor = $costamount * $newpermor / 100;
                        $newamtmor = $amtlon - $txnamount;
                        $newamtlon = $amtlon - $txnamount;
                        if ($newamtmor < 0) {
                            $newamtmor = 0;
                            $newpermor = 0;
                        }
                        if ($newamtlon < 0) {
                            $newamtlon = 0;
                            $newperlon = 0;
                        }
                        $newpermor = $newamtmor * 100 / $costamount;
                        $newperlon = $newamtlon * 100 / $princbal;
                        if ($newpermor > 100) {
                            $newamtmor = $costamount;
                            $newpermor = 100;
                        }

                        if ($newperlon > 100) {
                            $newamtlon = $princbal;
                            $newperlon = 100;
                        }
                        $newobamount = $obamount - $txnamount;
                        $newobpercent = $newobamount * 100 / $costamount;
                        if ($newobamount < 0) {
                            $newobamount = 0;
                            $newobpercent = 0;
                        }
                        if ($newobpercent > 100) {
                            $newobamount = $costamount;
                            $newobpercent = 100;
                        }
                    } else {
                        //Дараагийн барьцаа хөрөнгийн жагсаалт авч ирэх
                        $lnmors = $lnservice->AdjustNextMorObAmt($data->acntno, $instid);
                        $resttxnamt = $txnamount;
                        foreach ($lnmors as $lnmor) {
                            if ($lnmor->amtlon - $resttxnamt < 0) {
                                $newamtlon = 0;
                                $newperlon = 0;
                                $newpermor = 0;
                                $newamtmor = 0;
                                $amountToApply = min($resttxnamt, $lnmor->amtlon);
                                $newobamount = $lnmor->obamount - $amountToApply;
                                $newobpercent = $newobamount * 100 / $lnmor->costamount;
                                if ($newobamount < 0) {
                                    $newobamount = 0;
                                    $newobpercent = 0;
                                }
                                if ($newobpercent > 100) {
                                    $newobamount =  $lnmor->costamount;
                                    $newobpercent = 100;
                                }
                                $chdlnmor = LnMor::where('instid', $instid)->where('morno', $lnmor->morno)->first();
                                if ($chdlnmor) {
                                    $chvalues = 'Үүрэг хүлээж буй нийт дүн: ' . $chdlnmor->obamount . ' => ' . $newobamount .
                                        ', Үүрэг хүлээж буй нийт хувь: ' . $chdlnmor->obpercent . ' => ' . $newobpercent;
                                    $chdlnmor->obamount = $newobamount;
                                    $chdlnmor->obpercent = $newobpercent;
                                    $chdlnmor->updated_by = $userid;
                                    $chdlnmor->save();
                                    // барьцаа хөрөнгийн холбоосын өөрчлөлт
                                    $morlink = LnAccountMor::where('instid', $instid)->where('id', $lnmor->id)->first();
                                    if ($morlink) {
                                        $morlink->amtmor = $newamtmor;
                                        $morlink->permor = $newpermor;
                                        $morlink->amtlon = $newamtlon;
                                        $morlink->perlon = $newperlon;
                                        $morlink->updated_by = $userid;
                                        $morlink->save();
                                    }

                                    $lnhist = LnMorHist::where('instid', $instid)
                                        ->where('morno', $chdlnmor->morno)
                                        ->where('txndate', $txndate)
                                        ->where('statusid', 1)->first();
                                    if (empty($lnhist)) {
                                        // барьцаа хөрөнгийн өөрчлөлтийг LN_Mor_Hist бичэв
                                        $histData = [
                                            'morno' => $chdlnmor->morno,
                                            'txndate' => $txndate,
                                            'regno' => $chdlnmor->regno,
                                            'changedesc' => 'Автомат (SOD) өөрчлөв',
                                            'certno' => $chdlnmor->certno,
                                            'changevalues' => $chvalues,
                                            'postdate' => getNow(),
                                            'statusid' => 1,
                                            'instid' => $instid,
                                            'created_by' => $userid,
                                            'updated_by' => $userid,
                                        ];

                                        LnMorHist::create($histData);
                                    }
                                }
                                $resttxnamt = $resttxnamt - $lnmor->amtlon;
                            } else {
                                $newamtlon = $lnmor->amtlon - $resttxnamt;
                                $newperlon = $newamtlon * 100 / $princbal;
                                if ($newamtlon < 0) {
                                    $newamtlon = 0;
                                    $newperlon = 0;
                                }

                                if ($newperlon > 100) {
                                    $newamtlon = $princbal;
                                    $newperlon = 100;
                                }
                                $newamtmor = $lnmor->amtlon - $resttxnamt;
                                // $newamtmor = $lnmor->costamount * $newpermor / 100;
                                $newpermor = $newamtmor * 100 / $lnmor->costamount;
                                if ($newamtmor < 0) {
                                    $newamtmor = 0;
                                    $newpermor = 0;
                                }
                                if ($newpermor > 100) {
                                    $newamtmor = $lnmor->costamount;
                                    $newpermor = 100;
                                }
                                $newobamount = $lnmor->obamount - $resttxnamt;
                                $newobpercent = $newobamount * 100 / $lnmor->costamount;
                                if ($newobamount < 0) {
                                    $newobamount = 0;
                                    $newobpercent = 0;
                                }

                                if ($newobpercent > 100) {
                                    $newobamount =  $lnmor->costamount;
                                    $newobpercent = 100;
                                }

                                $acntmorid = $lnmor->id;
                                $morno = $lnmor->morno;
                                break;
                            }
                        }
                    }
                }
                //Барьцаа хөрөнгийн өөрчлөлтийг хадгалах

                $chdlnmor = LnMor::where('instid', $instid)->where('morno', $morno)->first();
                if ($chdlnmor) {
                    $chvalues = 'Үүрэг хүлээж буй нийт дүн: ' . $chdlnmor->obamount . ' => ' . $newobamount .
                        ', Үүрэг хүлээж буй нийт хувь: ' . $chdlnmor->obpercent . ' => ' . $newobpercent;
                    $chdlnmor->obamount = $newobamount;
                    $chdlnmor->obpercent = $newobpercent;
                    $chdlnmor->updated_by = $userid;
                    $chdlnmor->save();
                    // барьцаа хөрөнгийн холбоосын өөрчлөлт
                    $morlink = LnAccountMor::where('instid', $instid)->where('id', $acntmorid)->first();
                    if ($morlink) {
                        $morlink->amtmor = $newamtmor;
                        $morlink->permor = $newpermor;
                        $morlink->amtlon = $newamtlon;
                        $morlink->perlon = $newperlon;
                        $morlink->updated_by = $userid;
                        $morlink->save();
                    }

                    $lnhist = LnMorHist::where('instid', $instid)
                        ->where('morno', $chdlnmor->morno)
                        ->where('txndate', $txndate)
                        ->where('statusid', 1)->first();
                    if (empty($lnhist)) {
                        // барьцаа хөрөнгийн өөрчлөлтийг LN_Mor_Hist бичэв
                        $histData = [
                            'morno' => $chdlnmor->morno,
                            'txndate' => $txndate,
                            'regno' => $chdlnmor->regno,
                            'changedesc' => 'Автомат (SOD) өөрчлөв',
                            'certno' => $chdlnmor->certno,
                            'changevalues' => $chvalues,
                            'postdate' => getNow(),
                            'statusid' => 1,
                            'instid' => $instid,
                            'created_by' => $userid,
                            'updated_by' => $userid,
                        ];

                        LnMorHist::create($histData);
                    }
                }
                $step->succount = $step->succount + 1;
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
    /**
     * Данс нь хаагдсан Барьцаа хөрөнгийн зээлд үүрэг хүлээж буй дүнг бууруулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800124($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $princbal = 0;
        $newamtmor = 0;
        $newamtlon = 0;
        $newpermor = 0;
        $newperlon = 0;
        $newobamount = 0;
        $newobpercent = 0;
        //2. Зээл хаахад барьцааны төлөвийг чөлөөлсөн болгох тохиргоог шалгах
        $isdo = GPInstGp::where('instid', $instid)
            ->where('itemname', 'morStatChng')->where('itemvalue', '1')->first();
        if (!empty($isdo)) {
            $chdlnmor_morstatus = true;
        } else {
            $chdlnmor_morstatus = false;
        }
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800124',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $lnservice->ClosedLnMorObAmt($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                DB::beginTransaction();
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = null;
                $eodlogs['errtype'] = null;
                //Дүн тооцоолох

                $amtmor = $data->amtmor;
                $amtlon = $data->amtlon;
                $obamount = $data->obamount;
                $princbal = $data->princbal;
                $costamount = $data->costamount;
                if ($princbal <= 0) {
                    $newpermor = 0;
                    $newamtmor = 0;
                    $newamtlon = 0;
                    $newperlon = 0;
                    $newobamount = $obamount - $amtlon;
                    $newobpercent = $costamount == 0 ? 0 : $newobamount * 100 / $costamount;
                    if ($newobamount < 0) {
                        $newobamount = 0;
                        $newobpercent = 0;
                    }
                    if ($newobpercent > 100) {
                        $newobamount =  $costamount;
                        $newobpercent = 100;
                    }
                }
                //Барьцаа хөрөнгийн өөрчлөлтийг хадгалах

                $chdlnmor = LnMor::where('instid', $instid)->where('morno',  $data->morno)->first();
                if ($chdlnmor) {
                    $chvalues = 'Үүрэг хүлээж буй нийт дүн: ' . $chdlnmor->obamount . ' => ' . $newobamount .
                        ', Үүрэг хүлээж буй нийт хувь: ' . $chdlnmor->obpercent . ' => ' . $newobpercent;
                    $chdlnmor->obamount = $newobamount;
                    $chdlnmor->obpercent = $newobpercent;
                    $chdlnmor->updated_by = $userid;
                    $hasActiveLoan = LnAccountMor::where('ln_account_mor.instid', $instid)
                        ->where('ln_account_mor.morno', $data->morno)
                        ->where('ln_account_mor.statusid', 1)
                        ->join('ln_account', function ($join) {
                            $join->on('ln_account.instid', '=', 'ln_account_mor.instid')
                                ->on('ln_account.acntno', '=', 'ln_account_mor.acntno');
                        })
                        ->whereBetween('ln_account.statusid', [2, 8])
                        ->exists();
                    if ($chdlnmor_morstatus && $newobamount == 0 && !$hasActiveLoan) {
                        $chdlnmor->morstatus = 1;
                    }
                    $chdlnmor->save();
                    // барьцаа хөрөнгийн холбоосын өөрчлөлт
                    $morlink = LnAccountMor::where('instid', $instid)->where('id', $data->id)->first();
                    if ($morlink) {
                        $morlink->amtmor = $newamtmor;
                        $morlink->permor = $newpermor;
                        $morlink->amtlon = $newamtlon;
                        $morlink->perlon = $newperlon;
                        $morlink->updated_by = $userid;
                        $morlink->save();
                    }

                    $lnhist = LnMorHist::where('instid', $instid)
                        ->where('morno', $chdlnmor->morno)
                        ->where('txndate', $txndate)
                        ->where('statusid', 1)->first();
                    if (empty($lnhist)) {
                        // барьцаа хөрөнгийн өөрчлөлтийг LN_Mor_Hist бичэв
                        $histData = [
                            'morno' => $chdlnmor->morno,
                            'txndate' => $txndate,
                            'regno' => $chdlnmor->regno,
                            'changedesc' => 'Автомат (SOD) өөрчлөв',
                            'certno' => $chdlnmor->certno,
                            'changevalues' => $chvalues,
                            'postdate' => getNow(),
                            'statusid' => 1,
                            'instid' => $instid,
                            'created_by' => $userid,
                            'updated_by' => $userid,
                        ];

                        if ($chdlnmor_morstatus && $newobamount == 0 && !$hasActiveLoan) {
                            $histData['morstatus'] = 1;
                        }
                        LnMorHist::create($histData);
                    }
                }
                $step->succount = $step->succount + 1;
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
    /**
     * Өмнөх өдрийн үлдэгдэл хадгалах функц
     * Үүнийг зөвхөн 31ны өдөр л дуудаж ажиллуулна. тэнцүү хоногт сарын хүү тооцлолд хэрэг болж байгаа юм.
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800016($step)
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800016',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $date = Carbon::createFromFormat('Y-m-d', $txndate);
        $day = $date->day;
        try {
            if ($day == 31) {
                $datas = LnAccount::where('instid', $instid)->where('statusid', '>', 2)->update([
                    'prevprincbal' => DB::raw('tmp_princbal'),
                    'prevdueamount' => DB::raw('tmp_dueamount'),
                    'updated_by' => $userid
                ]);
                $step->allcount = $datas;
                $step->succount = $datas;
            }
        } catch (\Throwable $th) {
            $eodlogs['errtype'] = 'D';
            $eodlogs['errdesc'] = $th->getMessage();
            throw $th;
        } finally {
            if (isset($eodlogs['errtype'])) {
                if (strlen($eodlogs['errdesc']) > 2000) {
                    $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                }
                AdEodLogDetail::create($eodlogs);
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Худалдсан зээлийн үлдэгдэл тэнцэлийн гадуур тохируулах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800122($step)
    {

        $lastitem = $this->getLastEodStep($step);
        $lnservice = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $txnservice = new LnTxnService();
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800122',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $lnservice->CeCtAcntTxn($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        // Log::debug(count($datas));
        // Log::debug($datas);
        // return;
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                $eodlogs['errtype'] = null;
                $tP = new TxnJrnlEntity();
                $tP->setTxnAcntCode($data->acntno);
                $tP->setCurCode($data->curcode);
                $tP->setTxnDesc('Худалдсан зээлийн тэнцэлийн гадуур үлдэгдэл залруулав. (EOD)');
                $tP->setSourcecode(1);
                $tP->setRate(1);
                $tP->setInstid($instid);
                $tP->setPostdate(getNow());
                $tP->setUserid($userid);
                $tP->setTxncode('ln800018');
                $resp = $txnservice->doTxnLineAndCollCt($tP);
                if (!empty($resp->getTxnJrno())) {
                    $step->succount = $step->succount + 1;
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
    }
    /**
     * Барьцаа хөрөнгийн сарын эцсийн түүх хадгалах
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800139($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new LnEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800139',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->LnMorMonthlyHistDel($txndate, $lastitem, $instid);
            $datas = $service->LnMorMonthlyHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            $chunks = array_chunk($datas->toArray(), 25);
            foreach ($chunks as $chunk) {
                LnMorHistMonthly::insert($chunk);
            }

            $step->allcount = count($datas);
            $step->succount = $step->allcount;
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
