<?php

declare(strict_types=1);

namespace App\Data\AI;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    public function total(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /** Cumule l'usage de plusieurs tours (boucle d'appels d'outils). */
    public function plus(self $other): self
    {
        return new self(
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
        );
    }

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int} */
    public function toArray(): array
    {
        return [
            'input_tokens'  => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens'  => $this->total(),
        ];
    }
}
