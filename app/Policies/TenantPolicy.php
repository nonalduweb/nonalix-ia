<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Socle commun des policies cloisonnées.
 *
 * Deux vérifications, toujours dans cet ordre :
 *   1. la ressource appartient-elle au tenant de l'utilisateur ?
 *   2. l'utilisateur a-t-il la permission métier ?
 *
 * L'ordre est délibéré : l'appartenance prime sur la permission. Un `owner`
 * d'une entreprise n'a aucun droit sur les données d'une autre, quel que soit
 * son rôle. C'est la deuxième barrière après le scope global — si l'une des
 * deux cède, l'autre tient.
 */
abstract class TenantPolicy
{
    /** Préfixe des permissions, ex. `conversations`. */
    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $this->allows($user, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $this->allows($user, 'delete');
    }

    /**
     * La ressource appartient-elle au tenant de l'utilisateur ?
     *
     * Un super-admin échoue volontairement ce test : son `tenant_id` est nul.
     * Consulter les données d'un client passe par une impersonation tracée,
     * pas par un contournement de policy.
     */
    protected function owns(User $user, Model $model): bool
    {
        $tenantId = $model->getAttribute('tenant_id');

        return $tenantId !== null
            && $user->tenant_id !== null
            && $tenantId === $user->tenant_id;
    }

    protected function allows(User $user, string $action): bool
    {
        return $user->can($this->resource().'.'.$action);
    }
}
