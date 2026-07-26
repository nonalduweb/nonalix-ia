<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppAccount;

/**
 * Le compte WhatsApp porte les secrets Meta du client : sa gestion est
 * réservée aux rôles `owner` et `admin`. Un opérateur qui pourrait modifier
 * le jeton d'accès pourrait détourner le numéro de l'entreprise.
 */
class WhatsAppAccountPolicy extends TenantPolicy
{
    protected function resource(): string
    {
        return 'whatsapp';
    }

    public function update(User $user, $model): bool
    {
        return $this->owns($user, $model)
            && $user->hasAnyRole(['owner', 'admin'])
            && $this->allows($user, 'update');
    }

    /** Tester la connexion appelle réellement l'API Meta. */
    public function test(User $user, WhatsAppAccount $account): bool
    {
        return $this->update($user, $account);
    }
}
