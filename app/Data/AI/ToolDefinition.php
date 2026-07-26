<?php

declare(strict_types=1);

namespace App\Data\AI;

/**
 * Déclaration d'un outil exposé au modèle, au format neutre.
 *
 * La description est une partie du prompt : c'est elle qui détermine si le
 * modèle appelle l'outil au bon moment. Elle mérite autant de soin que le
 * prompt système lui-même.
 */
final readonly class ToolDefinition
{
    /** @param array<string, mixed> $parameters  JSON Schema des arguments */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters = [],
    ) {}

    /** Format OpenAI (également accepté par les API compatibles). */
    public function toOpenAiFormat(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $this->name,
                'description' => $this->description,
                'parameters'  => $this->parametersOrEmptyObject(),
            ],
        ];
    }

    /** Format Anthropic : le schéma est à plat, sous `input_schema`. */
    public function toAnthropicFormat(): array
    {
        return [
            'name'         => $this->name,
            'description'  => $this->description,
            'input_schema' => $this->parametersOrEmptyObject(),
        ];
    }

    /** Format Gemini : `functionDeclarations`, sans le champ `type`. */
    public function toGeminiFormat(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'parameters'  => $this->parametersOrEmptyObject(),
        ];
    }

    /**
     * Un outil sans paramètre doit tout de même déclarer un schéma d'objet
     * vide : les trois fournisseurs rejettent une déclaration sans `type`.
     */
    private function parametersOrEmptyObject(): array
    {
        return $this->parameters !== []
            ? $this->parameters
            : ['type' => 'object', 'properties' => (object) []];
    }
}
