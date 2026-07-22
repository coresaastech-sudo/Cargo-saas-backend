<?php

namespace Modules\Gp\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailTemplate extends Mailable
{
    use Queueable, SerializesModels;

    protected $details;
    protected $filePath;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details, $filePath = null)
    {
        $this->details = $details;
        $this->filePath = $filePath;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->subject($this->details["subject"])
            ->markdown($this->details["template"], $this->details["data"]);

        if ($this->filePath) {
            $email->attach($this->filePath);
        }
        return $email;
    }
}
