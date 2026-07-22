<?php

namespace Modules\Gp\Jobs;

use App\Events\LoanBureauListEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Ad\Entities\AdAutoJob;
use Modules\Ad\Http\Services\AdCreditInfoBueroService;
use Modules\Gp\Emails\MailTemplate;

class SendBueroJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $instid;
    protected $userid;
    protected $custno;
    protected $count;
    protected $acntno;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($instid, $custno, $userid, $count, $acntno)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->custno = $custno;
        $this->custno = $custno;
        $this->count = $count;
        $this->acntno = $acntno;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('SendBueroJob');
        event(new LoanBureauListEvent($this->count, $this->instid));
        $bueroService = new AdCreditInfoBueroService($this->instid, $this->userid, $this->acntno);
        $bueroService->upload($this->custno, $this->acntno);
        endJobInfo('SendBueroJob');
    }
}
