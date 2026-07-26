<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy extends TenantScopedPolicy
{
    protected function resource(): string
    {
        return 'conversations';
    }

    /** Attribuer une conversation à un opérateur. */
    public function assign(User $user, Conversation $conversation): bool
    {
        return $this->owns($user, $conversation) && $this->allows($user, 'assign');
    }

    /**
     * Répondre dans une conversation.
     *
     * Un opérateur ne peut écrire que dans une conversation qui lui est
     * attribuée ou qui n'appartient à personne : sans cette règle, deux
     * personnes répondent au même contact en même temps.
     */
    public function reply(User $user, Conversation $conversation): bool
    {
        if (! $this->owns($user, $conversation) || ! $this->allows($user, 'reply')) {
            return false;
        }

        if ($conversation->assigned_user_id === null || $conversation->assigned_user_id === $user->id) {
            return true;
        }

        // Les rôles d'encadrement peuvent intervenir partout.
        return $user->hasAnyRole(['owner', 'admin']);
    }

    /** Couper ou réactiver l'agent IA sur une conversation. */
    public function toggleAi(User $user, Conversation $conversation): bool
    {
        return $this->owns($user, $conversation) && $this->allows($user, 'toggle-ai');
    }

    public function close(User $user, Conversation $conversation): bool
    {
        return $this->owns($user, $conversation) && $this->allows($user, 'close');
    }
}
