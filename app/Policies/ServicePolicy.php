<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Les tarifs saisis ici sont ceux que l'agent IA annonce aux clients :
 * leur modification est réservée à l'encadrement.
 */
class ServicePolicy extends TenantPolicy
{
    protected function resource(): string
    {
        return 'settings';
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin']) && $this->allows($user, 'update');
    }

    public function update(User $user, $model): bool
    {
        return $this->owns($user, $model)
            && $user->hasAnyRole(['owner', 'admin'])
            && $this->allows($user, 'update');
    }

    public function delete(User $user, $model): bool
    {
        return $this->update($user, $model);
    }
}
