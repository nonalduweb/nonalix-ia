<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Data\Voice\SpeechResult;
use App\Data\Voice\TranscriptionResult;
use App\Exceptions\VoiceProviderException;
use App\Models\Agent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unique point de contact avec ElevenLabs.
 *
 * Tout passe par ici : aucun contrôleur, aucun job ne doit appeler l'API
 * directement. C'est ce qui garantit qu'une clé ne fuit pas dans un journal,
 * qu'un modèle se change en configuration, et qu'une panne se traduit partout
 * de la même façon.
 *
 * ElevenLabs ne fournit que l'oreille et la voix. Le raisonnement, la mémoire,
 * la base de connaissances et les outils restent à Nonalix IA.
 */
class ElevenLabsService
{
    /**
     * Clé effective pour cet agent.
     *
     * L'entreprise peut apporter la sienne ; à défaut, celle de la plateforme
     * s'applique. La clé de l'agent est chiffrée au repos par le cast Eloquent.
     */
    public function keyFor(?Agent $agent = null): ?string
    {
        $own = $agent?->elevenlabs_api_key;

        if (is_string($own) && trim($own) !== '') {
            return trim($own);
        }

        $platform = config('elevenlabs.api_key');

        return is_string($platform) && trim($platform) !== '' ? trim($platform) : null;
    }

    public function isConfigured(?Agent $agent = null): bool
    {
        return $this->keyFor($agent) !== null;
    }

    /**
     * Transcrit un contenu audio.
     *
     * @param  string  $audio  octets bruts du fichier
     *
     * @throws VoiceProviderException
     */
    public function transcribe(string $audio, string $filename, ?Agent $agent = null, ?string $language = null): TranscriptionResult
    {
        $this->assertAudioIsAcceptable($audio);

        $started = microtime(true);

        Log::channel('ai')->info('voice.transcription.started', [
            'bytes'    => strlen($audio),
            'agent_id' => $agent?->id,
        ]);

        $request = $this->client($agent)->attach('file', $audio, $filename);

        $payload = ['model_id' => (string) config('elevenlabs.models.stt')];

        // `null` vaut détection automatique : on ne force une langue que si
        // l'entreprise l'a explicitement choisie.
        if ($language !== null && $language !== 'auto') {
            $payload['language_code'] = $language;
        }

        try {
            $response = $request->post('/speech-to-text', $payload);
        } catch (Throwable $e) {
            $this->fail('voice.transcription.failed', 'stt_unreachable', $e->getMessage());
        }

        if (! $response->successful()) {
            $this->fail('voice.transcription.failed', 'stt_rejected', $this->errorMessage($response->json(), $response->status()));
        }

        $data = $response->json();
        $text = trim((string) ($data['text'] ?? ''));

        if ($text === '') {
            $this->fail('voice.transcription.failed', 'stt_empty', 'Transcription vide.');
        }

        $result = new TranscriptionResult(
            text: $text,
            language: $data['language_code'] ?? null,
            confidence: isset($data['language_probability']) ? (float) $data['language_probability'] : null,
            // La durée se lit sur le dernier mot horodaté : inutile de décoder
            // l'audio pour l'obtenir.
            seconds: $this->durationOf($data),
            latencyMs: (int) ((microtime(true) - $started) * 1000),
        );

        Log::channel('ai')->info('voice.transcription.completed', [
            'seconds'  => $result->seconds,
            'language' => $result->language,
            'ms'       => $result->latencyMs,
        ]);

        return $result;
    }

    /**
     * Synthétise un texte en audio.
     *
     * @param  'whatsapp'|'web'  $target  détermine le conteneur audio
     *
     * @throws VoiceProviderException
     */
    public function textToSpeech(
        string $text,
        string $voiceId,
        ?Agent $agent = null,
        string $target = 'web',
        bool $lowLatency = false,
    ): SpeechResult {
        $text = trim($text);

        if ($text === '') {
            $this->fail('voice.tts.failed', 'tts_empty_text', 'Rien à dire.');
        }

        $max = (int) config('elevenlabs.limits.max_tts_chars');

        // On tronque plutôt que d'échouer : une réponse un peu écourtée vaut
        // mieux qu'un silence, et le texte intégral reste en base.
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max);
        }

        $format = (string) config('elevenlabs.formats.'.$target, 'mp3_44100_128');
        $model  = $lowLatency
            ? (string) config('elevenlabs.models.tts_realtime')
            : (string) config('elevenlabs.models.tts_quality');

        $started = microtime(true);

        Log::channel('ai')->info('voice.tts.started', [
            'chars' => mb_strlen($text),
            'model' => $model,
            'format' => $format,
        ]);

        try {
            $response = $this->client($agent)->post(
                '/text-to-speech/'.$voiceId.'?output_format='.$format,
                ['text' => $text, 'model_id' => $model],
            );
        } catch (Throwable $e) {
            $this->fail('voice.tts.failed', 'tts_unreachable', $e->getMessage());
        }

        if (! $response->successful()) {
            $this->fail('voice.tts.failed', 'tts_rejected', $this->errorMessage($response->json(), $response->status()));
        }

        $audio = $response->body();

        if ($audio === '') {
            $this->fail('voice.tts.failed', 'tts_empty_audio', 'Audio vide.');
        }

        Log::channel('ai')->info('voice.tts.completed', [
            'bytes' => strlen($audio),
            'ms'    => (int) ((microtime(true) - $started) * 1000),
        ]);

        // Les formats `opus_*` d'ElevenLabs sortent encapsulés en Ogg : c'est
        // le conteneur qui compte pour WhatsApp, pas le codec seul.
        $isOgg = str_starts_with($format, 'opus') || str_starts_with($format, 'ogg');

        return new SpeechResult(
            audio: $audio,
            mimeType: $isOgg ? 'audio/ogg' : 'audio/mpeg',
            extension: $isOgg ? 'ogg' : 'mp3',
            characters: mb_strlen($text),
            model: $model,
            latencyMs: (int) ((microtime(true) - $started) * 1000),
        );
    }

    /**
     * Voix disponibles sur le compte.
     *
     * @return array<int, array{voice_id: string, name: string, description: ?string, preview_url: ?string}>
     */
    public function getVoices(?Agent $agent = null): array
    {
        try {
            $response = $this->client($agent)->get('/voices');
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return array_map(static fn (array $v) => [
            'voice_id'    => $v['voice_id'],
            'name'        => $v['name'] ?? $v['voice_id'],
            'description' => $v['labels']['description'] ?? $v['description'] ?? null,
            'preview_url' => $v['preview_url'] ?? null,
        ], $response->json('voices') ?? []);
    }

    /** Court échantillon pour laisser le client entendre une voix. */
    public function testVoice(string $voiceId, ?Agent $agent = null, string $language = 'fr'): SpeechResult
    {
        $texts = (array) config('elevenlabs.demo_text');

        return $this->textToSpeech(
            text: $texts[$language] ?? $texts['fr'],
            voiceId: $voiceId,
            agent: $agent,
            target: 'web',
        );
    }

    /** @return array{ok: bool, message: string} */
    public function healthCheck(?Agent $agent = null): array
    {
        if (! $this->isConfigured($agent)) {
            return ['ok' => false, 'message' => 'Aucune clé ElevenLabs configurée.'];
        }

        try {
            $response = $this->client($agent)->get('/voices');
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Service injoignable.'];
        }

        return match (true) {
            $response->successful()    => ['ok' => true,  'message' => 'Connexion établie.'],
            $response->status() === 401 => ['ok' => false, 'message' => 'Clé refusée ou permissions insuffisantes.'],
            default                     => ['ok' => false, 'message' => 'ElevenLabs a répondu '.$response->status().'.'],
        };
    }

    /**
     * Consommation du compte, si la clé porte la permission de lecture.
     *
     * @return array{used: ?int, limit: ?int, tier: ?string}
     */
    public function getUsage(?Agent $agent = null): array
    {
        try {
            $response = $this->client($agent)->get('/user/subscription');
        } catch (Throwable) {
            return ['used' => null, 'limit' => null, 'tier' => null];
        }

        if (! $response->successful()) {
            return ['used' => null, 'limit' => null, 'tier' => null];
        }

        return [
            'used'  => $response->json('character_count'),
            'limit' => $response->json('character_limit'),
            'tier'  => $response->json('tier'),
        ];
    }

    private function client(?Agent $agent): PendingRequest
    {
        $key = $this->keyFor($agent);

        if ($key === null) {
            $this->fail('voice.config.missing', 'no_api_key', 'Aucune clé ElevenLabs configurée.');
        }

        return Http::baseUrl((string) config('elevenlabs.base_url'))
            ->withHeaders(['xi-api-key' => $key])
            ->timeout((int) config('elevenlabs.limits.timeout_seconds'));
    }

    /** Le fichier mérite-t-il qu'on dépense un appel ? */
    private function assertAudioIsAcceptable(string $audio): void
    {
        if ($audio === '') {
            $this->fail('voice.transcription.failed', 'audio_empty', 'Fichier audio vide.');
        }

        $max = (int) config('elevenlabs.limits.max_audio_bytes');

        if (strlen($audio) > $max) {
            $this->fail('voice.transcription.failed', 'audio_too_large', 'Fichier audio trop volumineux.');
        }
    }

    /** @param array<string, mixed>|null $data */
    private function durationOf(?array $data): ?float
    {
        $words = $data['words'] ?? null;

        if (! is_array($words) || $words === []) {
            return null;
        }

        $last = end($words);

        return isset($last['end']) ? round((float) $last['end'], 2) : null;
    }

    /** @param array<string, mixed>|null $body */
    private function errorMessage(?array $body, int $status): string
    {
        $detail = $body['detail'] ?? null;

        if (is_array($detail)) {
            return (string) ($detail['message'] ?? $detail['status'] ?? 'HTTP '.$status);
        }

        return is_string($detail) ? $detail : 'HTTP '.$status;
    }

    /**
     * Journalise puis lève, sans jamais laisser filtrer la clé.
     *
     * @throws VoiceProviderException
     */
    private function fail(string $event, string $reason, string $message): never
    {
        Log::channel('ai')->error($event, ['reason' => $reason, 'message' => $message]);

        throw new VoiceProviderException($reason, $message);
    }
}
