<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Code de connexion à usage unique.
 *
 * Volontairement PAS mis en file d'attente, contrairement aux autres envois :
 * l'utilisateur attend ce code devant son écran. Le confier à Horizon
 * ajouterait une latence — et une panne de file le laisserait à la porte de
 * son propre compte.
 */
class TwoFactorCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Votre code de connexion : {$this->code}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Voici votre code de connexion à Nonalix IA :')
            ->line("**{$this->code}**")
            ->line("Il est valable {$this->minutes} minutes et ne fonctionne qu'une fois.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, quelqu\'un connaît votre mot de passe : changez-le sans attendre.')
            ->salutation('L\'équipe Nonalix IA');
    }
}
