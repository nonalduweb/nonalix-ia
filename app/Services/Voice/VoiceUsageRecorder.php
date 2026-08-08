<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\AiUsageLog;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trace ce que la voix consomme.
 *
 * Sur `ai_usage_logs`, la même table que le texte : un client veut savoir ce
 * que son agent lui coûte, pas ce que coûte chaque sous-système.
 *
 * Le coût reste à zéro tant que la grille n'est pas renseignée. Inventer un
 * tarif produirait des chiffres crédibles et faux, ce qui est pire que pas de
 * chiffre du tout.
 */
class VoiceUsageRecorder
{
    public function transcription(Conversation $conversation, ?string $agentId, float $seconds, int $latencyMs, string $model): void
    {
        $this->write($conversation, $agentId, 'stt', $model, [
            'audio_seconds' => round($seconds, 2),
            'cost_micros'   => (int) round($seconds * (int) config('elevenlabs.cost.stt_micros_per_second')),
            'latency_ms'    => $latencyMs,
        ]);
    }

    public function speech(Conversation $conversation, ?string $agentId, int $characters, int $latencyMs, string $model): void
    {
        $this->write($conversation, $agentId, 'tts', $model, [
            'characters'  => $characters,
            'cost_micros' => (int) round($characters * (int) config('elevenlabs.cost.tts_micros_per_char')),
            'latency_ms'  => $latencyMs,
        ]);
    }

    public function failure(Conversation $conversation, ?string $agentId, string $operation, string $reason): void
    {
        $this->write($conversation, $agentId, $operation, 'n/a', [
            'status'     => 'error',
            'error_code' => mb_substr($reason, 0, 60),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function write(Conversation $conversation, ?string $agentId, string $operation, string $model, array $extra): void
    {
        // Une écriture de journal ne doit jamais faire échouer la conversation
        // qu'elle observe.
        try {
            AiUsageLog::create(array_merge([
                'conversation_id' => $conversation->id,
                'agent_id'        => $agentId,
                'provider'        => 'elevenlabs',
                'model'           => $model,
                'operation'       => $operation,
                'status'          => 'success',
                'created_at'      => now(),
            ], $extra));
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Traçage de consommation vocale impossible.', [
                'operation' => $operation,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
