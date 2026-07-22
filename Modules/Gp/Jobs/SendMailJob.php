<?php

namespace Modules\Gp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Ad\Entities\AdSentNotification;
use Modules\Ad\Http\Services\AdAwsSesService;
use Modules\Gp\Emails\MailTemplate;
use Modules\Gp\Http\Controllers\GpInstInvoiceController;

class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('SendMailJob');
        $adnotifs = [];
        if (gettype($this->details["to"]) == 'array') {
            foreach ($this->details["to"] as $key => $useremail) {
                $adnotifs[] = AdSentNotification::create([
                    'reciever' => $useremail,
                    'title' => $this->details["subject"],
                    'description' => $this->details["template"],
                    'type' => "MAIL",
                    'body' => json_encode($this->details["data"]),
                    'statusid' => 2,
                    'instid' => 1,
                    'created_by' => 1,
                    'created_at' => getNow(),
                ]);
            }
        } else {
            $adnotifs[] = AdSentNotification::create([
                'reciever' => $this->details["to"],
                'title' => $this->details["subject"],
                'description' => $this->details["template"],
                'type' => "MAIL",
                'body' => json_encode($this->details["data"]),
                'statusid' => 2,
                'instid' => 1,
                'created_by' => 1,
                'created_at' => getNow(),
            ]);
        }
        $filePath = null;
        if (isset($this->details['invoiceid'])) {
            $invoicecontroller = new GpInstInvoiceController();
            $filePath = storage_path($this->details['filename']);
            $invoicecontroller->getPdfInvoice($this->details['invoiceid'], true, $this->details['userid'], $filePath);
            $email = new MailTemplate($this->details, $filePath);
        } else {
            $email = new MailTemplate($this->details);
        }

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

        $service = new AdAwsSesService();
        
        // Check if any email is blacklisted
        $isBlackListed = false;
        if (is_array($this->details["to"])) {
            foreach ($this->details["to"] as $emailItem) {
                if ($service->checkEmail($emailItem)) {
                    $isBlackListed = true;
                    break;
                }
            }
        } else {
            $isBlackListed = $service->checkEmail($this->details["to"]);
        }
        
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
            Mail::to($validEmails)->send($email);
            
            // Update notification status for successfully sent emails
            foreach ($adnotifs as $key => $adnotif) {
                if (in_array($adnotif->reciever, $validEmails)) {
                    $adnotif->statusid = 1;
                    $adnotif->save();
                }
            }
            
            endJobInfo('SendMailJob');
            if ($filePath) {
                unlink($filePath);
            }
        } else {
            Log::warning("No valid email addresses to send to in SendMailJob");
        }
    }
}
