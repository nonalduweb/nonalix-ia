<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Message sonde envoyé à l'adresse du client pour éprouver sa redirection.
 *
 * Le raisonnement tient en une phrase : si la règle de redirection fonctionne,
 * ce message — qui arrive chez le client comme n'importe quel courrier — nous
 * revient. Sa présence est la seule preuve acceptée que le canal est ouvert ;
 * une déclaration du client n'en serait pas une.
 */
class ForwardProbeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vérification de votre canal e-mail Nonalix IA ['.$this->code.']',
        );
    }

    public function content(): Content
    {
        $code = e($this->code);
        $name = e($this->companyName);

        return new Content(htmlString: <<<HTML
            <p>Bonjour {$name},</p>
            <p>
                Ce message vérifie que la redirection de votre adresse vers Nonalix&nbsp;IA
                fonctionne. Vous n'avez rien à faire : s'il nous revient, votre canal
                e-mail s'activera tout seul.
            </p>
            <p>Code de vérification : <strong>{$code}</strong></p>
            <p style="color:#64748b;font-size:12px">
                Si la redirection n'est pas encore en place, ce message restera dans votre
                boîte et le canal restera fermé. Vous pouvez le supprimer sans crainte.
            </p>
            HTML);
    }
}
