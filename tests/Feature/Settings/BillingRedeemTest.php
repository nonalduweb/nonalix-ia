<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\AccessCode;
use App\Models\AccessCodeRedemption;
use App\Models\Plan;
use App\Models\Subscription;

/*
| Consommation d'un code d'accès depuis l'espace client.
|
| Second usage des codes, après l'inscription : renouveler ou surclasser un
| abonnement. Même discipline que TenantRegistrar — verrou, revalidation sous
| transaction, trace de consommation.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $this->plan = Plan::factory()->create(['name' => 'Pro', 'slug' => 'pro']);

    $this->code = AccessCode::create([
        'code'       => 'ABCD-EFGH-JKLM',
        'plan_id'    => $this->plan->id,
        'max_uses'   => 5,
        'used_count' => 0,
        'trial_days' => 30,
    ]);
});

it('affiche la page de facturation', function () {
    $this->actingAsTenantUser($this->user)
        ->get('http://localhost/settings/billing')
        ->assertOk();
});

it('applique un code valide et bascule l\'entreprise en actif', function () {
    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $tenant = $this->tenant->fresh();

    expect($tenant->plan_id)->toBe($this->plan->id)
        ->and($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->trial_ends_at)->toBeNull();

    $this->actingForTenant($tenant);

    expect(Subscription::query()->where('external_reference', 'code_ABCD-EFGH-JKLM')->exists())->toBeTrue();
});

it('accepte le code quelle que soit sa casse ou sa ponctuation', function () {
    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'abcdefghjklm'])
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
});

it('trace la consommation du code', function () {
    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM']);

    // La table existe pour rattacher un client à l'opération commerciale qui
    // l'a amené : ne pas l'alimenter reviendrait à perdre cette trace.
    expect(AccessCodeRedemption::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('access_code_id', $this->code->id)
        ->exists())->toBeTrue();

    expect($this->code->fresh()->used_count)->toBe(1);
});

it('refuse le même code une seconde fois pour la même entreprise', function () {
    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasNoErrors();

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasErrors('code');

    expect($this->code->fresh()->used_count)->toBe(1);
});

it('accepte un second code différent, pour un renouvellement', function () {
    $autre = AccessCode::create([
        'code'       => 'NPQR-STUV-WXYZ',
        'plan_id'    => $this->plan->id,
        'max_uses'   => 5,
        'trial_days' => 30,
    ]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasNoErrors();

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'NPQR-STUV-WXYZ'])
        ->assertSessionHasNoErrors();

    expect(AccessCodeRedemption::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
    expect($autre->fresh()->used_count)->toBe(1);
});

it('refuse un code inconnu', function () {
    $planInitial = $this->tenant->plan_id;

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ZZZZ-ZZZZ-ZZZZ'])
        ->assertSessionHasErrors('code');

    // Le plan ne bouge pas : la factory crée déjà l'entreprise en `Active`,
    // c'est donc le rattachement à l'offre qui fait foi, pas le statut.
    expect($this->tenant->fresh()->plan_id)->toBe($planInitial);
});

it('refuse un code révoqué', function () {
    $this->code->update(['revoked_at' => now()]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasErrors('code');
});

it('refuse un code expiré', function () {
    $this->code->update(['expires_at' => now()->subDay()]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasErrors('code');
});

it('refuse un code épuisé', function () {
    $this->code->update(['max_uses' => 1, 'used_count' => 1]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertSessionHasErrors('code');
});

it('n\'altère rien lorsque le code est refusé', function () {
    $this->code->update(['revoked_at' => now()]);

    $this->actingAsTenantUser($this->user)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM']);

    $this->actingForTenant($this->tenant);

    expect(Subscription::query()->count())->toBe(0)
        ->and(AccessCodeRedemption::query()->count())->toBe(0)
        ->and($this->code->fresh()->used_count)->toBe(0);
});

it('refuse la consommation à un rôle sans la permission settings.update', function () {
    // Changer de plan engage l'entreprise : hors de portée d'un opérateur.
    [$tenant, $operateur] = $this->createTenantWithUser('agent', [
        'conversations.view', 'settings.view',
    ]);
    $this->actingForTenant($tenant);

    $this->actingAsTenantUser($operateur)
        ->post('http://localhost/settings/billing/redeem', ['code' => 'ABCD-EFGH-JKLM'])
        ->assertStatus(403);
});
