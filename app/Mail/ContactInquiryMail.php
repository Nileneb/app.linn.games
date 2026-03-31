<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Contact $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Neue Kontaktanfrage von {$this->contact->name}",
            replyTo: [$this->contact->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-inquiry',
        );
    }
}
