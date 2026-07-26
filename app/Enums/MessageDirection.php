<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageDirection: string
{
    case Inbound  = 'in';
    case Outbound = 'out';

    public function label(): string
    {
        return $this === self::Inbound ? 'Entrant' : 'Sortant';
    }
}
