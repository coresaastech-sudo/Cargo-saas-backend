<?php

namespace Modules\Gp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Re\Http\Controllers\ReInstReportTempController;

class ReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $validate;
    protected $report;
    protected $user;
    protected $reportkey;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($validate, $report, $user, $reportkey)
    {
        $this->validate = $validate;
        $this->report = $report;
        $this->user = $user;
        $this->reportkey = $reportkey;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('ReportJob');
        Auth::setUser($this->user);
        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }
        $data = (new ReInstReportTempController())->generateProcess($this->validate, $this->report, $this->user, $this->reportkey);
        Cache::put($this->reportkey, $data);
        endJobInfo('ReportJob');
    }
}
