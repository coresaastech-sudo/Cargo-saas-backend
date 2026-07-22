<?php

namespace Modules\Ad\Http\Controllers\Eod;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Services\AdCreditInfoBueroService;
use Modules\Ad\Http\Services\TrEodService;
use Modules\Gp\Entities\GPInstSeq;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Http\Services\CoreService;

class GeneralEodController extends CoreController
{
    /**
     * Өндөрлөх процесс эхлэв
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800001($step)
    {
        GPInstSeq::where('instid', auth()->user()->instid)
            ->where('seqid', 'EODSYSDATE')->update([
                'seqno' => CoreService::getTxnDate(auth()->user()->instid)
            ]);

        // Өндөрлөх процесс эхлэхийг заана.
        $isdone = GPInstSeq::where('instid', auth()->user()->instid)
            ->where('seqid', 'EODISON')->update([
                'seqno' => 1
            ]);
        $step->allcount = $isdone;
        $step->succount = $step->allcount;
        return [
            'status' => 200
        ];
    }

    /**
     * Системийн огноо өөрчлөв (Started red zone)
     *
     * @return void
     */
    public function ad800017($step)
    {
        // Энэд гэхдээ яг ямар өдөр шилжээд байгааг олох хэрэгтэй байгаа.
        CoreService::addTxnDate();
        $step->allcount = 1;
        $step->succount = $step->allcount;
    }


    /**
     * Started Yellow zone
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800059($step)
    {
        $step->allcount = 1;
        $step->succount = $step->allcount;
    }

    /**
     * Started Gray zone
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800087($step) {}

    /**
     * Дараагийн өдөр эхлэв. (Started Blue zone)
     *
     * @param  AdEodLog $step
     * @return void
     */
    public function ad800098($step)
    {
        // Энэд гэхдээ яг ямар өдөр шилжээд байгааг олох хэрэгтэй байгаа.
        // CoreService::addTxnDate();
        $isdone = GPInstSeq::where('instid', auth()->user()->instid)
            ->where('seqid', 'EODSYSDATE')->update([
                'seqno' => CoreService::getTxnDate(auth()->user()->instid)
            ]);
        $step->allcount = $isdone;
        $step->succount = $step->allcount;
        $this->CleanMemory();
    }

    /**
     * Өндөрлөх процесс төгсөв.
     *
     * @return void
     */
    public function ad800118($step)
    {
        GPInstSeq::where('instid', auth()->user()->instid)
            ->where('seqid', 'EODSYSDATE')->update([
                'seqno' => CoreService::getTxnDate(auth()->user()->instid)
            ]);
        // Өндөрлөх процесс дуусах.
        $isdone = GPInstSeq::where('instid', auth()->user()->instid)
            ->where('seqid', 'EODISON')->update([
                'seqno' => 0
            ]);
        $step->allcount = $isdone;
        $step->succount = $step->allcount;
        $this->CleanMemory();
        try {
            event(new \Modules\Gp\Events\SystemDateEvent(CoreService::getTxnDate(auth()->user()->instid), auth()->user()));
        } catch (Exception $ex) {
            Log::channel('eod_log')->debug($ex);
        }
        // Дараагийн өдөр өндөрлөх жагсаалтыг бэлдэнэ.
        (new TrEodService())->createEodList();
        // $process = GpctionCode::where('ACTION_CODE', 'ad011000')->first();
        // if ($process) {
        //     App::call($process->controller . '@' . $process->function);
        // }

        try {
            $provider = VwGPProviderConf::where('code', '7')->where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->first();
            if ($provider) {
                $providerConfig = json_decode($provider->config, true);

                if (isset($providerConfig)) {
                    if ((@$providerConfig['allow_eod'] ?? 0) == 1) {
                        $service = new AdCreditInfoBueroService(auth()->user()->instid, auth()->user()->id);

                        if (!$service->isOnSendZMSJob()) {
                            $creditInfoList = $service->sendData(1);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    private function CleanMemory()
    {
        Cache::flush();
    }
}
