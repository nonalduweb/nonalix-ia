<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Draft     = 'draft';
    case Queued    = 'queued';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Read      = 'read';
    case Failed    = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Brouillon',
            self::Queued    => 'En file',
            self::Sent      => 'Envoyé',
            self::Delivered => 'Distribué',
            self::Read      => 'Lu',
            self::Failed    => 'Échec',
        };
    }

    /**
     * Rang de progression du statut.
     *
     * Meta ne garantit pas l'ordre d'arrivée des webhooks de statut : un
     * `delivered` peut parvenir après un `read`. On ne recule donc jamais.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Draft     => -1,
            self::Queued    => 0,
            self::Sent      => 1,
            self::Delivered => 2,
            self::Read      => 3,
            self::Failed    => 4,   // terminal : jamais écrasé
        };
    }

    /** Le passage de ce statut vers `$next` est-il autorisé ? */
    public function canTransitionTo(self $next): bool
    {
        if ($this === self::Failed) {
            return false;
        }

        return $next->rank() > $this->rank();
    }
}
