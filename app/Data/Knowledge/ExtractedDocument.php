<?php

declare(strict_types=1);

namespace App\Data\Knowledge;

final readonly class ExtractedDocument
{
    /** @param array<string, mixed> $metadata  pages, titre, auteur, URL source… */
    public function __construct(
        public string $text,
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }

    public function length(): int
    {
        return mb_strlen($this->text);
    }
}
