<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active   = 'active';
    case Invited  = 'invited';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active   => 'Actif',
            self::Invited  => 'Invitation envoyée',
            self::Disabled => 'Désactivé',
        };
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
