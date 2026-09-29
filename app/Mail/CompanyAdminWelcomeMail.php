<?php

namespace App\Mail;

use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyAdminWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $adminUser,
        public Company $company,
        public string $otp,
        public string $verificationUrl,
        public ?string $temporaryPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to Nobingo: Activate {$this->company->name} Administrator Account",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company-welcome',
        );
    }
}
