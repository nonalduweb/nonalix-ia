<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy extends TenantScopedPolicy
{
    protected function resource(): string
    {
        return 'knowledge';
    }

    /** Relancer l'ingestion consomme des appels d'embeddings facturés. */
    public function reprocess(User $user, Document $document): bool
    {
        return $this->owns($user, $document)
            && $user->hasAnyRole(['owner', 'admin'])
            && $this->allows($user, 'update');
    }
}
