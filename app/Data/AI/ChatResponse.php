<?php

declare(strict_types=1);

namespace App\Data\AI;

use App\Enums\AiProvider;

/**
 * Réponse normalisée d'un fournisseur de chat.
 */
final readonly class ChatResponse
{
    /** @param array<int, ToolCall> $toolCalls */
    public function __construct(
        public ?string $content,
        public array $toolCalls,
        public TokenUsage $usage,
        public string $model,
        public AiProvider $provider,
        public ?string $finishReason = null,
        public int $latencyMs = 0,
        /** Coût estimé en micro-centimes d'euro. */
        public int $costMicros = 0,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Le modèle a-t-il produit un texte exploitable ?
     *
     * Un tour purement outillé renvoie `content = null` : c'est normal et ne
     * doit pas être confondu avec un échec.
     */
    public function hasContent(): bool
    {
        return $this->content !== null && trim($this->content) !== '';
    }

    /** Message assistant à réinjecter dans l'historique du tour suivant. */
    public function toAssistantMessage(): ChatMessage
    {
        return ChatMessage::assistant($this->content, $this->toolCalls);
    }

    /**
     * Métadonnées persistées dans `messages.ai_meta`.
     *
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return [
            'provider'      => $this->provider->value,
            'model'         => $this->model,
            'finish_reason' => $this->finishReason,
            'latency_ms'    => $this->latencyMs,
            'cost_micros'   => $this->costMicros,
            'usage'         => $this->usage->toArray(),
            'tool_calls'    => array_map(static fn (ToolCall $c) => $c->name, $this->toolCalls),
        ];
    }
}
