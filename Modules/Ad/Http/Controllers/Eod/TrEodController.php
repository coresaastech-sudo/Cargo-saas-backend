<?php

namespace Modules\Ad\Http\Controllers\Eod;

use App\Exceptions\MeException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Ad\Http\Services\AdEbarimtService;
use Modules\Ad\Http\Services\TrEodService;
use Modules\Gp\Entities\GPInstCur;
use Modules\Gp\Entities\GPInstCurRateHist;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Enums\EodContinueResponseCodesEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ia\Entities\IaPosition;
use Modules\Tr\Entities\TrCurRateHist;
use Modules\Tr\Entities\TrGlretailBal;
use Modules\Tr\Entities\TrGlretailEntry;
use Modules\Tr\Entities\TrJournal;

class TrEodController extends CoreController
{
    /**
     * Валютын ханш хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800090($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new TrEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800090',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        //Дундаж ханшийн түүх цэвэрлэх
        $service->AvgRateDel($txndate, $lastitem, $instid);
        //Дундаж ханшийн түүх
        $datas = $service->AvgRateSelect($txndate, $lastitem, $instid);
        // Log::debug($datas);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->curcode;
                TrCurRateHist::create([
                    'date' =>  $data->date,
                    'curcode' =>  $data->curcode,
                    'avgrate' =>  $data->avgrate,
                    'avgrateend' =>  $data->avgrateend,
                    'statusid' =>  1,
                    'instid' =>  $instid,
                    'created_by' =>  $userid,
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
        //Өдрийн ханшийн түүх
        $histdatas = $service->DailyRateSelectHist($txndate, $lastitem, $instid);
        if (count($histdatas) == 0) {
            $dailydatas = $service->DailyRateSelect($txndate, $lastitem, $instid);
            if (!$lastitem) {
                $step->allcount = count($dailydatas);
            }
            foreach ($dailydatas as $data) {
                try {
                    $eodlogs['acntno'] = $data->rtypecode;
                    GPInstCurRateHist::create([
                        'rtypecode' =>  $data->rtypecode,
                        'curcode' =>  $data->curcode,
                        'salerate' =>  $data->salerate,
                        'buyrate' =>  $data->buyrate,
                        'date' =>  $data->date,
                        'statusid' =>  1,
                        'instid' =>  auth()->user()->instid,
                        'created_by' =>  auth()->user()->id,
                        'updated_by' =>  auth()->user()->id
                    ]);
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
        }
    }
    /**
     * Валютын позиц хаах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800096($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new TrEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800096',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        //1. Өдрийн хаалтын ханш нь маргаашийн өглөөний дундаж ханш болгох
        GPInstCur::where('instid', $instid)->where('curcode', '<>', 'MNT')->update([
            'avgrate' => DB::raw('COALESCE(avgrateend, 1)')
        ]);
        //2. Гараар позиц хаасан байсныг reset хийх
        GPInstGp::where('instid', $instid)->where('itemname', 'PositionClosed')->update([
            'itemvalue' => 'N'
        ]);
        //3. Програмын ерөнхий параметр шалгах
        $pos = GPInstGp::where('instid', $instid)->where('itemname', 'PositionCloseUpdate')->first();
        if ($pos && $pos != 'E') {

            //4. Шинээр салбар эсвэл валют нэмэгдвэл түүний шинэ бичлэгийг Позицийн баазад нэмэх

            $datas = $service->NewBrCurPosSelect($txndate, $lastitem, $instid);

            if (!$lastitem) {
                $step->allcount = count($datas);
            }
            foreach ($datas as $data) {
                try {
                    $eodlogs['acntno'] = $data->curcode;
                    $eodlogs['acntbrchno'] = $data->brchno;
                    IaPosition::create([
                        'brchno' =>  $data->brchno,
                        'curcode' =>  $data->curcode,
                        'position' =>  0,
                        'statusid' =>  1,
                        'date' =>  $txndate,
                        'instid' => $instid,
                        'created_by' => $userid,
                        'updated_by' => $userid
                    ]);
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
            //5. Позицийн сүүлийн үлдэгдэлийг маргаашийн эхний позиц болгох
            $datas = $service->LastPosSelect($txndate, $lastitem, $instid);

            if (!$lastitem) {
                $step->allcount = count($datas);
            }
            foreach ($datas as $data) {
                try {
                    $eodlogs['acntno'] = $data->curcode;
                    $eodlogs['acntbrchno'] = $data->brchno;
                    IaPosition::where('instid', $instid)
                        ->where('brchno', $data->brchno)
                        ->where('curcode', $data->curcode)
                        ->update([
                            'brchno' =>  $data->brchno,
                            'curcode' =>  $data->curcode,
                            'position' => DB::raw("position + " . $data->currentposition),
                            'statusid' =>  1,
                            'date' =>  $txndate,
                            'instid' =>  $instid,
                            'created_by' => $userid,
                            'updated_by' => $userid
                        ]);
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
        }
    }
    /**
     * ЕД дансны үлдэгдэл тулгалтын тэйбэл бэлтгэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800093($step)
    {
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800093',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        try {
            TrGlretailBal::where('instid', $instid)
                ->where('date', $txndate)->delete();
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
     * ЕД дансны үлдэгдэл тулгалтын мэдээлэл илгээх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800094($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new TrEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800094',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $service->PostGLBal($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->gl;
                $eodlogs['acntbrchno'] = $data->brchno;
                TrGlretailBal::create([
                    'gl' =>  $data->gl,
                    'segcode' =>  $data->segcode,
                    'brchno' =>  $data->brchno,
                    'curcode' =>  $data->curcode,
                    'glcurcode' =>  $data->glcurcode,
                    'glsegcode' =>  $data->glsegcode,
                    'retailbal' =>  $data->retailbal,
                    'date' =>  $data->date,
                    'statusid' =>  1,
                    'instid' =>  $instid,
                    'created_by' =>  $userid,
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
    }
    /**
     * ЕД Поустинг хийх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800097($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new TrEodService();
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $userid = auth()->user()->id;
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800097',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];

        $datas = $service->PostingSelect($txndate, $lastitem, $instid);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }
        ini_set('max_execution_time', 480);
        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->jrno;
                $eodlogs['acntbrchno'] = $data->jritemno;
                TrGlretailEntry::create([
                    'jrno' => $data->jrno,
                    'jritemno' => $data->jritemno,
                    'tellerno' => $data->tellerno,
                    'brchno' => $data->brchno,
                    'txncode' => $data->txncode,
                    'corr' => $data->corr,
                    'txndate' => $data->txndate,
                    'postdate' => $data->postdate,
                    'txnamount' => $data->txnamount,
                    'curcode' => $data->curcode,
                    'currate' => $data->currate,
                    'gl' => $data->gl,
                    'acntbrchno' => $data->acntbrchno,
                    'segcode' => $data->segcode,
                    'retailacntno' => $data->retailacntno,
                    'retailacntmod' => $data->retailacntmod,
                    'flags' => 0,
                    'parenttxncode' => $data->parenttxncode,
                    'txndesc' => $data->txndesc,
                    'contacntmod' => $data->contacntmod,
                    'contacntno' => $data->contacntno,
                    'contcurcode' => $data->contcurcode,
                    'conttxnamount' => $data->conttxnamount,
                    'contcurrate' => $data->contcurrate,
                    'contgl' => $data->contgl,
                    'sign' => $data->sign,
                    'racntprodcode' => $data->racntprodcode,
                    'clscode' => $data->clscode,
                    'baseamount' => $data->baseamount,
                    'unitcode' => $data->unitcode,
                    'txntype' => $data->txntype,
                    'mark' => $data->mark,
                    'txnjritemno' => $data->txnjritemno,
                    'sourcecode' => $data->sourcecode,
                    'statusid' =>  1,
                    'instid' =>  $instid,
                    'created_by' =>  $userid,
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
    }
    /**
     * ЕД Поустинг хийгдсэн журнал цэвэрлэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800099($step)
    {
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800099',
            'instid' => auth()->user()->instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        try {
            $step->allcount = TrJournal::where('instid', $instid)->where('txndate', '<', $txndate)->delete();
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
     * Супервайзор өдрийн гүйлгээг түүхэнд хадгалж цэвэрлэх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800127($step)
    {
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800127',
            'instid' => $instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        try {
            DB::beginTransaction();
            // TrPendTxn -> TrPendTxnHist
            DB::insert('INSERT INTO "tr_pend_txn_hist" SELECT * FROM "tr_pend_txn" WHERE instid = ? AND txndate = ?', [$instid, $txndate]);

            // TrPendData -> TrPendDataHist
            DB::insert('INSERT INTO "tr_pend_data_hist" SELECT * FROM "tr_pend_data" WHERE instid = ? AND txndate = ?', [$instid, $txndate]);

            // Мөрийн тоог устгахдаа тоолно
            $txnCount = DB::table('tr_pend_txn')
                ->where('instid', $instid)
                ->where('txndate', $txndate)
                ->delete();

            $dataCount = DB::table('tr_pend_data')
                ->where('instid', $instid)
                ->where('txndate', $txndate)
                ->delete();

            $step->allcount = $txnCount;
            $step->succount = $step->allcount;
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
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
     * eBarimt өдрийн эцэст илгээх
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800131($step)
    {
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800131',
            'instid' => $instid,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ];
        try {
            $onlineteller = CoreService::getInstGp($instid, 'SYSTEMTELLERNUMBER');

            $user = GPInstUser::where('instid', $instid)->find(
                $onlineteller
            );

            $ebarimt_service = new AdEbarimtService($instid, $user);

            $res = $ebarimt_service->resendEbarimt($txndate, $instid);

            $step->allcount = $res['count'];
            $step->succount = $step->allcount;
            DB::commit();
        } catch (MeException $ex) {
            $eodlogs['errtype'] = 'A';
            $eodlogs['errdesc'] = $ex->getMessage();
            if (!EodContinueResponseCodesEnum::isValidValue($ex->getCode())) {
                $eodlogs['errtype'] = 'D';
                throw $ex;
            }
        } catch (\Throwable $th) {
            DB::rollBack();
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
}
