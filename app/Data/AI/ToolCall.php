<?php

declare(strict_types=1);

namespace App\Data\AI;

/** Demande d'appel d'outil émise par le modèle. */
final readonly class ToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {}

    /**
     * Construit l'objet depuis des arguments sérialisés en JSON.
     *
     * Les modèles produisent parfois du JSON invalide (troncature, guillemets
     * manquants). On dégrade alors vers un tableau vide plutôt que de faire
     * échouer tout le tour de conversation : l'outil recevra des arguments
     * incomplets et pourra répondre qu'il lui manque une information.
     */
    public static function fromJson(string $id, string $name, ?string $json): self
    {
        $arguments = [];

        if ($json !== null && $json !== '') {
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                $arguments = $decoded;
            }
        }

        return new self($id, $name, $arguments);
    }

    public function argument(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
