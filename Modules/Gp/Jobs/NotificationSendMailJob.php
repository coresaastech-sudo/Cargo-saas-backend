<?php

namespace Modules\Gp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Services\AdAwsSesService;
use Modules\Gp\Emails\NotificationMailTemplate;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class NotificationSendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details;
    protected $fromAddress;
    protected $fromName;
    public $tries = 4;
    public $timeout = 120;
    public $failOnTimeout = true;

    public function backoff()
    {
        return [60, 300, 900];
    }
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($details, $fromAddress, $fromName)
    {
        $this->details = $details;
        if (isset($fromAddress)) {
            $this->fromAddress = $fromAddress;
        } else {
            $this->fromAddress = config("mail.from.address");
        }

        if (isset($fromName)) {
            $this->fromName = $fromName;
        } else {
            $this->fromName = config("mail.from.name");
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('NotificationSendMailJob');

        // Validate email addresses
        if (is_array($this->details["to"])) {
            foreach ($this->details["to"] as $emailAddress) {
                if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                    Log::warning("Invalid email address in SendMailJob array: " . $emailAddress);
                    return;
                }
            }
        } else {
            if (!filter_var($this->details["to"], FILTER_VALIDATE_EMAIL)) {
                Log::warning("Invalid email address in SendMailJob: " . $this->details["to"]);
                return;
            }
        }

        $email = new NotificationMailTemplate($this->details, $this->fromAddress, $this->fromName);

        $service = new AdAwsSesService();

        // Filter out blacklisted emails
        $validEmails = [];
        if (is_array($this->details["to"])) {
            foreach ($this->details["to"] as $emailAddress) {
                if (!$service->checkEmail($emailAddress)) {
                    $validEmails[] = $emailAddress;
                } else {
                    Log::info("Skipping blacklisted email: " . $emailAddress);
                }
            }
        } else {
            if (!$service->checkEmail($this->details["to"])) {
                $validEmails[] = $this->details["to"];
            } else {
                Log::info("Skipping blacklisted email: " . $this->details["to"]);
            }
        }

        // Send email to valid addresses
        if (!empty($validEmails)) {
            try {
                Mail::to($validEmails)->send($email);
            } catch (TransportExceptionInterface $ex) {
                Log::warning('Notification email SMTP transport failed', [
                    'attempt' => $this->attempts(),
                    'to_count' => count($validEmails),
                    'message' => $ex->getMessage(),
                ]);

                throw $ex;
            }
            endJobInfo('NotificationSendMailJob');
        } else {
            Log::warning("No valid email addresses to send to in NotificationSendMailJob");
        }
    }
}
