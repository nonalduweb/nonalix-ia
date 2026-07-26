<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    case New          = 'new';
    case Contacted    = 'contacted';
    case Qualified    = 'qualified';
    case Unqualified  = 'unqualified';
    case Won          = 'won';
    case Lost         = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New         => 'Nouveau',
            self::Contacted   => 'Contacté',
            self::Qualified   => 'Qualifié',
            self::Unqualified => 'Non qualifié',
            self::Won         => 'Gagné',
            self::Lost        => 'Perdu',
        };
    }

    /** Le prospect est-il encore dans le pipeline commercial ? */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost, self::Unqualified], true);
    }
}
