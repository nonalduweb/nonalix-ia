<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Services\Agent\AgentTemplateLibrary;

/*
| Premier ecran d'un client qui vient d'arriver.
|
| Il ne sait pas arbitrer une temperature ni un seuil de pertinence, mais il
| reconnait son metier : la galerie doit passer devant tant qu'aucun agent
| n'est actif, et un clic doit suffire a obtenir un agent redige.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);
});

it('propose la galerie de metiers tant qu\'aucun agent n\'est actif', function () {
    Agent::factory()->create(['is_active' => false]);

    $this->actingAsTenantUser($this->user)
        ->get('http://localhost/settings/agent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hasActiveAgent', false)
            ->has('templates.restaurant'));
});

it('signale qu\'un agent est deja actif', function () {
    Agent::factory()->create(['is_active' => true]);

    $this->actingAsTenantUser($this->user)
        ->get('http://localhost/settings/agent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasActiveAgent', true));
});

it('n\'expose que ce qui sert a choisir, pas le prompt entier', function () {
    Agent::factory()->create(['is_active' => false]);

    // Le prompt systeme est long : l'envoyer a chaque affichage de la liste
    // n'apporterait rien a l'ecran de choix.
    $this->actingAsTenantUser($this->user)
        ->get('http://localhost/settings/agent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('templates.restaurant.title')
            ->has('templates.restaurant.industry')
            ->missing('templates.restaurant.system_prompt'));
});

it('installe un modele metier en un appel', function () {
    $agent = Agent::factory()->create(['name' => 'Assistant', 'is_active' => false]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent/install-template', ['template_key' => 'restaurant'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingForTenant($this->tenant);
    $fresh = $agent->fresh();

    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->name)->toContain('Léon')
        ->and($fresh->system_prompt)->toContain('maître d\'hôtel')
        ->and($fresh->enabled_tools)->toContain('book_appointment')
        ->and($fresh->greeting_message)->not->toBeEmpty();
});

it('cree l\'agent si l\'entreprise n\'en a aucun', function () {
    expect(Agent::query()->count())->toBe(0);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent/install-template', ['template_key' => 'clinic'])
        ->assertRedirect();

    $this->actingForTenant($this->tenant);

    expect(Agent::query()->count())->toBe(1)
        ->and(Agent::query()->first()->is_active)->toBeTrue();
});

it('n\'active qu\'un seul agent apres installation', function () {
    $ancien = Agent::factory()->create(['name' => 'Ancien', 'is_active' => true]);
    Agent::factory()->create(['name' => 'Autre', 'is_active' => false]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent/install-template', ['template_key' => 'ecommerce']);

    $this->actingForTenant($this->tenant);

    // C'est l'agent DEJA actif qui recoit le modele.
    expect($ancien->fresh()->name)->toContain('Eva');
    expect(Agent::query()->where('is_active', true)->count())->toBe(1);
});

it('refuse un modele inconnu', function () {
    Agent::factory()->create();

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent/install-template', ['template_key' => 'garage'])
        ->assertSessionHasErrors('template_key');
});

it('refuse l\'installation a un role sans la permission', function () {
    [$tenant, $operateur] = $this->createTenantWithUser('agent', ['conversations.view', 'agent.view']);
    $this->actingForTenant($tenant);
    Agent::factory()->create();

    $this->actingAsTenantUser($operateur)
        ->post('http://localhost/settings/agent/install-template', ['template_key' => 'restaurant'])
        ->assertStatus(403);
});

it('laisse les reglages de plateforme intacts', function () {
    $agent = Agent::factory()->create([
        'is_active'     => true,
        'temperature'   => 0.9,
        'max_tokens'    => 2048,
        'memory_window' => 20,
    ]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/agent/install-template', ['template_key' => 'travel_agency']);

    $this->actingForTenant($this->tenant);
    $fresh = $agent->fresh();

    // Un modele metier redige un agent ; il n'arbitre pas le cout ni la memoire.
    expect((float) $fresh->temperature)->toBe(0.9)
        ->and($fresh->max_tokens)->toBe(2048)
        ->and($fresh->memory_window)->toBe(20);
});

it('couvre chaque modele de la bibliotheque', function () {
    $library = app(AgentTemplateLibrary::class);

    foreach ($library->keys() as $key) {
        $template = $library->find($key);

        expect($template)->toHaveKeys([
            'title', 'description', 'industry', 'name', 'persona',
            'greeting_message', 'fallback_message', 'system_prompt', 'enabled_tools',
        ]);
        expect($template['enabled_tools'])->toBeArray()->not->toBeEmpty();
    }
});
