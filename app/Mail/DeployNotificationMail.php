<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DeployNotificationMail extends Mailable
{
    public function __construct(
        public string $deployedAt,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Linn.Games — Deployment erfolgreich',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deploy-notification',
        );
    }
}
