<?php

namespace App\Mail;

use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $adminUser,
        public Company $company,
        public string $otp,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Security Verification: Password Reset Code for {$this->company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-otp',
        );
    }
}
