<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Trial     = 'trial';
    case Active    = 'active';
    case PastDue   = 'past_due';
    case Suspended = 'suspended';
    case Closed    = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Trial     => 'Période d\'essai',
            self::Active    => 'Actif',
            self::PastDue   => 'Paiement en retard',
            self::Suspended => 'Suspendu',
            self::Closed    => 'Fermé',
        };
    }

    /**
     * Le tenant peut-il utiliser la plateforme ?
     *
     * `past_due` reste opérationnel : couper le service d'un client qui a
     * simplement un impayé récent lui ferait perdre des conversations en cours,
     * ce qui est disproportionné. La suspension est une décision explicite.
     */
    public function isOperational(): bool
    {
        return match ($this) {
            self::Trial, self::Active, self::PastDue => true,
            self::Suspended, self::Closed            => false,
        };
    }
}
