<?php

declare(strict_types=1);

namespace App\Data\AI;

/**
 * Un message de la conversation, dans un format neutre.
 *
 * C'est ce format que manipule tout le code métier. Chaque adaptateur de
 * fournisseur le traduit vers son propre schéma — et lui seul connaît les
 * particularités de son API.
 */
final readonly class ChatMessage
{
    /**
     * @param  'system'|'user'|'assistant'|'tool'  $role
     * @param  array<int, ToolCall>                $toolCalls   réponses de l'assistant demandant un outil
     * @param  string|null                         $toolCallId  identifiant auquel ce message-outil répond
     */
    public function __construct(
        public string $role,
        public ?string $content = null,
        public array $toolCalls = [],
        public ?string $toolCallId = null,
        public ?string $name = null,
    ) {}

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(?string $content, array $toolCalls = []): self
    {
        return new self('assistant', $content, $toolCalls);
    }

    /** Résultat de l'exécution d'un outil, réinjecté dans la conversation. */
    public static function tool(string $toolCallId, string $name, string $content): self
    {
        return new self('tool', $content, toolCallId: $toolCallId, name: $name);
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'role'         => $this->role,
            'content'      => $this->content,
            'tool_calls'   => array_map(static fn (ToolCall $c) => $c->toArray(), $this->toolCalls),
            'tool_call_id' => $this->toolCallId,
            'name'         => $this->name,
        ], static fn ($v) => $v !== null && $v !== []);
    }
}
