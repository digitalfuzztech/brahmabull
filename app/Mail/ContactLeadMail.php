<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ContactLeadMail extends Mailable
{
    public $name;
    public $email;
    public $subjectLine;
    public $messageBody;
    public $type;

    public function __construct(
        $name = null,
        $email = null,
        $subjectLine = null,
        $messageBody = null,
        $type = 'guest'
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->subjectLine = $subjectLine;
        $this->messageBody = $messageBody;
        $this->type = $type;
    }

    public function build()
    {
        $subject = match ($this->type) {
            'player' => 'Player Message - ' . $this->subjectLine,
            'agent'  => 'Agent Message - ' . $this->subjectLine,
            default  => 'New BrahmaBull Contact Lead',
        };

        return $this
            ->subject($subject)
            ->view('emails.contact-lead');
    }
}
