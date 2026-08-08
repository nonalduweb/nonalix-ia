<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Mail\AgentReplyMail;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendEmailMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Crée une nouvelle instance de Job.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
    ) {}

    /**
     * Exécute le Job.
     */
    public function handle(): void
    {
        $message = Message::findOrFail($this->messageId);
        $conversation = $message->conversation;
        $contact = $conversation->contact;

        // Déterminer l'adresse e-mail de destination
        $recipientEmail = $contact->email;
        if (empty($recipientEmail)) {
            // Repli : extraire l'adresse e-mail du wa_id (ex: email_client@gmail.com)
            $recipientEmail = str_replace('email_', '', $contact->wa_id);
        }

        if (empty($recipientEmail) || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            $message->update(['status' => MessageStatus::Failed]);
            throw new RuntimeException("Adresse e-mail invalide pour le contact.");
        }

        // Trouver le premier message entrant pour extraire le sujet d'origine
        $originalInbound = $conversation->messages()
            ->where('direction', MessageDirection::Inbound->value)
            ->first();

        $subject = 'Réponse de ' . ($conversation->tenant?->name ?? 'Nonalix IA');
        if ($originalInbound !== null) {
            if (preg_match('/^Sujet\s*:\s*(.+)$/m', $originalInbound->body, $matches)) {
                $subject = 'Re: ' . trim($matches[1]);
            }
        }

        try {
            Mail::to($recipientEmail)->send(new AgentReplyMail($subject, $message->body));

            $message->update(['status' => MessageStatus::Delivered]);
        } catch (Throwable $e) {
            $message->update(['status' => MessageStatus::Failed]);
            throw $e;
        }
    }
}
