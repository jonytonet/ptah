<?php

declare(strict_types=1);

namespace Ptah\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu código de verificação — ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'ptah::mail.two-factor-code');
    }

    public function attachments(): array
    {
        return [];
    }
}
