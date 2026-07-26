<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use RuntimeException;

/**
 * Restaure le contexte de tenant dans un worker.
 *
 * Un job ne partage aucun état avec la requête qui l'a créé : le tenant doit
 * être sérialisé dans la charge utile du job, puis réinjecté explicitement.
 * Sans cela, la première requête Eloquent du worker lèverait une exception —
 * ce qui est le comportement voulu du TenantScope, mais pas ici.
 *
 * Le nettoyage en fin d'exécution est indispensable : les workers sont des
 * processus longs qui enchaînent des jobs de tenants différents.
 */
trait RunsInTenantContext
{
    /**
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    protected function withTenant(string $tenantId, callable $callback): mixed
    {
        $context = app(TenantContext::class);

        $tenant = $context->runWithout(
            static fn () => Tenant::query()->find($tenantId),
        );

        if ($tenant === null) {
            throw new RuntimeException("Tenant introuvable : {$tenantId}.");
        }

        return $context->runAs($tenant, static fn () => $callback($tenant));
    }
}
