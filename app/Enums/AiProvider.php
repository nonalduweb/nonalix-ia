<?php

declare(strict_types=1);

namespace App\Enums;

enum AiProvider: string
{
    case OpenAI    = 'openai';
    case Anthropic = 'anthropic';
    case Gemini    = 'gemini';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI    => 'OpenAI',
            self::Anthropic => 'Anthropic (Claude)',
            self::Gemini    => 'Google Gemini',
        };
    }

    /**
     * Fournisseurs capables de produire des embeddings.
     *
     * Anthropic n'expose pas d'API d'embeddings : un tenant peut donc utiliser
     * Claude pour la conversation tout en passant par OpenAI pour l'indexation.
     */
    public function supportsEmbeddings(): bool
    {
        return match ($this) {
            self::OpenAI, self::Gemini => true,
            self::Anthropic            => false,
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
