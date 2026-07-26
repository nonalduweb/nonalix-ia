<?php

declare(strict_types=1);

namespace App\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Tentative d'envoi d'un message libre hors de la fenêtre de service Meta.
 *
 * Ce n'est pas une erreur technique mais une règle de la plateforme WhatsApp :
 * passé 24 h sans message du contact, seul un template approuvé peut sortir.
 * On bloque côté Nonalix plutôt que de laisser Meta refuser, afin de préserver
 * la note de qualité du numéro du client.
 */
class ServiceWindowClosedException extends RuntimeException
{
    public function __construct(
        public readonly string $conversationId,
        public readonly ?CarbonInterface $expiredAt = null,
    ) {
        parent::__construct(
            'La fenêtre de 24 h est fermée : seul un modèle de message approuvé '
            .'peut être envoyé à ce contact.'
        );
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'expired_at'      => $this->expiredAt?->toIso8601String(),
        ];
    }
}
