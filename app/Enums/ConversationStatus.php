<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationStatus: string
{
    /** Conversation active, l'IA ou un opérateur répond. */
    case Open = 'open';

    /** En attente d'un humain : l'IA a passé la main, personne n'a encore repris. */
    case Pending = 'pending';

    /** Mise en veille volontaire par l'opérateur. */
    case Snoozed = 'snoozed';

    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open    => 'Ouverte',
            self::Pending => 'En attente',
            self::Snoozed => 'En veille',
            self::Closed  => 'Fermée',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Closed;
    }
}
