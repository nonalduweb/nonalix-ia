<?php

declare(strict_types=1);

namespace App\Enums;

enum SenderType: string
{
    case Contact = 'contact';
    case Ai      = 'ai';
    case Agent   = 'agent';

    /** Message émis par la plateforme (confirmation d'opt-out, erreur…). */
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contact',
            self::Ai      => 'Agent IA',
            self::Agent   => 'Opérateur',
            self::System  => 'Système',
        };
    }

    /** Ce message doit-il être compté dans la mémoire conversationnelle ? */
    public function isPartOfMemory(): bool
    {
        return $this !== self::System;
    }
}
