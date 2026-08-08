<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Enums\ConversationStatus;
use App\Exceptions\VoiceProviderException;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Conversation;
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
 * Transforme une note vocale reçue en texte, puis passe la main au moteur.
 *
 * Le message vocal existe déjà en base quand ce job démarre : le webhook l'a
 * enregistré avec son `media`. On ne fait qu'y ajouter la transcription, dans
 * `body` — ce qui suffit à la faire entrer dans la mémoire conversationnelle
 * sans qu'aucune autre ligne de code ne change. C'est ce qui garantit la
 * mémoire unifiée : pour l'agent, une note vocale transcrite EST un message.
 */
class TranscribeInboundAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [10, 30];

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

            // Déjà transcrit : un rejeu ne doit pas relancer une génération.
            if (($message->media['transcription'] ?? null) !== null) {
                return;
            }

            if ($agent === null || ! $agent->voiceEnabled()) {
                return;
            }

            $mediaId = $message->media['id'] ?? null;

            if (! is_string($mediaId) || $mediaId === '') {
                return;
            }

            try {
                $audio = $this->download($conversation, $mediaId);

                if ($audio === null) {
                    $this->giveUp($conversation, $agent->id, 'media_unavailable');

                    return;
                }

                $path = $this->store($tenant->id, $message->id, $audio);

                $result = $voice->transcribe(
                    audio: $audio,
                    filename: basename($path),
                    agent: $agent,
                    language: $agent->voiceLanguage(),
                );

                $usage->transcription(
                    $conversation,
                    $agent->id,
                    $result->seconds ?? 0.0,
                    $result->latencyMs ?? 0,
                    (string) config('elevenlabs.models.stt'),
                );

                // La transcription devient le corps du message : elle alimente
                // la mémoire, la recherche et l'affichage, sans traitement
                // particulier ailleurs.
                $message->forceFill([
                    'body'  => $result->text,
                    'media' => array_merge($message->media ?? [], [
                        'storage_path'     => $path,
                        'transcription'    => $result->text,
                        'duration_seconds' => $result->seconds,
                        'language'         => $result->language,
                    ]),
                ])->save();

                if (! $conversation->shouldAiRespond()) {
                    return;
                }

                // À partir d'ici, plus rien ne distingue une note vocale d'un
                // message écrit : même job, même mémoire, mêmes outils.
                GenerateAgentReplyJob::dispatch(
                    tenantId: $tenant->id,
                    conversationId: $conversation->id,
                    incomingText: $result->text,
                    incomingWasAudio: true,
                )->onQueue('ai');
            } catch (VoiceProviderException $e) {
                $usage->failure($conversation, $agent->id, 'stt', $e->reason);

                // Dernière tentative : on laisse une trace lisible dans le fil
                // plutôt qu'un message vide, et un humain prend la main.
                if ($this->attempts() >= $this->tries) {
                    $this->giveUp($conversation, $agent->id, $e->reason);

                    return;
                }

                throw $e;
            } catch (Throwable $e) {
                Log::channel('ai')->error('Échec du traitement d\'une note vocale.', [
                    'message_id' => $this->messageId,
                    'error'      => $e->getMessage(),
                ]);

                if ($this->attempts() >= $this->tries) {
                    $this->giveUp($conversation, $agent->id, 'transcription_failed');

                    return;
                }

                throw $e;
            }
        });
    }

    /** @throws Throwable */
    private function download(Conversation $conversation, string $mediaId): ?string
    {
        $account = $conversation->whatsappAccount;

        if ($account === null) {
            return null;
        }

        return CloudApiClient::for($account)->downloadMedia($mediaId);
    }

    /**
     * Range l'audio sur le disque privé, cloisonné par entreprise.
     *
     * Jamais dans `public/` : une note vocale peut contenir un nom, un numéro,
     * une adresse. La purge programmée l'effacera selon la rétention configurée.
     */
    private function store(string $tenantId, string $messageId, string $audio): string
    {
        $path = $tenantId.'/voice/'.$messageId.'.ogg';

        Storage::disk('media')->put($path, $audio);

        return $path;
    }

    /** Marque l'échec dans le fil et rend la main à un humain. */
    private function giveUp(Conversation $conversation, ?string $agentId, string $reason): void
    {
        Log::channel('ai')->warning('Note vocale non transcrite.', [
            'conversation_id' => $conversation->id,
            'reason'          => $reason,
        ]);

        $conversation->forceFill([
            'ai_enabled'      => false,
            'handover_at'     => now(),
            'handover_reason' => 'vocal_non_transcrit',
            'status'          => ConversationStatus::Pending,
        ])->save();
    }
}
