<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $userEmail;

    public function __construct($token, $userEmail)
    {
        $this->token = $token;
        $this->userEmail = $userEmail;
    }

    public function build()
    {
        return $this->subject('Reset Your User Password')
                    ->view('emails.password_reset')
                    ->with([
                        'token' => $this->token,
                        'email' => $this->userEmail,
                    ]);
    }
}
