<?php

namespace App\Mail;

use App\Models\ClientInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientInvoice $invoice,
        public string $checkoutUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Link de pago: $' . number_format($this->invoice->total, 2) . ' MXN',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice-payment-link');
    }
}
