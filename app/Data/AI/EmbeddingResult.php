<?php

declare(strict_types=1);

namespace App\Data\AI;

use App\Enums\AiProvider;

final readonly class EmbeddingResult
{
    /** @param array<int, array<int, float>> $vectors  un vecteur par texte, dans l'ordre d'entrée */
    public function __construct(
        public array $vectors,
        public TokenUsage $usage,
        public string $model,
        public AiProvider $provider,
        public int $dimensions,
        public int $latencyMs = 0,
        public int $costMicros = 0,
    ) {}

    public function first(): ?array
    {
        return $this->vectors[0] ?? null;
    }

    public function count(): int
    {
        return count($this->vectors);
    }
}
