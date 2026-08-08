<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Exceptions\VoiceProviderException;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\Message;
use App\Services\Voice\ElevenLabsService;
use App\Services\Voice\VoiceUsageRecorder;
use App\Services\WhatsApp\CloudApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Donne une voix à une réponse déjà rédigée.
 *
 * Le texte est écrit en base AVANT que ce job ne démarre, et il y reste quoi
 * qu'il advienne : c'est lui qui alimente la recherche, l'historique et les
 * statistiques, et qui permet à un opérateur de comprendre un échange sans
 * écouter chaque fichier.
 *
 * En cas d'échec de la synthèse, la réponse part donc en texte. Un client qui
 * attend ne doit pas se heurter au silence parce qu'un fournisseur tiers a
 * hoqueté.
 */
class SynthesizeReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public array $backoff = [10];

    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
    ) {}

    public function handle(ElevenLabsService $voice, VoiceUsageRecorder $usage): void
    {
        $this->withTenant($this->tenantId, function ($tenant) use ($voice, $usage) {
            $message = Message::query()->with('conversation')->find($this->messageId);

            if ($message === null || $message->conversation === null) {
                return;
            }

            $conversation = $message->conversation;
            $agent        = $conversation->agent ?? $tenant->activeAgent();
            $text         = (string) $message->body;
            $voiceId      = $agent?->voiceId();

            if ($agent === null || $voiceId === null || trim($text) === '') {
                $this->fallbackToText($conversation, $message, 'voix_non_configuree');

                return;
            }

            $isWhatsApp = $conversation->channel === 'whatsapp';

            try {
                $speech = $voice->textToSpeech(
                    text: $text,
                    voiceId: $voiceId,
                    agent: $agent,
                    target: $isWhatsApp ? 'whatsapp' : 'web',
                );

                $usage->speech($conversation, $agent->id, $speech->characters, $speech->latencyMs ?? 0, $speech->model);
            } catch (VoiceProviderException $e) {
                $usage->failure($conversation, $agent->id, 'tts', $e->reason);

                if ($this->attempts() >= $this->tries) {
                    $this->fallbackToText($conversation, $message, $e->reason);

                    return;
                }

                throw $e;
            }

            $path = $this->store($tenant->id, $message->id, $speech->audio, $speech->extension);

            $message->forceFill([
                'type'  => MessageType::Audio,
                'media' => array_merge($message->media ?? [], [
                    'storage_path' => $path,
                    'mime_type'    => $speech->mimeType,
                    // Le texte reste la source de vérité : l'audio n'en est
                    // qu'une restitution.
                    'spoken_text'  => $text,
                    'characters'   => $speech->characters,
                ]),
            ])->save();

            if (! $isWhatsApp) {
                // Widget et banc d'essai lisent l'audio depuis la base : rien
                // à transporter.
                $message->update(['status' => MessageStatus::Delivered]);

                return;
            }

            $this->sendAsVoiceNote($tenant->id, $message, $speech->audio, $speech->mimeType);
        });
    }

    /**
     * Téléverse puis envoie la note vocale.
     *
     * L'échec retombe sur le texte : mieux vaut une réponse écrite qu'aucune.
     */
    private function sendAsVoiceNote(string $tenantId, Message $message, string $audio, string $mimeType): void
    {
        $conversation = $message->conversation;
        $account      = $conversation->whatsappAccount;
        $contact      = $conversation->contact;

        if ($account === null || $contact === null) {
            $this->fallbackToText($conversation, $message, 'compte_whatsapp_absent');

            return;
        }

        try {
            $client  = CloudApiClient::for($account);
            $mediaId = $client->uploadMedia($audio, 'reponse-'.$message->id.'.ogg', $mimeType);
            $wamid   = $client->sendVoiceNote($contact->wa_id, $mediaId);

            $message->forceFill([
                'wamid'   => $wamid !== '' ? $wamid : $message->wamid,
                'status'  => MessageStatus::Sent,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::channel('ai')->error('Envoi de la note vocale impossible.', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            $this->fallbackToText($conversation, $message, 'envoi_vocal_impossible');
        }
    }

    /**
     * Repli : on renvoie la réponse par écrit.
     *
     * Le message redevient un texte ordinaire et repart par le transporteur
     * habituel. Le contact reçoit une réponse — c'est tout ce qui compte.
     */
    private function fallbackToText($conversation, Message $message, string $reason): void
    {
        Log::channel('ai')->warning('Repli en texte après échec vocal.', [
            'message_id' => $message->id,
            'reason'     => $reason,
        ]);

        $message->forceFill([
            'type'   => MessageType::Text,
            'status' => MessageStatus::Queued,
        ])->save();

        match ($conversation->channel) {
            'web'   => $message->update(['status' => MessageStatus::Delivered]),
            'email' => \App\Jobs\AI\SendEmailMessageJob::dispatch($conversation->tenant_id, $message->id)->onQueue('email'),
            default => SendWhatsAppMessageJob::dispatch($conversation->tenant_id, $message->id)->onQueue('whatsapp'),
        };
    }

    /** Disque privé, cloisonné par entreprise, soumis à la purge programmée. */
    private function store(string $tenantId, string $messageId, string $audio, string $extension): string
    {
        $path = $tenantId.'/voice/reponse-'.$messageId.'.'.$extension;

        Storage::disk('media')->put($path, $audio);

        return $path;
    }
}
