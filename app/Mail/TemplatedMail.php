<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public ?string $unsubscribeUrl,
        public string $mailLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function headers(): Headers
    {
        return new Headers(text: array_filter([
            'List-Unsubscribe' => $this->unsubscribeUrl ? '<'.$this->unsubscribeUrl.'>' : null,
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.templated', with: [
            'body' => $this->htmlBody,
            'unsubscribeUrl' => $this->unsubscribeUrl,
            'locale' => $this->mailLocale,
        ]);
    }
}
