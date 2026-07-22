<?php

namespace Modules\Gp\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Ad\Http\Services\AdEbarimtService;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEbarimt;
use Modules\Ad\Http\Services\AdAutoJobService;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;

class EBarimtJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $AC;
    protected $data;
    protected $user;
    protected $consumerNo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($AC, $data, $user, $consumerNo = null)
    {
        $this->AC = $AC;
        $this->data = $data;
        $this->user = $user;
        $this->consumerNo = $consumerNo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('EBarimtJob');
        try {
            $ebarimt = new AdEbarimtService($this->user->instid, $this->user);
            if (@$this->data['txnPreview'][0]['corr'] == 1) {
                $orgjrno = @$this->data['txnPreview'][0]['orgjrno'] ?? null;

                if (isset($orgjrno)) {
                    $ebarimtItem = AdEbarimt::where('instid', $this->user->instid)->where('jrno', $orgjrno)->where('res_success', 1)->first();
                    if (isset($ebarimtItem)) {
                        $data = $ebarimt->rebillVat($ebarimtItem->id);
                    }
                }
            } else {
                $data = $ebarimt->generateSingleVat($this->AC, $this->data, $this->consumerNo);

                if ($data) {
                    $tax = $data['tax'];
                    $cust = $data['cust'];

                    $cust_info = null;
                    if ($cust->custtypecode === 0) {
                        $cust_info = CrCustInd::where("custno", $cust->custno)->where("instid", $this->user->instid)->where("statusid", "<>", -1)->first();
                    } else {
                        $cust_info = CrCustOrg::where("custno", $cust->custno)->where("instid", $this->user->instid)->where("statusid", "<>", -1)->first();
                    }

                    if (empty($data['response']['consumerNo'])) {
                        if (!empty($cust_info) && !empty($cust_info->email)) {
                            $autojobService = new AdAutoJobService();
                            $autojob = $autojobService->checkAutoJobActionCode($this->AC, $cust_info, $tax);
                        }
                    }
                }
            }
            endJobInfo('EBarimtJob');
        } catch (Exception $e) {
        }
    }
}
