<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Lien de confirmation d'adresse, en français.
 *
 * Remplace la notification par défaut de Laravel, rédigée en anglais et
 * signée « Laravel ».
 *
 * Mise en file : un relais SMTP lent ne doit pas retarder la réponse HTTP de
 * l'inscription. La file `default` est traitée par Horizon.
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Confirmez votre adresse e-mail — Nonalix IA')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre espace Nonalix IA est créé. Il reste à confirmer cette adresse pour y accéder.')
            ->action('Confirmer mon adresse', $this->verificationUrl($notifiable))
            ->line("Ce lien expire dans {$minutes} minutes.")
            ->line('Cette adresse sert aussi à récupérer votre compte : conservez-y un accès.')
            ->salutation('L\'équipe Nonalix IA');
    }

    /**
     * L'URL est signée et datée : sans signature, n'importe qui pourrait
     * confirmer l'adresse d'un tiers en devinant un identifiant.
     */
    private function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
