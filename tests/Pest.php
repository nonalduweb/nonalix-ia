<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|------------------------------------------------------------------------------
| Configuration Pest
|------------------------------------------------------------------------------
| RefreshDatabase sur toute la suite : chaque test part d'une base propre, dans
| une transaction annulée à la fin. Sur une plateforme multi-tenant, un résidu
| de test masquerait exactement le type de fuite qu'on cherche à détecter.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|------------------------------------------------------------------------------
| Attentes personnalisées
|------------------------------------------------------------------------------
*/

expect()->extend('toBelongToTenant', function (string $tenantId) {
    expect($this->value->tenant_id)->toBe($tenantId);

    return $this;
});

/**
 * Vérifie qu'une requête n'expose aucune donnée d'un autre tenant.
 * Utilisée par les tests d'isolation, qui sont bloquants pour la Phase 1.
 */
expect()->extend('toBeIsolatedFrom', function (string $foreignId) {
    $ids = collect($this->value)->pluck('id')->all();

    expect($ids)->not->toContain($foreignId);

    return $this;
});
