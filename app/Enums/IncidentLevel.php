<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentLevel: string
{
    case Info     = 'info';
    case Warning  = 'warning';
    case Error    = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info     => 'Information',
            self::Warning  => 'Avertissement',
            self::Error    => 'Erreur',
            self::Critical => 'Critique',
        };
    }

    /** Doit-on notifier l'équipe NONALIX immédiatement ? */
    public function requiresImmediateAlert(): bool
    {
        return $this === self::Critical;
    }
}
