<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

/**
 * Ajoute `where tenant_id = ?` à toute requête sur un modèle cloisonné.
 *
 * Comportement en l'absence de tenant : on LÈVE une exception plutôt que de
 * laisser passer une requête non filtrée. Un scope silencieusement inopérant
 * est le scénario exact qui provoque une fuite inter-tenant en production ;
 * une exception, elle, se voit immédiatement en développement et en test.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->scopeIsDisabled()) {
            return;
        }

        if (! $context->has()) {
            throw new RuntimeException(sprintf(
                'Requête sur [%s] hors de tout contexte de tenant. Définir le '
                .'tenant courant, ou encadrer l\'appel par TenantContext::runWithout() '
                .'si l\'accès transverse est délibéré.',
                $model::class,
            ));
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $context->id(),
        );
    }
}
