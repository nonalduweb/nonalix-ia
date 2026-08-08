<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Panne du fournisseur vocal.
 *
 * Porte un motif stable, destiné au code appelant qui décide du repli — et non
 * le message brut du fournisseur, qui peut contenir des détails d'appel.
 *
 * `reason` et non `code` : `Exception::$code` existe déjà et n'est pas
 * readonly, le redéclarer est une erreur fatale.
 */
class VoiceProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return ['reason' => $this->reason, 'message' => $this->getMessage()];
    }
}
