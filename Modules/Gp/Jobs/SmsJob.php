<?php

namespace Modules\Gp\Jobs;

// use App\Services\AdSmsService;
use Modules\Ad\Entities\AdSentNotification;
use Modules\Ad\Http\Services\AdSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Modules\Ad\Entities\AdAutoJob;
use Modules\Gp\Emails\MailTemplate;
use Exception;

class SmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phone;
    protected $instid;
    protected $description;
    protected $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($phone, $description, $instid, $id)
    {
        $this->phone = $phone;
        $this->instid = $instid;
        $this->description = $description;
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('SmsJob');
        $sentNotification = AdSentNotification::where('id', $this->id)->first();
        try {
            $smsService = new AdSmsService($this->instid);
            $smsService->sendSms($this->phone, $this->description);
            if ($sentNotification) {
                $sentNotification->update(['statusid' => 1]);
            }
        } catch (Exception $e) {
            if ($sentNotification) {
                $sentNotification->update([
                    'statusid' => 2,
                    'error_msg' => $e->getMessage()
                ]);
            }
        }
        endJobInfo('SmsJob');
    }
}
