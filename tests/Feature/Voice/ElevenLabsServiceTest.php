<?php

declare(strict_types=1);

use App\Exceptions\VoiceProviderException;
use App\Models\Agent;
use App\Services\Voice\ElevenLabsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
| Couche ElevenLabs.
|
| Simulee de bout en bout : un test ne doit jamais consommer le quota reel du
| compte. Les reponses reproduisent la forme exacte constatee sur l'API.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    config([
        'elevenlabs.api_key'          => 'cle-plateforme',
        'elevenlabs.base_url'         => 'https://api.elevenlabs.io/v1',
        'elevenlabs.formats.whatsapp' => 'opus_48000_64',
        'elevenlabs.formats.web'      => 'mp3_44100_128',
    ]);

    $this->service = app(ElevenLabsService::class);
});

it('utilise la cle de la plateforme par defaut', function () {
    $agent = Agent::factory()->create();

    expect($this->service->keyFor($agent))->toBe('cle-plateforme');
});

it('prefere la cle de l\'entreprise quand elle en a une', function () {
    // Apportee par le client, chiffree au repos par le cast Eloquent.
    $agent = Agent::factory()->create(['elevenlabs_api_key' => 'cle-du-client']);

    expect($this->service->keyFor($agent))->toBe('cle-du-client');
});

it('ne fuit jamais la cle hors du serveur', function () {
    $agent = Agent::factory()->create(['elevenlabs_api_key' => 'cle-du-client']);

    // Ni en JSON, ni en tableau : c'est ce qui alimente les reponses Inertia.
    expect($agent->toArray())->not->toHaveKey('elevenlabs_api_key');
    expect(json_encode($agent))->not->toContain('cle-du-client');
});

it('se declare non configure sans aucune cle', function () {
    config(['elevenlabs.api_key' => null]);

    expect($this->service->isConfigured(Agent::factory()->create()))->toBeFalse();
});

it('transcrit un audio et en tire la duree', function () {
    Http::fake(['*/speech-to-text' => Http::response([
        'text'                 => 'Bonjour, je voudrais reserver une table.',
        'language_code'        => 'fra',
        'language_probability' => 0.98,
        // La duree se lit sur le dernier mot : pas besoin de decoder l'audio.
        'words'                => [
            ['text' => 'Bonjour', 'start' => 0.1, 'end' => 0.6],
            ['text' => 'table',   'start' => 2.0, 'end' => 2.64],
        ],
    ])]);

    $result = $this->service->transcribe('octets-audio', 'note.ogg');

    expect($result->text)->toBe('Bonjour, je voudrais reserver une table.')
        ->and($result->language)->toBe('fra')
        ->and($result->seconds)->toBe(2.64)
        ->and($result->duration())->toBe('00:02');
});

it('refuse un audio vide sans appeler le fournisseur', function () {
    Http::fake();

    expect(fn () => $this->service->transcribe('', 'vide.ogg'))
        ->toThrow(VoiceProviderException::class);

    Http::assertNothingSent();
});

it('refuse un audio trop volumineux sans appeler le fournisseur', function () {
    Http::fake();
    config(['elevenlabs.limits.max_audio_bytes' => 10]);

    expect(fn () => $this->service->transcribe(str_repeat('a', 50), 'gros.ogg'))
        ->toThrow(VoiceProviderException::class);

    Http::assertNothingSent();
});

it('signale une transcription vide plutot que de la propager', function () {
    Http::fake(['*/speech-to-text' => Http::response(['text' => '   '])]);

    expect(fn () => $this->service->transcribe('octets', 'note.ogg'))
        ->toThrow(VoiceProviderException::class);
});

it('rend de l\'Ogg pour WhatsApp et du MP3 pour le navigateur', function () {
    Http::fake(['*/text-to-speech/*' => Http::response('OggS-octets-audio')]);

    $whatsapp = $this->service->textToSpeech('Bonjour', 'voix-1', null, 'whatsapp');
    $web      = $this->service->textToSpeech('Bonjour', 'voix-1', null, 'web');

    // WhatsApp n'affiche une VRAIE note vocale que pour de l'Opus en Ogg.
    expect($whatsapp->mimeType)->toBe('audio/ogg')->and($whatsapp->extension)->toBe('ogg');
    expect($web->mimeType)->toBe('audio/mpeg')->and($web->extension)->toBe('mp3');
});

it('choisit le modele rapide quand la latence prime', function () {
    Http::fake(['*/text-to-speech/*' => Http::response('audio')]);

    config([
        'elevenlabs.models.tts_quality'  => 'modele-qualite',
        'elevenlabs.models.tts_realtime' => 'modele-rapide',
    ]);

    expect($this->service->textToSpeech('Bonjour', 'v', null, 'web', true)->model)->toBe('modele-rapide');
    expect($this->service->textToSpeech('Bonjour', 'v', null, 'web', false)->model)->toBe('modele-qualite');
});

it('tronque un texte trop long au lieu d\'echouer', function () {
    Http::fake(['*/text-to-speech/*' => Http::response('audio')]);
    config(['elevenlabs.limits.max_tts_chars' => 10]);

    // Une reponse ecourtee vaut mieux qu'un silence ; le texte integral reste
    // en base.
    expect($this->service->textToSpeech(str_repeat('a', 100), 'v')->characters)->toBe(10);
});

it('traduit un refus du fournisseur en panne exploitable', function () {
    Http::fake(['*/text-to-speech/*' => Http::response(
        ['detail' => ['message' => 'Quota depasse', 'status' => 'quota_exceeded']],
        401,
    )]);

    expect(fn () => $this->service->textToSpeech('Bonjour', 'v'))
        ->toThrow(VoiceProviderException::class);
});

it('survit a un fournisseur injoignable', function () {
    Http::fake(fn () => throw new ConnectionException('injoignable'));

    expect(fn () => $this->service->transcribe('octets', 'note.ogg'))
        ->toThrow(VoiceProviderException::class);
});

it('rend une liste de voix vide plutot que d\'echouer', function () {
    // La liste sert a peupler un menu deroulant : son absence ne doit pas
    // empecher la page de s'afficher.
    Http::fake(['*/voices' => Http::response([], 401)]);

    expect($this->service->getVoices())->toBe([]);
});

it('rapporte une connexion etablie', function () {
    Http::fake(['*/voices' => Http::response(['voices' => []])]);

    expect($this->service->healthCheck()['ok'])->toBeTrue();
});

it('distingue une cle refusee d\'une panne', function () {
    // Un second Http::fake() n'ecrase pas le premier — le stub initial
    // continuerait de repondre. D'ou un test par etat.
    Http::fake(['*/voices' => Http::response([], 401)]);

    expect($this->service->healthCheck())
        ->ok->toBeFalse()
        ->message->toContain('permissions');
});

it('signale l\'absence de cle sans appeler le fournisseur', function () {
    Http::fake();
    config(['elevenlabs.api_key' => null]);

    expect($this->service->healthCheck()['ok'])->toBeFalse();
    Http::assertNothingSent();
});
