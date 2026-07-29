<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AccessCode;
use App\Support\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Transmet au prospect le code d'accès qui lui ouvre la création de compte.
 *
 * Le lien porte le code en paramètre : recopier douze caractères à la main
 * est la première occasion d'abandonner. Le code reste écrit en clair dans le
 * message, pour qui préfère le saisir ou le transmettre à un collègue.
 */
class AccessCodeGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AccessCode $code,
        public readonly string $companyName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Votre accès à Nonalix IA')
            ->greeting("Bonjour {$notifiable->contact_name},")
            ->line("Votre demande pour **{$this->companyName}** est acceptée.")
            ->line("Votre code d'accès : **{$this->code->code}**")
            ->action('Créer mon espace', Domain::app('register?code='.$this->code->code));

        if ($this->code->plan) {
            $mail->line("Il ouvre le pack **{$this->code->plan->name}**, avec {$this->code->trial_days} jours d'essai.");
        }

        if ($this->code->expires_at) {
            $mail->line('Ce code expire le '.$this->code->expires_at->translatedFormat('j F Y').'.');
        }

        return $mail
            ->line('La configuration prend une quinzaine de minutes : votre numéro WhatsApp, votre activité, vos tarifs.')
            ->salutation('L\'équipe Nonalix IA');
    }
}
