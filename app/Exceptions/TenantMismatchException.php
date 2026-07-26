<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée lorsqu'une ressource d'un tenant est atteinte depuis le contexte d'un
 * autre tenant.
 *
 * C'est une anomalie de sécurité, jamais une erreur utilisateur ordinaire :
 * elle est systématiquement rapportée, et l'utilisateur ne reçoit qu'un 404
 * afin de ne rien révéler sur l'existence de la ressource visée.
 */
class TenantMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $model,
        public readonly ?string $expectedTenantId = null,
        public readonly ?string $actualTenantId = null,
    ) {
        parent::__construct(sprintf(
            'Accès inter-tenant refusé sur [%s] (contexte : %s, ressource : %s).',
            $model,
            $expectedTenantId ?? 'aucun',
            $actualTenantId ?? 'aucun',
        ));
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'model'              => $this->model,
            'expected_tenant_id' => $this->expectedTenantId,
            'actual_tenant_id'   => $this->actualTenantId,
            'security_event'     => true,
        ];
    }
}
