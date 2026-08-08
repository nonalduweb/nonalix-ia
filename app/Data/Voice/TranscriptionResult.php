<?php

declare(strict_types=1);

namespace App\Data\Voice;

/** Résultat d'une transcription, dans un format neutre du fournisseur. */
final readonly class TranscriptionResult
{
    public function __construct(
        public string $text,
        public ?string $language = null,
        public ?float $confidence = null,
        public ?float $seconds = null,
        public ?int $latencyMs = null,
    ) {}

    /** Durée lisible, telle qu'affichée dans le fil : « 00:14 ». */
    public function duration(): ?string
    {
        if ($this->seconds === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv((int) $this->seconds, 60), (int) $this->seconds % 60);
    }
}
