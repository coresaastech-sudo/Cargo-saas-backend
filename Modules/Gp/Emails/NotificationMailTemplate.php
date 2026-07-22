<?php

namespace Modules\Gp\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

class NotificationMailTemplate extends Mailable
{
    use Queueable, SerializesModels;

    protected $details;
    public $fromAddress;
    public $fromName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details, $fromAddress, $fromName)
    {
        $this->details = $details;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    public function envelope()
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: $this->details["subject"]
        );
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.welcome_plain')
            ->subject($this->details["subject"])
            ->html($this->details["template"]);
        // return $this->subject($this->details["subject"])->html($this->details["template"]);
    }
}
