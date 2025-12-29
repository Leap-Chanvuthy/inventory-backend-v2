<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyIdentityMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $temporaryPassword;
    public $appFrontendUrl;
    public $appName;
    public $verifyUrl;

    public function __construct($name, $email, $temporaryPassword, $appFrontendUrl, $verifyUrl)
    {
        $this->name = $name;
        $this->email = $email;
        $this->temporaryPassword = $temporaryPassword;
        $this->appFrontendUrl = $appFrontendUrl;
        $this->appName = config('app.name');
        $this->verifyUrl = $verifyUrl;
    }

    public function envelope()
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'Verify Your Account'
        );
    }

    public function content()
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.verify_user_identity'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
