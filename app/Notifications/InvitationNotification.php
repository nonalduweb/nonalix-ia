<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation à rejoindre un espace : le destinataire choisit son mot de passe.
 *
 * Aucun mot de passe n'est jamais transmis par e-mail. Le compte est créé sans
 * secret connu de personne, et seul le titulaire de l'adresse en définit un —
 * ce qui prouve du même coup qu'il contrôle cette boîte.
 */
class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $tenantName,
        /** L'invité est-il le propriétaire de l'entreprise, ou un équipier ? */
        public readonly bool $isOwner = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitation.accept', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $days = (int) round(config('auth.passwords.invitations.expire', 10080) / 1440);

        $message = (new MailMessage)
            ->subject($this->isOwner
                ? "Votre espace {$this->tenantName} est prêt — Nonalix IA"
                : "Vous êtes invité à rejoindre {$this->tenantName} — Nonalix IA")
            ->greeting("Bonjour {$notifiable->name},");

        $message = $this->isOwner
            ? $message->line("L'espace Nonalix IA de **{$this->tenantName}** a été créé pour vous.")
            : $message->line("Vous avez été invité à rejoindre l'espace Nonalix IA de **{$this->tenantName}**.");

        return $message
            ->line('Choisissez votre mot de passe pour activer votre accès.')
            ->action('Définir mon mot de passe', $url)
            ->line("Ce lien est valable {$days} jours et ne fonctionne qu'une fois.")
            ->line('Si vous n\'attendiez pas cette invitation, ignorez ce message : sans mot de passe défini, le compte reste inutilisable.')
            ->salutation('L\'équipe Nonalix IA');
    }
}
