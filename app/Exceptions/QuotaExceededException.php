<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Quota du plan atteint pour une métrique donnée.
 *
 * Volontairement porteuse du détail (métrique, limite, usage) : le client doit
 * pouvoir comprendre ce qui bloque et agir, pas seulement voir « erreur 429 ».
 */
class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $metric,
        public readonly int $limit,
        public readonly int $current,
        public readonly ?string $tenantId = null,
    ) {
        parent::__construct(sprintf(
            'Quota « %s » atteint : %d/%d pour la période en cours.',
            $metric,
            $current,
            $limit,
        ));
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'metric'    => $this->metric,
            'limit'     => $this->limit,
            'current'   => $this->current,
        ];
    }
}
