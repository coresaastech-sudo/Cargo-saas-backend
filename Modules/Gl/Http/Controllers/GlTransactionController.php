<?php

namespace Modules\Gl\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Controllers\AdInstEodController;
use Modules\Gl\Entities\GlTransaction;
use Modules\Gl\Http\Requests\GlTxnCurrRequest;
use Modules\Gl\Http\Requests\GlTxnRequest;
use Modules\Gl\Http\Services\GlProcessService;
use Modules\Gl\Http\Services\GlTxnService;
use Modules\Gp\Entities\GPInstCur;
use Modules\Gp\Entities\GPInstCurRateHist;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Entities\TxnResult;
use Modules\Tr\Http\Controllers\TxnCoreController;

class GlTransactionController extends Controller
{
    /**
     * Гүйлгээ буцаалт
     * @return array
     */
    public function gl900000(Request $request)
    {
        $validated = $this->validate($request, [
            'journal' => 'required'
        ]);
        $txnResult = new TxnResult();
        $jrItem = new TxnItemEntity();

        $txns = GlTransaction::where('journal', $validated['journal'])
            ->where('instid', auth()->user()->instid)
            ->where('correctoin', 0)
            ->get();

        if (count($txns) == 0) {
            $this->error('RC000117', ['jrno' => $validated['journal']]);
        }
        // else {
        //     if ($txns[0]->txndate != CoreService::getGlDate(auth()->user()->instid)) {
        //         $this->error('RC000222', [
        //             'field' => 'Өмнөх өдрийн гүйлгээг буцаах боломжгүй!'
        //         ]);
        //     }
        // }
        $jrno = "CR" . CoreService::getGlNextJrno();
        $txnResult->setTxnJrno($jrno);
        DB::beginTransaction();
        try {
            foreach ($txns as $key => $parameter) {
                if (round($parameter['amount'], 2) != 0) {
                    $p = new TxnJrnlEntity();
                    $p->setTxnAcntCode($parameter->account);
                    $p->setTxnAmount($parameter->amount * -1);
                    $p->setRate(null);
                    $p->setCurCode($parameter->currency);
                    $p->setContCurCode($parameter->currency);
                    $p->setTxnDesc($validated['journal'] . ' гүйлгээг буцаав.');
                    $p->setAcntbrchno($parameter->branch);
                    $p->setTxncode('gl900000');
                    $p->setMainAcntPosition('PUSH');
                    $p->setJrno($jrno);
                    $p->setCorr(1);
                    $p->setTxndate($parameter->txndate);

                    // $p->setUnit('0000');
                    $service = new GlTxnService();
                    $jrItem = $service->doTxn($p, $jrItem);
                }
            }
            GlTransaction::where('journal', $validated['journal'])
                ->where('instid', auth()->user()->instid)
                ->where('correctoin', 0)->update(['correctoin' => 1]);
            if ($p->getIsPreview() != 1) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        $txnResult->setTxnJritemNo($jrItem->getJritemno());
        $txnResult->setTxnpreview($jrItem->getTxnpreview());
        return $txnResult->jsonSerialize(true);
    }

    /**
     * Гүйлгээ
     * @return array
     */
    public function gl900001(GlTxnRequest $request)
    {
        $parameters = $request->validated();
        if ((new AdInstEodController())->isOnEodJob()) {
            $this->error('RC000108');
        }
        $txnResult = new TxnResult();
        $jrItem = new TxnItemEntity();
        try {
            $amount = 0;
            foreach ($parameters['txns'] as $key => $parameter) {
                $amount = $amount + ($parameter['amount']);
            }

            if (round($amount, 2) != 0) {
                $this->error('RC000047', [
                    'txnamount*' => $amount,
                    'rate' => '',
                    'curcode' => '',
                    'contamount*' => '0',
                    'contrate' => '',
                    'contcurcode' => '',
                ]);
            }
            $jrno = "MT" . CoreService::getGlNextJrno();
            $txnResult->setTxnJrno($jrno);
            DB::beginTransaction();
            try {
                foreach ($parameters['txns'] as $key => $parameter) {
                    if (round($parameter['amount'], 2) != 0) {
                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($parameter['acntno']);
                        $p->setTxnAmount($parameter['amount']);
                        $p->setRate($parameters['rate'] ?? null);
                        $p->setCurCode($parameters['curcode']);
                        $p->setContCurCode($parameters['curcode']);
                        $p->setTxnDesc($parameter['txndesc'] ?? "");
                        $p->setAcntbrchno($parameters['brchno']);
                        $p->setTxncode('gl900001');
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        if (isset($parameters['txndate'])) {
                            $p->setTxndate($parameters['txndate']);
                        }
                        if (isset($parameters['isclosebalance'])) {
                            $p->setIsCloseBalance($parameters['isclosebalance']);
                        }
                        // $p->setUnit('0000');
                        $service = new GlTxnService();
                        $jrItem = $service->doTxn($p, $jrItem);
                    }
                }
                if ($p->getIsPreview() != 1) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        } catch (MeException $th) {
            Log::channel('txn_log')->error($th);
            throw $th;
        } catch (Exception $ex) {
            throw $ex;
        } finally {
        }

        $txnResult->setTxnJritemNo($jrItem->getJritemno());
        $txnResult->setTxnpreview($jrItem->getTxnpreview());
        return $txnResult->jsonSerialize(true);
    }

    /**
     * Салбар валют хоорондын гүйлгээ
     * @return array
     */
    public function gl900002(GlTxnCurrRequest $request)
    {
        $parameters = $request->validated();
        $txnResult = new TxnResult();
        $jrItem = new TxnItemEntity();
        try {
            // $amount = 0;
            // foreach ($parameters['txns'] as $key => $parameter) {
            //     $amount = $amount + ($parameter['amount']);
            // }

            // if ($amount != 0) {
            //     $this->error('RC000047', [
            //         'txnamount*' => $amount,
            //         'rate' => '',
            //         'curcode' => '',
            //         'contamount*' => '0',
            //         'contrate' => '',
            //         'contcurcode' => '',
            //     ]);
            // }
            $jrno = "MT" . CoreService::getGlNextJrno();
            $txnResult->setTxnJrno($jrno);
            DB::beginTransaction();
            try {
                $amount = 0;
                $txnamount = 0;
                $conttxnamount = 0;
                foreach ($parameters['txns'] as $key => $parameter) {
                    if (round($parameter['amount'], 2) != 0) {
                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($parameter['acntno']);
                        $p->setTxnAmount($parameter['amount']);
                        $p->setRate($parameter['rate'] ?? null);
                        $p->setCurCode($parameters['curcode']);
                        $p->setContCurCode($parameters['contcurcode']);
                        $p->setTxnDesc($parameter['txndesc'] ?? "");
                        $p->setAcntbrchno($parameters['brchno']);
                        $p->setTxncode('gl900002');
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        if (isset($parameters['txndate'])) {
                            $p->setTxndate($parameters['txndate']);
                        }
                        // $p->setUnit('0000');
                        $service = new GlTxnService();
                        $jrItem = $service->doTxn($p, $jrItem);
                        $amount = $amount + $p->getBaseamount();
                        $txnamount = $txnamount + $parameter['amount'];
                    }
                }

                if ($amount == 0) {
                    $this->error('RC000042');
                }

                $contamount = 0;
                foreach ($parameters['conttxns'] as $key => $parameter) {
                    if (round($parameter['amount'], 2) != 0) {
                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($parameter['acntno']);
                        $p->setTxnAmount($parameter['amount']);
                        $p->setRate($parameter['contrate'] ?? null);
                        $p->setCurCode($parameters['contcurcode']);
                        $p->setContCurCode($parameters['curcode']);
                        $p->setTxnDesc($parameter['txndesc'] ?? "");
                        $p->setAcntbrchno($parameters['contbrchno']);
                        $p->setTxncode('gl900002');
                        $p->setMainAcntPosition('PULL');
                        $p->setJrno($jrno);
                        if (isset($parameters['txndate'])) {
                            $p->setTxndate($parameters['txndate']);
                        }

                        // $p->setUnit('0000');
                        $service = new GlTxnService();
                        $jrItem = $service->doTxn($p, $jrItem);
                        $contamount = $contamount + $p->getBaseamount();
                        $conttxnamount = $conttxnamount + $parameter['amount'];
                    }
                }

                if (round(($amount + $contamount), 2) != 0) {
                    $this->error('RC000047', [
                        'txnamount*' => $amount,
                        'rate' => '',
                        'curcode' => '',
                        'contamount*' => $contamount,
                        'contrate' => '',
                        'contcurcode' => '',
                    ]);
                }

                if ($parameters['curcode'] != $parameters['contcurcode']) {
                    $gp = GPInstGp::where('instid', auth()->user()->instid)
                        ->where('itemname', 'SpotAccount')->first();
                    if (empty($gp)) {
                        $this->error('RC000163');
                    }

                    $p = new TxnJrnlEntity();
                    $p->setTxnAcntCode($gp->itemvalue);
                    $p->setTxnAmount($txnamount * (-1));
                    // $p->setRate($parameter['contrate'] ?? null);
                    $p->setCurCode($parameters['curcode']);
                    $p->setContCurCode($parameters['contcurcode']);
                    $p->setTxnDesc($parameters['txndesc'] ?? "");
                    $p->setAcntbrchno($parameters['brchno']);
                    $p->setTxncode('gl900002');
                    $p->setMainAcntPosition('PUSH');
                    $p->setJrno($jrno);
                    if (isset($parameters['txndate'])) {
                        $p->setTxndate($parameters['txndate']);
                    }

                    $p1 = clone $p;
                    $p1->setTxnAmount($conttxnamount * (-1));
                    $p1->setCurCode($parameters['contcurcode']);
                    $p1->setContCurCode($parameters['curcode']);
                    // $p->setUnit('0000');
                    $service = new GlTxnService();
                    $jrItem = $service->doTxn($p, $jrItem);
                    $jrItem = $service->doTxn($p1, $jrItem);

                    $equivacct = GPInstCur::select('equivacct')
                        ->where('curcode', $parameters['curcode'])
                        ->where('instid', auth()->user()->instid)
                        ->where('statusid', 1)
                        ->first();
                    if (empty($equivacct) || empty($equivacct->equivacct)) {
                        $this->error('RC000164', [
                            'curcode' => $parameters['curcode']
                        ]);
                    }

                    $equivacct1 = GPInstCur::select('equivacct')
                        ->where('curcode', $parameters['contcurcode'])
                        ->where('instid', auth()->user()->instid)
                        ->where('statusid', 1)
                        ->first();
                    if (empty($equivacct1) || empty($equivacct1->equivacct)) {
                        $this->error('RC000164', [
                            'curcode' => $parameters['contcurcode']
                        ]);
                    }

                    $p = new TxnJrnlEntity();
                    $p->setTxnAcntCode($equivacct->equivacct);
                    $p->setTxnAmount($amount * (-1));
                    // $p->setRate($parameter['contrate'] ?? null);
                    $p->setCurCode($parameters['curcode']);
                    $p->setContCurCode($parameters['contcurcode']);
                    $p->setTxnDesc($parameters['txndesc'] ?? "");
                    $p->setAcntbrchno($parameters['brchno']);
                    $p->setTxncode('gl900002');
                    $p->setMainAcntPosition('PUSH');
                    $p->setJrno($jrno);
                    if (isset($parameters['txndate'])) {
                        $p->setTxndate($parameters['txndate']);
                    }

                    $p1 = clone $p;
                    $p1->setTxnAcntCode($equivacct1->equivacct);
                    $p1->setTxnAmount($amount);

                    $jrItem = $service->doTxn($p, $jrItem);
                    $jrItem = $service->doTxn($p1, $jrItem);
                }

                if ($parameters['brchno'] != $parameters['contbrchno']) {
                    $gp = GPInstGp::where('instid', auth()->user()->instid)
                        ->where('itemname', 'IBAccount')->first();
                    if (empty($gp)) {
                        $this->error('RC000165');
                    }

                    $p = new TxnJrnlEntity();
                    $p->setTxnAcntCode($gp->itemvalue);
                    $p->setTxnAmount($conttxnamount);
                    // $p->setRate($parameter['contrate'] ?? null);
                    $p->setCurCode($parameters['contcurcode']);
                    $p->setContCurCode($parameters['contcurcode']);
                    $p->setTxnDesc($parameters['txndesc'] ?? "");
                    $p->setAcntbrchno($parameters['brchno']);
                    $p->setTxncode('gl900002');
                    $p->setMainAcntPosition('PUSH');
                    $p->setJrno($jrno);
                    if (isset($parameters['txndate'])) {
                        $p->setTxndate($parameters['txndate']);
                    }

                    $p1 = clone $p;
                    $p1->setTxnAmount($conttxnamount * (-1));
                    $p1->setCurCode($parameters['contcurcode']);
                    $p1->setContCurCode($parameters['contcurcode']);
                    $p1->setAcntbrchno($parameters['contbrchno']);
                    // $p->setUnit('0000');
                    $service = new GlTxnService();
                    $jrItem = $service->doTxn($p, $jrItem);
                    $jrItem = $service->doTxn($p1, $jrItem);
                }


                if ($p->getIsPreview() != 1) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        } catch (MeException $th) {
            Log::channel('txn_log')->error($th);
            throw $th;
        } catch (Exception $ex) {
            throw $ex;
        } finally {
        }

        $txnResult->setTxnJritemNo($jrItem->getJritemno());
        $txnResult->setTxnpreview($jrItem->getTxnpreview());
        return $txnResult->jsonSerialize(true);
    }

    public function gl900003(Request $request)
    {
        $validated = $this->validate($request, [
            'data' => 'required',
            'data.brchno' => 'required',
            'data.curcode' => 'required',
            'data.gldate' => 'required',
            'data.settleacnt' => 'required',
            'data.txndesc' => 'required',
            'data.isclosebalance' => 'nullable',

            'searchData' => 'required',
            'searchData.gldate' => 'required',
            'searchData.brchno' => 'nullable',
            'searchData.curcode' => 'nullable',
            'searchData.type' => 'nullable',
        ]);
        $validateS = $validated['searchData'];
        $validateA = $validated['data'];
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $service = new GlProcessService();
        $brchno = $validateS['brchno'] ?? null;
        $curcode = $validateS['curcode'] ?? null;
        $type = $validateS['type'] ?? null;
        $gldate = $validateS['gldate'] ?? CoreService::getGlDate($instid);

        $datas = $service->SelectInExBalSettle($gldate, $instid, $brchno, $curcode, $type);

        $txnResult = new TxnResult();
        $jrItem = new TxnItemEntity();
        $serviceTxn = new GlTxnService();
        try {

            $jrno = "SB" . CoreService::getGlNextJrno();
            $txnResult->setTxnJrno($jrno);
            DB::beginTransaction();
            try {
                foreach ($datas as $key => $parameter) {
                    $parameter = json_decode(json_encode($parameter), true);
                    if (round($parameter['amount'], 2) != 0) {
                        $p = new TxnJrnlEntity();
                        $txnamount = $parameter['amount'];

                        $p->setTxnAcntCode($validateA['settleacnt']);
                        $p->setRate(null);
                        $p->setCurCode($validateA['curcode']);
                        $p->setContCurCode($parameter['currency']);


                        if ($validateA['curcode'] != $parameter['currency']) {
                            $ratetype = GPInstCurRateHist::where('rtypecode', 1)
                                ->where('curcode', $p->getCurCode())
                                ->where('instid', $instid)
                                ->whereDate('date', $gldate)
                                ->where('statusid', 1)->first();
                            if (empty($ratetype)) {
                                $this->error('RC000045', ['curcode' => $curcode]);
                            }
                            $p->setRate($ratetype->buyrate);
                            $ratetype = GPInstCurRateHist::where('rtypecode', 1)
                                ->where('curcode', $p->getContCurCode())
                                ->where('instid', $instid)
                                ->whereDate('date', $gldate)
                                ->where('statusid', 1)->first();
                            if (empty($ratetype)) {
                                $this->error('RC000045', ['curcode' => $curcode]);
                            }
                            $p->setContRate($ratetype->salerate);
                            $txnamount = convertAmt($txnamount, $p->getContRate(), $p->getRate());
                        }
                        $p->setTxnAmount($txnamount);
                        $p->setTxnDesc($validateA['txndesc'] ?? "");
                        $p->setAcntbrchno($validateA['brchno']);
                        $p->setContAcntbrchno($parameter['branch']);
                        $p->setTxncode('gl900003');
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        $p->setTxndate($gldate);
                        if (isset($validateA['isclosebalance'])) {
                            $p->setIsCloseBalance($validateA['isclosebalance']);
                        }

                        $jrItem = $serviceTxn->doTxn($p, $jrItem);

                        $p = new TxnJrnlEntity();
                        $p->setTxnAcntCode($parameter['account']);
                        $p->setTxnAmount($parameter['amount'] * -1);
                        $p->setRate(null);
                        $p->setCurCode($parameter['currency']);
                        $p->setContCurCode($validateA['curcode']);

                        $p->setTxnDesc($validateA['txndesc'] ?? "");
                        $p->setAcntbrchno($parameter['branch']);
                        $p->setContAcntbrchno($validateA['brchno']);
                        $p->setTxncode('gl900003');
                        $p->setMainAcntPosition('PUSH');
                        $p->setJrno($jrno);
                        $p->setTxndate($gldate);
                        if (isset($validateA['isclosebalance'])) {
                            $p->setIsCloseBalance($validateA['isclosebalance']);
                        }
                        $jrItem = $serviceTxn->doTxn($p, $jrItem);

                        if ($validateA['curcode'] != $parameter['currency']) {
                            $gp = GPInstGp::where('instid', auth()->user()->instid)
                                ->where('itemname', 'SpotAccount')->first();
                            if (empty($gp)) {
                                $this->error('RC000163');
                            }

                            $p = new TxnJrnlEntity();
                            $p->setTxnAcntCode($gp->itemvalue);
                            $p->setTxnAmount($txnamount * (-1));
                            $p->setCurCode($validateA['curcode']);
                            $p->setContCurCode($parameter['currency']);
                            $p->setAcntbrchno($validateA['brchno']);
                            $p->setTxncode('gl900003');
                            $p->setMainAcntPosition('PUSH');
                            $p->setJrno($jrno);
                            $p->setTxnDesc($validateA['txndesc'] ?? "");
                            $p->setTxndate($gldate);

                            $p1 = clone $p;
                            $p1->setTxnAmount($parameter['amount'] * (-1));
                            $p1->setCurCode($parameter['currency']);
                            $p1->setContCurCode($validateA['curcode']);
                            $service = new GlTxnService();
                            $jrItem = $service->doTxn($p, $jrItem);
                            $jrItem = $service->doTxn($p1, $jrItem);

                            $equivacct = GPInstCur::select('equivacct')
                                ->where('curcode', $validateA['curcode'])
                                ->where('instid', auth()->user()->instid)
                                ->where('statusid', 1)
                                ->first();
                            if (empty($equivacct) || empty($equivacct->equivacct)) {
                                $this->error('RC000164');
                            }

                            $equivacct1 = GPInstCur::select('equivacct')
                                ->where('curcode', $parameter['currency'])
                                ->where('instid', auth()->user()->instid)
                                ->where('statusid', 1)
                                ->first();
                            if (empty($equivacct1) || empty($equivacct1->equivacct)) {
                                $this->error('RC000164');
                            }

                            $p = new TxnJrnlEntity();
                            $p->setTxnAcntCode($equivacct->equivacct);
                            $p->setTxnAmount($parameter['amount'] * (-1));
                            $p->setCurCode($validateA['curcode']);
                            $p->setContCurCode($parameter['currency']);
                            $p->setTxnDesc($parameters['txndesc'] ?? "");
                            $p->setAcntbrchno($validateA['brchno']);
                            $p->setTxncode('gl900003');
                            $p->setMainAcntPosition('PUSH');
                            $p->setJrno($jrno);
                            $p->setTxnDesc($validateA['txndesc'] ?? "");
                            $p->setTxndate($gldate);

                            $p1 = clone $p;
                            $p1->setTxnAcntCode($equivacct1->equivacct);
                            $p1->setTxnAmount($parameter['amount']);

                            $jrItem = $service->doTxn($p, $jrItem);
                            $jrItem = $service->doTxn($p1, $jrItem);
                        }

                        if ($validateA['brchno'] != $parameter['branch']) {
                            $gp = GPInstGp::where('instid', auth()->user()->instid)
                                ->where('itemname', 'IBAccount')->first();
                            if (empty($gp)) {
                                $this->error('RC000165');
                            }

                            $p = new TxnJrnlEntity();
                            $p->setTxnAcntCode($gp->itemvalue);
                            $p->setTxnAmount($parameter['amount']);
                            $p->setCurCode($parameter['currency']);
                            $p->setContCurCode($parameter['currency']);
                            $p->setAcntbrchno($parameter['branch']);
                            $p->setTxncode('gl900003');
                            $p->setMainAcntPosition('PUSH');
                            $p->setJrno($jrno);
                            $p->setTxnDesc($validateA['txndesc'] ?? "");
                            $p->setTxndate($gldate);

                            $p1 = clone $p;
                            $p1->setTxnAmount($parameter['amount'] * (-1));
                            $p1->setCurCode($parameter['currency']);
                            $p1->setContCurCode($parameter['currency']);
                            $p1->setAcntbrchno($validateA['brchno']);


                            $service = new GlTxnService();
                            $jrItem = $service->doTxn($p, $jrItem);
                            $jrItem = $service->doTxn($p1, $jrItem);
                        }

                    }
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        } catch (MeException $th) {
            Log::channel('txn_log')->error($th);
            throw $th;
        } catch (Exception $ex) {
            throw $ex;
        } finally {
        }

        $txnResult->setTxnJritemNo($jrItem->getJritemno());
        $txnResult->setTxnpreview($jrItem->getTxnpreview());
        return $txnResult->jsonSerialize(true);
    }
}
