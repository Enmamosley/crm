<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización ' . $this->quote->quote_number,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quote-sent');
    }
}
