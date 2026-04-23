<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PendingRegistrationVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $maxExceptions = 1;

    public function __construct(public readonly PendingRegistration $pending) {}

    public function failed(\Throwable $exception): void
    {
        Log::error('Mail delivery permanently failed', [
            'mailable' => static::class,
            'exception' => $exception->getMessage(),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'E-Mail-Adresse bestätigen – app.linn.games');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pending-registration-verification');
    }
}
