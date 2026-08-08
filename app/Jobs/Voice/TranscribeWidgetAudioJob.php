<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Enums\ConversationStatus;
use App\Exceptions\VoiceProviderException;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Message;
use App\Services\Voice\ElevenLabsService;
use App\Services\Voice\VoiceUsageRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transcrit un message vocal envoyé depuis le widget.
 *
 * Jumeau de TranscribeInboundAudioJob, à une différence près : l'audio est
 * déjà chez nous, le navigateur l'a téléversé. Il n'y a donc rien à
 * télécharger chez un fournisseur tiers.
 *
 * Comme pour WhatsApp, la transcription devient le corps du message. C'est ce
 * qui fait qu'un visiteur peut écrire, puis parler, puis réécrire : pour
 * l'agent, c'est une seule conversation, avec un seul fil de mémoire et les
 * mêmes outils.
 */
class TranscribeWidgetAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [5, 15];

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

            // Déjà transcrit : un rejeu ne relance pas une génération.
            if (($message->media['transcription'] ?? null) !== null) {
                return;
            }

            if ($agent === null || ! $agent->voiceEnabled()) {
                return;
            }

            $path = $message->media['storage_path'] ?? null;

            if (! is_string($path) || ! Storage::disk('media')->exists($path)) {
                return;
            }

            try {
                $result = $voice->transcribe(
                    audio: Storage::disk('media')->get($path),
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

                $message->forceFill([
                    'body'  => $result->text,
                    'media' => array_merge($message->media ?? [], [
                        'transcription'    => $result->text,
                        'duration_seconds' => $result->seconds,
                        'language'         => $result->language,
                    ]),
                ])->save();

                if (! $conversation->shouldAiRespond()) {
                    return;
                }

                GenerateAgentReplyJob::dispatch(
                    tenantId: $tenant->id,
                    conversationId: $conversation->id,
                    incomingText: $result->text,
                    incomingWasAudio: true,
                )->onQueue('ai');
            } catch (VoiceProviderException $e) {
                $usage->failure($conversation, $agent->id, 'stt', $e->reason);

                if ($this->attempts() >= $this->tries) {
                    $this->giveUp($conversation, $e->reason);

                    return;
                }

                throw $e;
            } catch (Throwable $e) {
                Log::channel('ai')->error('Échec de transcription depuis le widget.', [
                    'message_id' => $this->messageId,
                    'error'      => $e->getMessage(),
                ]);

                if ($this->attempts() >= $this->tries) {
                    $this->giveUp($conversation, 'transcription_failed');

                    return;
                }

                throw $e;
            }
        });
    }

    /** Le visiteur ne doit pas rester sans interlocuteur. */
    private function giveUp($conversation, string $reason): void
    {
        Log::channel('ai')->warning('Message vocal du widget non transcrit.', [
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
