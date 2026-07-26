<?php

declare(strict_types=1);

namespace App\Enums;

enum OptInStatus: string
{
    /** Le contact a écrit en premier : consentement implicite au service. */
    case Unknown = 'unknown';

    case OptedIn  = 'opted_in';
    case OptedOut = 'opted_out';

    public function label(): string
    {
        return match ($this) {
            self::Unknown  => 'Non renseigné',
            self::OptedIn  => 'Abonné',
            self::OptedOut => 'Désabonné',
        };
    }

    /**
     * Peut-on envoyer un message à ce contact ?
     *
     * `unknown` est autorisé pour les réponses de service (le contact a écrit
     * en premier), mais l'envoi proactif par template exige un opt-in explicite.
     */
    public function allowsServiceMessages(): bool
    {
        return $this !== self::OptedOut;
    }

    public function allowsProactiveMessages(): bool
    {
        return $this === self::OptedIn;
    }
}
