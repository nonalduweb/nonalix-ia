<?php

declare(strict_types=1);

namespace App\Data\AI;

/**
 * Requête de génération, indépendante du fournisseur.
 *
 * Le `system` est isolé du tableau de messages : OpenAI l'attend comme un
 * message de rôle `system`, Anthropic comme un paramètre `system` de premier
 * niveau, Gemini comme `systemInstruction`. Le porter séparément évite que
 * chaque adaptateur ait à le retrouver et l'extraire.
 */
final readonly class ChatRequest
{
    /**
     * @param  array<int, ChatMessage>     $messages
     * @param  array<int, ToolDefinition>  $tools
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?string $system = null,
        public array $tools = [],
        public float $temperature = 0.3,
        public int $maxTokens = 1024,
        public ?string $toolChoice = null,
    ) {}

    public function withMessages(array $messages): self
    {
        return new self(
            $this->model, $messages, $this->system, $this->tools,
            $this->temperature, $this->maxTokens, $this->toolChoice,
        );
    }

    /** Ajoute des messages à la suite (résultats d'outils, tour suivant). */
    public function appending(ChatMessage ...$messages): self
    {
        return $this->withMessages([...$this->messages, ...$messages]);
    }

    public function hasTools(): bool
    {
        return $this->tools !== [];
    }
}
