<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class TalkPhoneProvisionMail extends BaseMailable
{
    public function content(): Content
    {
        return new Content(
            view: 'emails.en-us.talkphone.provision',
            text: 'emails.en-us.talkphone.provision-text',
        );
    }
}
