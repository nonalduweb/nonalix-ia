<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Jobs\Voice\SynthesizeReplyJob;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\AI\AgentRunner;
use Illuminate\Support\Facades\Queue;

/*
| Les trois modes de reponse, dans les six combinaisons.
|
| C'est le critere d'acceptation : `same_as_user` doit signifier texte pour du
| texte et vocal pour du vocal — jamais une interpretation.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $account = WhatsAppAccount::factory()->create();
    $this->conversation = Conversation::factory()->for($account, 'whatsappAccount')->create();

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->andReturn([
        'content'  => 'Nos tarifs demarrent a 500 euros.',
        'handover' => false,
        'metadata' => [],
    ]);
    $this->app->instance(AgentRunner::class, $runner);

    $this->agentWith = function (string $mode, bool $enabled = true) {
        return Agent::factory()->create([
            'is_active'         => true,
            'handover_keywords' => [],
            'enabled_tools'     => [],
            'settings'          => [
                'voice_enabled'        => $enabled,
                'voice_response_mode'  => $mode,
                'elevenlabs_voice_id'  => 'voix-de-test',
            ],
        ]);
    };

    $this->reply = function (bool $incomingWasAudio) {
        app()->call([new GenerateAgentReplyJob(
            $this->tenant->id,
            $this->conversation->id,
            'Quels sont vos tarifs ?',
            $incomingWasAudio,
        ), 'handle']);
    };
});

/** Le message sortant est-il parti vers la synthese, ou vers le texte ? */
function partiEnVocal(): bool
{
    $vocal = false;
    Queue::assertPushed(SynthesizeReplyJob::class, function () use (&$vocal) {
        $vocal = true;

        return true;
    });

    return $vocal;
}

it('texte entrant + mode texte -> reponse texte', function () {
    Queue::fake();
    ($this->agentWith)('text');

    ($this->reply)(false);

    Queue::assertNotPushed(SynthesizeReplyJob::class);
    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('texte entrant + mode vocal -> reponse vocale', function () {
    Queue::fake();
    ($this->agentWith)('voice');

    ($this->reply)(false);

    // `voice` signifie toujours vocal, quel que soit le format recu.
    Queue::assertPushed(SynthesizeReplyJob::class);
    Queue::assertNotPushed(SendWhatsAppMessageJob::class);
});

it('texte entrant + meme format -> reponse texte', function () {
    Queue::fake();
    ($this->agentWith)('same_as_user');

    ($this->reply)(false);

    Queue::assertNotPushed(SynthesizeReplyJob::class);
    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('vocal entrant + mode texte -> reponse texte', function () {
    Queue::fake();
    ($this->agentWith)('text');

    ($this->reply)(true);

    // `text` signifie jamais de vocal, meme sur un message vocal recu.
    Queue::assertNotPushed(SynthesizeReplyJob::class);
    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('vocal entrant + mode vocal -> reponse vocale', function () {
    Queue::fake();
    ($this->agentWith)('voice');

    ($this->reply)(true);

    Queue::assertPushed(SynthesizeReplyJob::class);
});

it('vocal entrant + meme format -> reponse vocale', function () {
    Queue::fake();
    ($this->agentWith)('same_as_user');

    ($this->reply)(true);

    Queue::assertPushed(SynthesizeReplyJob::class);
    Queue::assertNotPushed(SendWhatsAppMessageJob::class);
});

it('ne parle jamais si les fonctions vocales sont desactivees', function () {
    Queue::fake();
    // Mode `voice` mais interrupteur general ferme : le texte l'emporte.
    ($this->agentWith)('voice', enabled: false);

    ($this->reply)(true);

    Queue::assertNotPushed(SynthesizeReplyJob::class);
    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('conserve toujours le texte de la reponse en base', function () {
    Queue::fake();
    ($this->agentWith)('voice');

    ($this->reply)(true);

    $this->actingForTenant($this->tenant);
    $message = Message::query()->where('direction', MessageDirection::Outbound)->first();

    // Le texte alimente la recherche, l'historique et les statistiques : il
    // reste la source de verite, meme quand la reponse part en audio.
    expect($message->body)->toBe('Nos tarifs demarrent a 500 euros.');
});

it('decide le mode dans le modele, de facon deterministe', function () {
    $voice = ($this->agentWith)('voice');
    $text  = ($this->agentWith)('text');
    $same  = ($this->agentWith)('same_as_user');

    expect($voice->shouldReplyWithVoice(false))->toBeTrue()
        ->and($voice->shouldReplyWithVoice(true))->toBeTrue();

    expect($text->shouldReplyWithVoice(false))->toBeFalse()
        ->and($text->shouldReplyWithVoice(true))->toBeFalse();

    expect($same->shouldReplyWithVoice(false))->toBeFalse()
        ->and($same->shouldReplyWithVoice(true))->toBeTrue();
});

it('retombe sur `same_as_user` devant un mode inconnu', function () {
    $agent = Agent::factory()->create([
        'settings' => ['voice_enabled' => true, 'voice_response_mode' => 'n_importe_quoi'],
    ]);

    expect($agent->voiceResponseMode())->toBe('same_as_user');
});
