<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatchException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Cloisonne un modèle par tenant.
 *
 * Trois garanties, dans cet ordre :
 *   1. lecture   — le scope global filtre toutes les requêtes ;
 *   2. écriture  — `tenant_id` est renseigné automatiquement à la création ;
 *   3. intégrité — toute tentative d'écriture sur une ligne appartenant à un
 *                  autre tenant lève une TenantMismatchException.
 *
 * Le point 3 est ce qui protège des cas où le scope a été désactivé plus haut
 * dans la pile : la vérification se fait au plus près de la base.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                static::assertBelongsToCurrentTenant($model);

                return;
            }

            $context = app(TenantContext::class);

            if (! $context->has()) {
                throw new RuntimeException(sprintf(
                    'Création de [%s] impossible : aucun tenant en contexte et '
                    .'aucun tenant_id fourni explicitement.',
                    $model::class,
                ));
            }

            $model->setAttribute('tenant_id', $context->id());
        });

        // `updating` et `deleting` : on refuse de toucher une ligne qui n'est
        // pas la nôtre, même si elle a réussi à arriver jusqu'ici.
        static::updating(static fn (Model $model) => static::assertBelongsToCurrentTenant($model));
        static::deleting(static fn (Model $model) => static::assertBelongsToCurrentTenant($model));
    }

    protected static function assertBelongsToCurrentTenant(Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->scopeIsDisabled() || ! $context->has()) {
            return;
        }

        $modelTenantId = $model->getAttribute('tenant_id');

        if ($modelTenantId !== null && $modelTenantId !== $context->id()) {
            throw new TenantMismatchException(
                model: $model::class,
                expectedTenantId: $context->id(),
                actualTenantId: (string) $modelTenantId,
            );
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Requête sans cloisonnement.
     *
     * Réservé à l'administration et aux traitements transverses. Le nom est
     * volontairement explicite : il doit sauter aux yeux en revue de code.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }

    /** Requête cloisonnée sur un tenant précis, hors contexte courant. */
    public static function forTenant(Tenant|string $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return static::withoutTenantScope()->where(
            (new static)->qualifyColumn('tenant_id'),
            $tenantId,
        );
    }
}
