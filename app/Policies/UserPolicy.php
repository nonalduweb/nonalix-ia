<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Gestion des membres de l'équipe.
 *
 * Deux garde-fous non négociables :
 *   - nul ne peut supprimer ni désactiver son propre compte depuis cet écran
 *     (on ne se verrouille pas hors de son entreprise par accident) ;
 *   - seul un `owner` peut agir sur un autre `owner`.
 */
class UserPolicy extends TenantPolicy
{
    protected function resource(): string
    {
        return 'users';
    }

    public function view(User $user, $model): bool
    {
        return $this->owns($user, $model) && $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin']) && $this->allows($user, 'create');
    }

    public function update(User $user, $model): bool
    {
        if (! $this->owns($user, $model) || ! $user->hasAnyRole(['owner', 'admin'])) {
            return false;
        }

        if ($model->hasRole('owner') && ! $user->hasRole('owner')) {
            return false;
        }

        return $this->allows($user, 'update');
    }

    public function delete(User $user, $model): bool
    {
        return $model->id !== $user->id && $this->update($user, $model);
    }
}
