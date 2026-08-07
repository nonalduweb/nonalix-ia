<?php

declare(strict_types=1);

use App\Models\AccessCode;
use App\Models\Agent;
use App\Models\Plan;
use App\Services\Tenancy\TenantRegistrar;

/*
| Ce qu'une entreprise trouve en arrivant.
|
| Elle n'avait aucun agent : la page de configuration en fabriquait un a la
| premiere visite. Ce filet a disparu avec le multi-agents, et une entreprise
| pouvait installer le widget avant d'avoir cree le moindre agent — les
| visiteurs ecrivaient alors dans un chat qui ne repondait jamais.
*/

beforeEach(function () {
    // L'inscription provisionne les roles du tenant : sans le catalogue de
    // permissions en base, RoleProvisioner echoue avant meme l'agent.
    $this->seed(Database\Seeders\PermissionSeeder::class);

    $this->plan = Plan::factory()->create();
    $this->code = AccessCode::create([
        'code'       => 'ABCD-EFGH-JKLM',
        'plan_id'    => $this->plan->id,
        'max_uses'   => 5,
        'trial_days' => 14,
    ]);
});

function registerTenant(): array
{
    return app(TenantRegistrar::class)->register('ABCD-EFGH-JKLM', [
        'company'  => 'Garage Martin',
        'name'     => 'Paul Martin',
        'email'    => 'paul@garage-martin.test',
        'password' => 'motdepasse-solide',
    ], '127.0.0.1');
}

it('cree un agent des l\'inscription', function () {
    ['tenant' => $tenant] = registerTenant();

    $agents = Agent::forTenant($tenant)->get();

    expect($agents)->toHaveCount(1)
        ->and($agents->first()->name)->toBe('Assistant')
        ->and($agents->first())->toBelongToTenant($tenant->id);
});

it('laisse cet agent inactif', function () {
    ['tenant' => $tenant] = registerTenant();

    // Rien ne doit parler aux clients de l'entreprise avant qu'elle n'ait relu
    // le prompt et active l'agent elle-meme.
    expect(Agent::forTenant($tenant)->first()->is_active)->toBeFalse();

    // `activeAgent()` passe par le scope global : il lui faut un contexte.
    $this->actingForTenant($tenant);
    expect($tenant->activeAgent())->toBeNull();
});

it('le dote de valeurs exploitables', function () {
    ['tenant' => $tenant] = registerTenant();

    $agent = Agent::forTenant($tenant)->first();

    expect($agent->model)->not->toBeEmpty()
        ->and($agent->memory_window)->toBeGreaterThan(0)
        ->and($agent->rag_top_k)->toBeGreaterThan(0)
        ->and($agent->handover_keywords)->toContain('humain')
        ->and($agent->enabled_tools)->toContain('request_human_handover');
});

it('n\'attribue l\'agent qu\'a l\'entreprise qui vient de naitre', function () {
    ['tenant' => $premier] = registerTenant();

    $this->code->refresh();
    $second = app(TenantRegistrar::class)->register('ABCD-EFGH-JKLM', [
        'company'  => 'Boulangerie Dupont',
        'name'     => 'Marie Dupont',
        'email'    => 'marie@boulangerie-dupont.test',
        'password' => 'motdepasse-solide',
    ], '127.0.0.1');

    expect(Agent::forTenant($premier)->count())->toBe(1)
        ->and(Agent::forTenant($second['tenant'])->count())->toBe(1);
});
