<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AccessRequest;
use App\Support\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'équipe NONALIX qu'une demande d'accès vient d'arriver.
 *
 * Sans cet envoi, une demande resterait invisible jusqu'à ce que quelqu'un
 * pense à consulter l'administration — et un prospect qui attend trois jours
 * est un prospect perdu.
 */
class AccessRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AccessRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Demande d'accès — {$this->request->company}")
            ->greeting('Nouvelle demande d\'accès')
            ->line("**Entreprise** : {$this->request->company}")
            ->line("**Contact** : {$this->request->contact_name}")
            ->line("**E-mail** : {$this->request->email}");

        if ($this->request->phone) {
            $mail->line("**Téléphone** : {$this->request->phone}");
        }

        if ($this->request->plan) {
            $mail->line("**Pack souhaité** : {$this->request->plan->name}");
        }

        if ($this->request->message) {
            $mail->line('---')->line($this->request->message);
        }

        return $mail
            ->action('Traiter la demande', Domain::admin('access-requests'))
            ->line('Approuver génère un code d\'accès et l\'envoie automatiquement au prospect.');
    }
}
