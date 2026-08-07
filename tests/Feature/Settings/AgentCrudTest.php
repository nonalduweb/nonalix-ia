<?php

declare(strict_types=1);

use App\Models\Agent;

/*
| CRUD multi-agents.
|
| La création et la suppression s'appuient sur `agent.create` et `agent.delete`.
| Ces permissions n'existaient pas au catalogue : AgentPolicy les réclamait, et
| toute la fonctionnalité répondait 403, y compris au propriétaire. Ces tests
| verrouillent le catalogue autant que le comportement.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);
});

it('liste les agents de l\'entreprise', function () {
    Agent::factory()->create(['name' => 'Accueil']);

    $this->actingAsTenantUser($this->user)
        ->get('http://localhost/settings/agent')
        ->assertOk();
});

it('permet au propriétaire de créer un agent', function () {
    $this->actingAsTenantUser($this->user)
        ->get('http://localhost/settings/agent/create')
        ->assertOk();

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent', agentPayload(['name' => 'Commercial']))
        ->assertRedirect('http://localhost/settings/agent')
        ->assertSessionHasNoErrors();

    $this->actingForTenant($this->tenant);

    expect(Agent::query()->where('name', 'Commercial')->exists())->toBeTrue();
});

it('rattache le nouvel agent au tenant de l\'utilisateur', function () {
    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent', agentPayload(['name' => 'Cloisonné']));

    $this->actingForTenant($this->tenant);

    expect(Agent::query()->where('name', 'Cloisonné')->first())
        ->toBelongToTenant($this->tenant->id);
});

it('n\'autorise qu\'un seul agent actif à la fois', function () {
    $premier = Agent::factory()->create(['name' => 'Premier', 'is_active' => true]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent', agentPayload(['name' => 'Second', 'is_active' => true]));

    $this->actingForTenant($this->tenant);

    expect($premier->fresh()->is_active)->toBeFalse();
    expect(Agent::query()->where('is_active', true)->count())->toBe(1);
});

it('supprime un agent et bascule l\'activité sur un autre', function () {
    $actif   = Agent::factory()->create(['name' => 'Actif', 'is_active' => true]);
    $secours = Agent::factory()->create(['name' => 'Secours', 'is_active' => false]);

    $this->actingAsTenantUser($this->user)
        ->delete("http://localhost/settings/agent/{$actif->id}")
        ->assertRedirect('http://localhost/settings/agent');

    $this->actingForTenant($this->tenant);

    expect(Agent::query()->whereKey($actif->id)->exists())->toBeFalse();
    expect($secours->fresh()->is_active)->toBeTrue();
});

it('refuse de supprimer le dernier agent', function () {
    $seul = Agent::factory()->create();

    $this->actingAsTenantUser($this->user)
        ->delete("http://localhost/settings/agent/{$seul->id}")
        ->assertStatus(422);

    $this->actingForTenant($this->tenant);

    expect(Agent::query()->whereKey($seul->id)->exists())->toBeTrue();
});

it('refuse la création à un rôle sans la permission', function () {
    // Un opérateur voit la messagerie, pas la configuration de l'agent.
    [$tenant, $operateur] = $this->createTenantWithUser('agent', [
        'conversations.view', 'leads.view',
    ]);
    $this->actingForTenant($tenant);

    $this->actingAsTenantUser($operateur)
        ->post('http://localhost/settings/agent', agentPayload())
        ->assertStatus(403);
});

it('refuse la suppression à un rôle sans la permission', function () {
    [$tenant, $operateur] = $this->createTenantWithUser('agent', [
        'conversations.view', 'agent.view',
    ]);
    $this->actingForTenant($tenant);

    $agent = Agent::factory()->create();
    Agent::factory()->create();

    $this->actingAsTenantUser($operateur)
        ->delete("http://localhost/settings/agent/{$agent->id}")
        ->assertStatus(403);
});

it('conserve la clé API lorsque le champ est laissé vide', function () {
    $agent = Agent::factory()->create(['api_key' => 'sk-secret-existante']);

    $this->actingAsTenantUser($this->user)
        ->put("http://localhost/settings/agent/{$agent->id}", agentPayload(['api_key' => '']))
        ->assertSessionHasNoErrors();

    $this->actingForTenant($this->tenant);

    expect($agent->fresh()->api_key)->toBe('sk-secret-existante');
});

it('accepte une URL de webhook n8n vide', function () {
    $agent = Agent::factory()->create();

    // Le formulaire envoie une chaîne vide quand le client n'utilise pas n8n :
    // la règle `url` ne doit pas la rejeter.
    $this->actingAsTenantUser($this->user)
        ->put("http://localhost/settings/agent/{$agent->id}", agentPayload([
            'settings' => ['n8n_webhook_url' => ''],
        ]))
        ->assertSessionHasNoErrors();
});

/** Corps valide pour le formulaire d'agent. */
function agentPayload(array $overrides = []): array
{
    return array_merge([
        'name'          => 'Assistant',
        'provider'      => 'openai',
        'model'         => 'gpt-4.1-mini',
        'temperature'   => 0.3,
        'max_tokens'    => 1024,
        'language'      => 'fr',
        'memory_window' => 12,
        'rag_top_k'     => 5,
        'rag_min_score' => 0.75,
        'is_active'     => true,
    ], $overrides);
}
