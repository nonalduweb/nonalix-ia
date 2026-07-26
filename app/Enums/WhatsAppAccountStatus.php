<?php

declare(strict_types=1);

namespace App\Enums;

enum WhatsAppAccountStatus: string
{
    /** Identifiants saisis mais jamais validés auprès de Meta. */
    case Pending = 'pending';

    case Connected = 'connected';

    /** Dernier appel Meta en échec (jeton expiré, numéro banni…). */
    case Error = 'error';

    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Pending      => 'En attente de validation',
            self::Connected    => 'Connecté',
            self::Error        => 'Erreur',
            self::Disconnected => 'Déconnecté',
        };
    }

    public function canSend(): bool
    {
        return $this === self::Connected;
    }
}
