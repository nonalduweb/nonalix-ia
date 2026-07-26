<?php

declare(strict_types=1);

namespace App\Data\Knowledge;

final readonly class Chunk
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $content,
        public int $position,
        public int $tokens = 0,
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }
}
