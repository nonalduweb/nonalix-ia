<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEventStatus: string
{
    case Received  = 'received';
    case Processed = 'processed';
    case Failed    = 'failed';

    /** Événement reconnu mais sans traitement prévu (ex. `message_echoes`). */
    case Ignored = 'ignored';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Processed, self::Ignored], true);
    }
}
