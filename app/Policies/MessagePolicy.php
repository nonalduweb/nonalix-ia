<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Les messages sont un journal d'échanges : ils se lisent et se créent, mais
 * ne se modifient ni ne se suppriment. Réécrire l'historique d'une
 * conversation client n'a aucun cas d'usage légitime.
 */
class MessagePolicy extends TenantScopedPolicy
{
    protected function resource(): string
    {
        return 'conversations';
    }

    public function update(User $user, $model): bool
    {
        return false;
    }

    public function delete(User $user, $model): bool
    {
        return false;
    }

    public function view(User $user, $model): bool
    {
        return $this->owns($user, $model) && $this->allows($user, 'view');
    }

    /** L'autorisation d'écrire est portée par la conversation, pas le message. */
    public function create(User $user): bool
    {
        return $this->allows($user, 'reply');
    }

    public function retry(User $user, Message $message): bool
    {
        return $this->owns($user, $message) && $this->allows($user, 'reply');
    }
}
