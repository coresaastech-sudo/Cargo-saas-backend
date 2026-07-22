<?php

namespace Modules\Gp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Ad\Http\Services\AdNotificationService;

class NotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $title;
    protected $description;
    protected $tokens;
    protected $instid;
    protected $appid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($title, $description, $tokens, $instid, $appid)
    {
        $this->title = $title;
        $this->description = $description;
        $this->tokens = $tokens;
        $this->instid = $instid;
        $this->appid = $appid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('NotificationJob');
        $adNotificationService = new AdNotificationService($this->instid);
        $adNotificationService->sendNotificationFirebase($this->title, $this->description, $this->tokens, $this->appid);
        endJobInfo('NotificationJob');
    }
}
