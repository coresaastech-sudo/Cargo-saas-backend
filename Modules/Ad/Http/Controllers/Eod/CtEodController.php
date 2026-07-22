<?php

namespace Modules\Ad\Http\Controllers\Eod;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Http\Services\CoreService;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Ad\Http\Services\CtEodService;
use Modules\Ia\Entities\IaCtAccount;
use Modules\Ia\Entities\IaCtAccountHist;

class CtEodController extends CoreController
{

    /**
     * Тэнцэлийн гадуурх дансны үлдэгдэл түр хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800057($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $dpservice = new CtEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800057',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];

        $datas = $dpservice->CreateTmpCTBals($txndate, $lastitem, $instid);
        // Log::debug([count($datas), $datas]);
        if (!$lastitem) {
            $step->allcount = count($datas);
        }

        foreach ($datas as $data) {
            try {
                $eodlogs['acntno'] = $data->acntno;
                $eodlogs['acntbrchno'] = $data->brchno;
                IaCtAccount::where('acntno', $data->acntno)
                    ->where('instid', $instid)->update([
                        'tmp_currentbal' =>  DB::raw('currentbal'),
                        'tmp_statusid' =>  DB::raw('statusid'),
                        'tmp_capint' =>  DB::raw('capint'),
                        'tmp_currentcount' =>  DB::raw('currentcount'),
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
     * Тэнцлийн гадуурх дансны түүх хадгалах
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800121($step)
    {
        $lastitem = $this->getLastEodStep($step);
        $service = new CtEodService();
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getEodSysdate($instid);
        $eodlogs = [
            'eoddate' => $step->eoddate,
            'stepno' => $step->stepno,
            'orderno' => $step->orderno,
            'ACTION_CODE' => 'ad800121',
            'instid' => $instid,
            'created_by' => $userid,
            'updated_by' => $userid,
        ];
        try {
            DB::beginTransaction();
            $service->CtAcntHistDel($txndate, $lastitem, $instid);
            $datas = $service->CtAcntHistAdd($txndate, $lastitem, $instid, $userid);

            $eodlogs['acntno'] = "Bulk";
            $eodlogs['acntbrchno'] = auth()->user()->brchno;
            $eodlogs['errtype'] = null;
            IaCtAccountHist::insert($datas->toArray());
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
