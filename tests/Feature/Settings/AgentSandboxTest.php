<?php

declare(strict_types=1);

use App\Contracts\AI\ChatProvider;
use App\Data\AI\ChatResponse;
use App\Data\AI\TokenUsage;
use App\Enums\AiProvider;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiProviderManager;

/*
| Banc d'essai.
|
| Il doit permettre d'entendre son agent sans rien publier — et surtout sans
| rien ecrire : ni contact, ni conversation, ni message. C'est ce qui le rend
| utilisable a volonte sans polluer la boite de reception du client.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $this->agent = Agent::factory()->create(['is_active' => true]);

    // Fournisseur simule : aucun appel reseau, reponse maitrisee.
    $this->provider = new class implements ChatProvider
    {
        public array $received = [];

        public function name(): AiProvider
        {
            return AiProvider::OpenAI;
        }

        public function defaultModel(): string
        {
            return 'gpt-4.1-mini';
        }

        public function supportsTools(): bool
        {
            return true;
        }

        public function chat(\App\Data\AI\ChatRequest $request): ChatResponse
        {
            $this->received[] = $request;

            return new ChatResponse(
                provider: AiProvider::OpenAI,
                model: 'gpt-4.1-mini',
                content: 'Bonjour, je peux reserver une table.',
                toolCalls: [],
                usage: new TokenUsage(10, 20),
                finishReason: 'stop',
                costMicros: 0,
                latencyMs: 12,
            );
        }
    };

    $manager = Mockery::mock(AiProviderManager::class);
    $manager->shouldReceive('chatFor')->andReturn($this->provider);
    $manager->shouldReceive('fallbackChat')->andReturn(null);
    $this->app->instance(AiProviderManager::class, $manager);
});

it('repond a un message d\'essai', function () {
    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", [
            'message' => 'Bonjour, vous avez de la place ce soir ?',
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Bonjour, je peux reserver une table.')
        ->assertJsonStructure(['reply', 'debug' => ['rag_chunks', 'total_ms']]);
});

it('n\'ecrit rien en base', function () {
    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Bonjour'])
        ->assertOk();

    $this->actingForTenant($this->tenant);

    // Le banc d'essai ne doit polluer ni la boite de reception ni les contacts.
    expect(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0);
});

it('garde le fil de la conversation d\'un message a l\'autre', function () {
    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Premier'])
        ->assertOk();

    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Second'])
        ->assertOk();

    // Le second appel doit porter l'echange precedent, sinon l'essai ne
    // refleterait pas ce que vit un vrai visiteur.
    $dernier = end($this->provider->received);
    $contenus = array_map(fn ($m) => $m->content, $dernier->messages);

    expect($contenus)->toContain('Premier')
        ->and($contenus)->toContain('Bonjour, je peux reserver une table.')
        ->and($contenus)->toContain('Second');
});

it('n\'arme aucun outil', function () {
    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Bonjour'])
        ->assertOk();

    // Un essai ne doit creer aucun prospect reel ni envoyer d'e-mail.
    expect($this->provider->received[0]->tools)->toBe([]);
});

it('repart de zero apres reinitialisation', function () {
    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Premier'])
        ->assertOk();

    $this->actingAsTenantUser($this->user)
        ->deleteJson("http://localhost/settings/agent/{$this->agent->id}/essai")
        ->assertOk();

    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Apres remise a zero'])
        ->assertOk();

    $contenus = array_map(fn ($m) => $m->content, end($this->provider->received)->messages);

    expect($contenus)->not->toContain('Premier');
});

it('valide le message', function () {
    $this->actingAsTenantUser($this->user)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => ''])
        ->assertStatus(422);
});

it('refuse l\'essai d\'un agent d\'une autre entreprise', function () {
    [$autre, $intrus] = $this->createTenantWithUser();

    $this->actingAsTenantUser($intrus)
        ->postJson("http://localhost/settings/agent/{$this->agent->id}/essai", ['message' => 'Bonjour'])
        ->assertStatus(404);
});
