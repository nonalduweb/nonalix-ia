<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy extends TenantScopedPolicy
{
    protected function resource(): string
    {
        return 'contacts';
    }

    /**
     * Désinscrire un contact.
     *
     * Volontairement permissif : la désinscription protège le contact, jamais
     * l'entreprise. Toute personne pouvant voir un contact doit pouvoir
     * honorer sa demande immédiatement.
     */
    public function optOut(User $user, Contact $contact): bool
    {
        return $this->owns($user, $contact) && $this->allows($user, 'view');
    }
}
