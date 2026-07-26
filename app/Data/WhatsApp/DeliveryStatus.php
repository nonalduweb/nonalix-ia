<?php

declare(strict_types=1);

namespace App\Data\WhatsApp;

use App\Enums\MessageStatus;
use Carbon\CarbonImmutable;

/** Notification de statut de livraison reçue par webhook. */
final readonly class DeliveryStatus
{
    /** @param array<string, mixed>|null $error */
    public function __construct(
        public string $wamid,
        public MessageStatus $status,
        public CarbonImmutable $timestamp,
        public ?string $recipientId = null,
        public ?array $error = null,
    ) {}

    public function isFailure(): bool
    {
        return $this->status === MessageStatus::Failed;
    }
}
