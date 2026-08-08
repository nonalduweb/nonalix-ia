<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Crée une nouvelle instance de Mailable.
     */
    public function __construct(
        public readonly string $subjectText,
        public readonly string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->bodyText)),
        );
    }
}
