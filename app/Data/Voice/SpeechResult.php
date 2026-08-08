<?php

declare(strict_types=1);

namespace App\Data\Voice;

/** Audio synthétisé, prêt à être stocké ou transmis. */
final readonly class SpeechResult
{
    public function __construct(
        public string $audio,
        public string $mimeType,
        public string $extension,
        public int $characters,
        public string $model,
        public ?int $latencyMs = null,
    ) {}
}
