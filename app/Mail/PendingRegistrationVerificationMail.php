<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingRegistrationVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PendingRegistration $pending) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'E-Mail-Adresse bestätigen – app.linn.games');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pending-registration-verification');
    }
}
