<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\AI\AgentRunner;

/*
| Le job qui fait parler l'agent.
|
| Il n'etait couvert par AUCUN test : une reecriture avait pu en supprimer le
| verrou, les horaires, les mots-cles de transfert et le controle de quota, et
| meme le rendre totalement inoperant, sans qu'une seule assertion ne bronche.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $this->agent = Agent::factory()->create([
        'is_active'         => true,
        'handover_keywords' => ['humain', 'conseiller'],
        'enabled_tools'     => [],
    ]);

    $account = WhatsAppAccount::factory()->create();
    $this->conversation = Conversation::factory()->for($account, 'whatsappAccount')->create();

    // Moteur simule : le job est teste, pas le fournisseur.
    $this->runner = Mockery::mock(AgentRunner::class);
    $this->app->instance(AgentRunner::class, $this->runner);

    $this->expectGeneration = function (string $content = 'Bonjour, je peux vous aider.') {
        $this->runner->shouldReceive('run')->once()->andReturn([
            'content'  => $content,
            'handover' => false,
            'metadata' => [],
        ]);
    };

    $this->run = fn () => app()->call(
        [new GenerateAgentReplyJob($this->tenant->id, $this->conversation->id, $this->text ?? 'Bonjour'), 'handle'],
    );
});

it('s\'execute et repond', function () {
    ($this->expectGeneration)();

    ($this->run)();

    $this->actingForTenant($this->tenant);

    expect(Message::query()->where('body', 'Bonjour, je peux vous aider.')->exists())->toBeTrue();
});

it('ne repond pas sur une conversation reprise par un humain', function () {
    $this->runner->shouldNotReceive('run');
    $this->conversation->forceFill(['handover_at' => now(), 'ai_enabled' => false])->save();

    ($this->run)();

    $this->actingForTenant($this->tenant);
    expect(Message::query()->count())->toBe(0);
});

it('ne repond pas sur une conversation close', function () {
    // `ai_enabled` seul ne suffit pas : une conversation close n'attend plus
    // de reponse, meme si l'IA n'y a pas ete coupee explicitement.
    $this->runner->shouldNotReceive('run');
    $this->conversation->forceFill(['status' => ConversationStatus::Closed])->save();

    ($this->run)();

    $this->actingForTenant($this->tenant);
    expect(Message::query()->count())->toBe(0);
});

it('ne repond pas si l\'agent est desactive', function () {
    // C'est aussi ce que le widget promet au visiteur via `auto_reply`.
    $this->runner->shouldNotReceive('run');
    $this->agent->forceFill(['is_active' => false])->save();

    ($this->run)();

    $this->actingForTenant($this->tenant);
    expect(Message::query()->count())->toBe(0);
});

it('transfere sur un mot-cle sans appeler le modele', function () {
    // Le contact ne doit pas attendre une generation pour etre transfere, ni
    // risquer que le modele passe outre.
    $this->runner->shouldNotReceive('run');
    $this->text = 'Je veux parler a un conseiller';

    ($this->run)();

    $this->actingForTenant($this->tenant);
    $fresh = $this->conversation->fresh();

    expect($fresh->handover_at)->not->toBeNull()
        ->and($fresh->ai_enabled)->toBeFalse()
        ->and($fresh->handover_reason)->toBe('demande_explicite');
});

it('retient la reponse en brouillon sur le canal e-mail en mode assiste', function () {
    ($this->expectGeneration)();

    $this->conversation->forceFill(['channel' => 'email'])->save();
    $this->agent->forceFill(['settings' => ['email_mode' => 'assisted']])->save();

    ($this->run)();

    $this->actingForTenant($this->tenant);
    $message = Message::query()->where('direction', MessageDirection::Outbound)->first();

    // Rien ne part sans un humain : le courrier engage plus qu'un chat.
    expect($message->status)->toBe(MessageStatus::Draft);
});

it('marque la reponse comme distribuee sur le canal web', function () {
    ($this->expectGeneration)();

    $this->conversation->forceFill(['channel' => 'web'])->save();

    ($this->run)();

    $this->actingForTenant($this->tenant);
    $message = Message::query()->where('direction', MessageDirection::Outbound)->first();

    expect($message->status)->toBe(MessageStatus::Delivered);
});

it('n\'ecrit aucun message si le moteur ne produit pas de texte', function () {
    // Cas d'un outil qui a transfere : la conversation est deja a jour.
    $this->runner->shouldReceive('run')->once()->andReturn([
        'content'  => null,
        'handover' => true,
        'metadata' => [],
    ]);

    ($this->run)();

    $this->actingForTenant($this->tenant);
    expect(Message::query()->where('direction', MessageDirection::Outbound)->count())->toBe(0);
});
