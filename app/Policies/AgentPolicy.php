<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * La configuration de l'agent détermine ce que l'entreprise dit à ses clients :
 * modifier le prompt est un acte d'encadrement, pas une action d'opérateur.
 */
class AgentPolicy extends TenantPolicy
{
    protected function resource(): string
    {
        return 'agent';
    }

    public function update(User $user, $model): bool
    {
        return $this->owns($user, $model)
            && $user->hasAnyRole(['owner', 'admin'])
            && $this->allows($user, 'update');
    }
}
