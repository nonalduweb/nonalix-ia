<?php

declare(strict_types=1);

namespace App\Data\WhatsApp;

use App\Enums\MessageType;
use Carbon\CarbonImmutable;

/** Message entrant, extrait du payload Meta et normalisé. */
final readonly class InboundMessage
{
    /** @param array<string, mixed>|null $media */
    public function __construct(
        public string $wamid,
        public string $from,
        public string $phoneNumberId,
        public MessageType $type,
        public ?string $body,
        public CarbonImmutable $timestamp,
        public ?string $profileName = null,
        public ?array $media = null,
        public ?string $contextWamid = null,
    ) {}

    /**
     * Texte exploitable, quelle que soit la forme du message.
     *
     * Un bouton et une réponse de liste portent un titre qui a valeur de
     * réponse utilisateur : les traiter comme du texte évite que l'agent
     * reçoive un message vide quand le contact a simplement cliqué.
     */
    public function textContent(): ?string
    {
        return $this->body !== null && trim($this->body) !== '' ? $this->body : null;
    }

    public function hasText(): bool
    {
        return $this->textContent() !== null;
    }
}
