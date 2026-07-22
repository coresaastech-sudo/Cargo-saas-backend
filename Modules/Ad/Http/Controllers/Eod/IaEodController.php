<?php

namespace Modules\Ad\Http\Controllers\Eod;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Ad\Entities\AdResAccountBal;
use Modules\Ad\Entities\Views\VwAdIaResAccountBalCalc;
use Modules\Ad\Http\Services\IaEodService;
use Modules\Ca\Entities\CaCashBalHist;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Enums\EodContinueResponseCodesEnum;
use Modules\Ia\Entities\IaAccount;
use Modules\Ia\Entities\IaAccountHist;
use Modules\Ia\Entities\IaDeAccount;
use Modules\Ia\Entities\IaDeAccountHist;
use Modules\Ia\Entities\IaDeAccountType;
use Modules\Ia\Entities\IaDeSchd;
use Modules\Ia\Entities\IaRecPay;
use Modules\Ia\Entities\IaRecPayHist;
use Modules\Ia\Http\Services\IaDepreciationService;
use Modules\Ln\Entities\LnAccountType;
use Modules\Tr\Entities\FinTxnEntity;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Services\IaDeTxnService;
use Modules\Tr\Http\Services\IaTxnService;
use TypeError;

class IaEodController extends CoreController
{

    /**
     * Дотоодын дансны үлдэгдэл түр хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800054($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800054',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CreateTmpIaBals($txndate, $lastitem, $instid);
        // Log::debug([count($datas), $datas]);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                IaAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'tmp_currentbal' =>  DB::raw('currentbal'),
                        'tmp_statusid' =>  DB::raw('statusid'),
                        'tmp_brchno' =>  DB::raw('brchno'),
                        'tmp_typecode' =>  DB::raw('typecode'),
                        'tmp_clscode' =>  DB::raw('clscode'),
                        'tmp_clscodetrm' =>  DB::raw('clscodetrm'),
                        'tmp_clscodeqlt' =>  DB::raw('clscodeqlt'),
                        'updated_by' =>  $userid
                    ]);
                $step->succount = $step->succount + 1;
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
        // $this->error('RC000005');
    }

    /**
     * Хорогдуулалтын дансны үлдэгдэл түр хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800133($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $baseLog = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800133',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CreateTmpIaDeBals($txndate, $lastitem, $instid);
        // Log::debug([count($datas), $datas]);
        if (!$lastitem) {
            $step->allcount = $datas->count();
        }

        foreach ($datas as $data) {
            $eodlogs = $baseLog;
            $eodlogs['acntno'] = $data->acntno;
            $eodlogs['acntbrchno'] = $data->brchno;
            try {
                IaDeAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)
                    ->update([
                        'tmp_irr' =>  DB::raw('irr'),
                        'tmp_currentbal' =>  DB::raw('currentbal'),
                        'tmp_totaldeprbal' =>  DB::raw('totaldeprbal'),
                        'tmp_depramount' =>  DB::raw('depramount'),
                        'updated_by' =>  $userid
                    ]);
                $step->succount = $step->succount + 1;
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
                    throw $th;
                }
            }
        }
        // $this->error('RC000005');
    }
    /**
     * Авлагын үлдэгдэл түр хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800137($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $baseLog = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800137',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CreateTmpIaRecBals($txndate, $lastitem, $instid);
        // Log::debug([count($datas), $datas]);
        if (!$lastitem) {
            $step->allcount = $datas->count();
        }

        foreach ($datas as $data) {
            $eodlogs = $baseLog;
            $eodlogs['acntno'] = $data->recpayno;
            $eodlogs['acntbrchno'] = $data->brchno;
            try {
                IaRecPay::where('recpayno', $data->recpayno)
                    ->where('instid', $instid)
                    ->update([
                        'tmp_amount' =>  DB::raw('amount'),
                        'tmp_balance' =>  DB::raw('balance'),
                        'tmp_insurancepaidamount' =>  DB::raw('insurancepaidamount'),
                        'tmp_statusid' =>  DB::raw('statusid'),
                        'tmp_clscode' =>  DB::raw('clscode'),
                        'tmp_clscodetrm' =>  DB::raw('clscodetrm'),
                        'tmp_clscodeqlt' =>  DB::raw('clscodeqlt'),
                        'updated_by' =>  $userid
                    ]);
                $step->succount = $step->succount + 1;
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
                    throw $th;
                }
            }
        }
        // $this->error('RC000005');
    }

    /**
     * Дотоодын дансны түүх хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800120($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800120',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->IaAcntHistDel($txndate, $lastitem, $instid);
            $datas = $service->IaAcntHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            $chunks = array_chunk($datas->toArray(), 25);
            foreach ($chunks as $chunk) {
                IaAccountHist::insert($chunk);
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

    /**
     * Кассын дансны түүх хадгалах
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800134($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800134',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->CaAcntHistDel($txndate, $lastitem, $instid);
            $datas = $service->CaAcntHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            $chunks = array_chunk($datas->toArray(), 25);
            foreach ($chunks as $chunk) {
                CaCashBalHist::insert($chunk);
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
    /**
     * Авлагын түүх хадгалах
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800138($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800138',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->RecPayHistDel($txndate, $lastitem, $instid);
            $datas = $service->RecPayAcntHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            $chunks = array_chunk($datas->toArray(), 25);
            foreach ($chunks as $chunk) {
                IaRecPayHist::insert($chunk);
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
    /**
     * Хорогдуулалтын дансны түүх хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800132($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800132',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->DeAcntHistDel($txndate, $lastitem, $instid);
            $datas = $service->DeAcntHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            $chunks = array_chunk($datas->toArray(), 25);
            foreach ($chunks as $chunk) {
                IaDeAccountHist::insert($chunk);
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

    /**
     * Орлого, зардлын данс хаах /Жилийн эцэст/
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800092($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800092',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaAcntIncomeExpense($txndate, $lastitem, $instid, $userid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        $iatxnservice = new IaTxnService();

        $jrItem = new TxnItemEntity();
        foreach ($datas as $key => $acnt) {
            try {
                DB::beginTransaction();
                $p = new FinTxnEntity();
                $eodlogs['acntno'] = $acnt->acntno;
                $eodlogs['acntbrchno'] = $acnt->brchno;
                $p->setIsPreview(0);
                $p->setJrno(CoreService::getNextJrno());
                $p->setTxndate(CoreService::getTxnDate($instid));

                $p->setPostdate(Carbon::now());
                $p->setCorr(0);
                $p->setTxnAcntMod(AccountTypeEnum::ia);
                $p->setTxnAcntCode($acnt->acntno);
                $p->setCurCode($acnt->curcode);
                $p->setProdcode($acnt->typecode);
                $p->setRate(1);
                $p->setChid(1);
                $p->setContRate($p->getRate());

                $p->setIntbal(0);
                $p->setGl($acnt->gl);
                // $p->setContgl();
                $p->setSourcecode(1);
                $p->setUserid($userid);
                $p->setRtypecode(1);
                $p->setIscash(0);

                if ($acnt->currentbal > 0) {
                    $p->setTxncode('ia903096');
                    $p->setTxntype(0);
                    $p->setTxnAmount($acnt->tmp_currentbal);
                    $p->setTxnDesc('Орлогын данс тэглэв.(EOY)');
                } else {
                    $p->setTxncode('ia903095');
                    $p->setTxntype(1);
                    $p->setTxnAmount($acnt->tmp_currentbal * -1);
                    $p->setTxnDesc('Зардлын данс тэглэв.(EOY)');
                }
                $p->setAcntbal($acnt->currentbal - $acnt->tmp_currentbal);
                $p->setContAmount($p->getTxnAmount());

                $iatxnservice->insertIaTxnDb($p, $jrItem);
                IaAccount::where('acntno', $acnt->acntno)
                    ->where('instid', $instid)->update([
                        'currentbal' => DB::raw('currentbal - tmp_currentbal'),
                        'updated_by' => $userid,
                        'lasttellertxndate' => $p->getTxndate(),
                    ]);
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
     * Өмчлөх бусад хөрөнгийн ангилал шилжүүлэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800135($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800135',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaOfClsChangeList($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        $iatxnservice = new IaTxnService();

        $jrItem = new TxnItemEntity();
        foreach ($datas as $key => $acnt) {
            try {
                $p = new TxnJrnlEntity();
                $eodlogs['acntno'] = $acnt->acntno;
                $eodlogs['acntbrchno'] = $acnt->brchno;
                $eodlogs['errtype'] = null;
                $p->setTxnAcntCode($acnt->acntno);
                $p->setClscode($acnt->newclscodetrm);
                $p->setIsPreview(0);
                $p->setSourcecode(1);
                $p->setUserid($userid);
                $p->setRate(1);
                $p->setInstid($instid);
                $p->setPostdate(getNow());
                $p->setTxndate($txndate);
                $p->setTxnDesc('ӨБ Хөрөнгийн ангилал шилжүүлэв (EOD)');

                $iatxnservice->ia800005($p, $jrItem);
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
     * Өмчлөх бусад хөрөнгийн Эрсдлийн санг авто байгуулах алхам
     * ad800136
     *
     * @param  mixed $step
     * @return void
     */
    public function ad800136($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800136',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        try {
            $datas = VwAdIaResAccountBalCalc::where('instid', $instid)
                ->where('autoriskfund', 1)
                ->orderBy('acntno')->get();

            if (!$lastitem) {
                $step->allcount = count($datas);
            }

            foreach ($datas as $data) {
                try {
                    $eodlogs['acntno'] = $data->acntno;
                    $eodlogs['acntbrchno'] = $data->brchno;
                    $eodlogs['errtype'] = null;
                    $accountBal = AdResAccountBal::where('acntno', $data->acntno)
                        ->where('acnttype', $data->resacnttype ?? 'IA')
                        ->where('instid', $instid)
                        ->where('statusid', 0)
                        ->first();
                    if (empty($data->res_acntno)) {
                        $this->error("RC000065");
                    }
                    if ($accountBal) {
                        AdResAccountBal::where('acntno', $data->acntno)
                            ->where('acnttype', $data->resacnttype ?? 'IA')
                            ->where('instid', $instid)
                            ->where('statusid', 0)->update([
                                'acntno' => $data->acntno,
                                'acnttype' => $data->resacnttype ?? 'IA',
                                'balance' => $data->currentbal,
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
                            'acnttype' => $data->resacnttype ?? 'IA',
                            'balance' => $data->currentbal,
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
                        ->where('acnttype', $data->resacnttype ?? 'IA')
                        ->where('instid', $instid)
                        ->where('statusid', 0)
                        ->first();
                    $p = new TxnJrnlEntity();
                    if ($accountBal->amount < 0) {
                        $p->setTxnAcntCode($accountBal->res_acntno);
                        $p->setContAcntCode($accountBal->cont_acntno);
                        $p->setTxnDesc('ӨБХ Эрсдэлийн сангийн буцаалт (EOM)' . $accountBal->acntno . ' ' . $accountBal->rescls . '->' . $accountBal->clscode);
                        $p->setTxnAmount($accountBal->amount * -1);
                    } else {
                        $p->setTxnAcntCode($accountBal->cont_acntno);
                        $p->setContAcntCode($accountBal->res_acntno);
                        $p->setTxnDesc('ӨБХ Эрсдэлийн сангийн гүйлгээ (EOM)' . $accountBal->acntno . ' ' . $accountBal->rescls . '->' . $accountBal->clscode);
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
     * Авлага ангилал шилжүүлэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800126($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800126',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaRecClsChangeList($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        $iatxnservice = new IaTxnService();

        $jrItem = new TxnItemEntity();
        foreach ($datas as $key => $acnt) {
            try {
                $p = new TxnJrnlEntity();
                $eodlogs['acntno'] = $acnt->recpayno;
                $eodlogs['acntbrchno'] = $acnt->brchno;
                $eodlogs['errtype'] = null;
                $p->setTxnAcntCode($acnt->recpayno);
                $p->setClscode($acnt->newclscodetrm);
                $p->setIsPreview(0);
                $p->setSourcecode(1);
                $p->setUserid($userid);
                $p->setInstid($instid);
                $p->setTxnDesc('Авлагын ангилал шилжүүлэв (EOD)');

                $iatxnservice->ia800002($p, $jrItem);
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
     * Авлагын авто төлөлт
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800117($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800117',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaRecPaymentList($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        $iatxnservice = new IaTxnService();

        $jrItem = new TxnItemEntity();
        foreach ($datas as $key => $acnt) {
            try {
                $p = new TxnJrnlEntity();
                $eodlogs['acntno'] = $acnt->recpayno;
                $eodlogs['acntbrchno'] = $acnt->brchno;
                $eodlogs['errtype'] = null;
                $p->setTxnAcntCode($acnt->recpayno);
                $p->setIsPreview(0);
                $p->setSourcecode(1);
                $p->setUserid($userid);
                $p->setTxnAmount($acnt->txnamount);
                $p->setType('C');
                $p->setContAcntMod($acnt->tacnttype);
                $p->setCurCode($acnt->curcode);
                $p->setContAcntCode($acnt->contacntno);
                $p->setRate(1);
                $p->setTxnDesc('Авлага авто төлөлт хийв. (EOD)');
                $p->setPostdate(getNow());
                $p->setTxndate($txndate);
                $p->setInstid($instid);

                $iatxnservice->ia800001($p, $jrItem);
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
     * Хорогдуулалтын дансны хуваарь үүсгэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800128($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800128',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaDeprSchdAcntList($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        $iadepreciationservice = new IaDepreciationService();
        foreach ($datas as $key => $acnt) {
            try {
                DB::beginTransaction();
                $eodlogs['acntno'] = $acnt->acntno;
                $eodlogs['acntbrchno'] = $acnt->brchno;
                $eodlogs['errtype'] = null;
                $acntprod = LnAccountType::where('prodcode', $acnt->linked_acntprodcode)
                    ->where('instid', $instid)
                    ->where('statusid', 1)->first();
                $product = IaDeAccountType::where('prodcode', $acnt->prodcode)
                    ->where('instid', $instid)
                    ->where('statusid', 1)->first();
                $respirr = $iadepreciationservice->calcCashFlowAndIRR(
                    $acnt->linked_acntno,
                    $acnt->linked_acntmod,
                    $acntprod,
                    $acnt->currentbal,
                    Carbon::parse($txndate),
                    $product,
                    $instid
                );
                if ($respirr['success']) {
                    $deprSchd = $iadepreciationservice->calculateDeprSchedule(
                        $respirr['data'],
                        $acnt->currentbal,
                        $respirr['irr'],
                        Carbon::parse($txndate),
                        $acntprod
                    );
                } else {
                    $this->error($respirr['message']);
                }

                $deacccount = IaDeAccount::where('acntno', $acnt->acntno)
                    ->where('instid', $instid)
                    ->first();
                $deacccount->activeschdid = $acnt->activeschdid + 1;
                $deacccount->crtschd = 0;
                $tmpinsert = [];
                foreach ($deprSchd as $key => $schd) {
                    $tmpinsert[] = [
                        'acntno' => $deacccount->acntno,
                        'schdid' => $deacccount->activeschdid,
                        'itemno' => $key + 1,
                        'deprday' => $schd['deprday'],
                        'depramount' => $schd['depramount'],
                        'deprdailyamount' => $schd['deprdailyamount'],
                        'islast' => count($deprSchd) == $key + 1 ? 1 : 0,
                        'deprtheorbal' => $schd['deprtheorbal'],
                        'statusid' => 1,
                        'instid' => $instid,
                        'created_by' => $userid,
                        'created_at' => Carbon::now(),
                    ];
                }

                IaDeSchd::insert($tmpinsert);
                $step->succount = $step->succount + 1;
                $deacccount->save();
                DB::commit();
            } catch (MeException $ex) {
                Log::error($ex);
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
                    DB::rollBack();
                    if (strlen($eodlogs['errdesc']) > 2000) {
                        $eodlogs['errdesc'] = substr($eodlogs['errdesc'], 0, 2000);
                    }
                    AdEodLogDetail::create($eodlogs);
                }
            }
        }
    }
    /**
     * Хорогдуулалтын гүйлгээ хийх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800129($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800129',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaDeprectionList($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        $iadetxnservice = new IaDeTxnService();

        $jrItem = new TxnItemEntity();
        foreach ($datas as $key => $acnt) {
            try {
                $eodlogs['acntno'] = $acnt->retailacntno;
                $eodlogs['acntbrchno'] = $acnt->acntbrchno;
                $eodlogs['errtype'] = null;

                $p = new TxnJrnlEntity();
                $p->setTxnAcntCode($acnt->retailacntno);
                $p->setAcntbrchno($acnt->acntbrchno);
                $p->setIsPreview(0);
                $p->setSourcecode(1);
                $p->setUserid($userid);
                $p->setTxnAmount($acnt->txnamount);
                $p->setCurCode($acnt->curcode);
                $p->setRate(1);
                $p->setInstid($instid);
                $p->setPostdate(getNow());
                $p->setUserid($userid);
                $p->setTxndate($txndate);
                $p->setIscash(0);
                $p->setTxntype($acnt->txntype);

                if ($acnt->iserror == 1) {
                    $p->setTxnDesc("Хувиар дуусахаас өмнө үлдэгдэл дуусав, Данс: $acnt->retailacntno");
                } else {
                    $p->setTxnDesc("Хорогдуулалтын гүйлгээ. (EOD)");
                }
                $iadetxnservice->de906051($p, $jrItem);
                $step->succount = $step->succount + 1;
            } catch (MeException $ex) {
                $eodlogs['errtype'] = 'A';
                $eodlogs['errdesc'] = $ex->getMessage();
                if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                    $eodlogs['errtype'] = 'D';
                    Log::error($ex);
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
     * Өдрийн хорогдуулалтын дүн тодорхойлох
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800130($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new IaEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800130',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        $datas = $service->IaDeprAmountList($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        $eodlogs['errtype'] = null;
        foreach ($datas as $key => $acnt) {
            try {
                $eodlogs['acntno'] = $acnt->acntno;
                $eodlogs['acntbrchno'] = $acnt->brchno;
                $eodlogs['errtype'] = null;

                IaDeAccount::where('acntno', $acnt->acntno)
                    ->where('instid', $instid)
                    ->update([
                        'depramount' => $acnt->deprdailyamount
                    ]);

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
}
