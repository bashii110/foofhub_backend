<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp
    ) {}

    public function build()
    {
        return $this
            ->subject('FoodHub - Email Verification Code')
            ->html("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
                    <h2>Welcome to FoodHub</h2>

                    <p>Thank you for creating your account.</p>

                    <p>Your email verification code is:</p>

                    <h1 style='font-size: 36px; letter-spacing: 10px;'>
                        {$this->otp}
                    </h1>

                    <p>This code will expire in 10 minutes.</p>

                    <p>If you did not create this account, please ignore this email.</p>
                </div>
            ");
    }
}