<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $magicUrl;
    public string $buttonText;

    /**
     * @param string $url        URL completa de destino del botón del email.
     * @param string $buttonText Texto del botón (por defecto neutro).
     */
    public function __construct(string $url, string $buttonText = 'Acceder →')
    {
        $this->magicUrl   = $url;
        $this->buttonText = $buttonText;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu enlace de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
        );
    }
}
