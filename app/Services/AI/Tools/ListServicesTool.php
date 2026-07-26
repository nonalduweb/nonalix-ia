<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Contracts\AI\AgentTool;
use App\Data\AI\ToolDefinition;
use App\Models\Conversation;
use App\Models\Service;

/**
 * Consultation du catalogue de prestations.
 *
 * Les prestations figurent déjà dans le prompt système, mais un catalogue
 * volumineux y est tronqué. Cet outil permet une recherche ciblée et garantit
 * que le tarif communiqué vient de la base, pas de la mémoire du modèle.
 */
class ListServicesTool implements AgentTool
{
    public function name(): string
    {
        return 'list_services';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Recherche les prestations proposées par l'entreprise et leurs "
                ."tarifs officiels. À utiliser dès qu'il est question d'un prix, "
                ."d'une prestation précise ou d'une durée. N'annonce jamais un tarif "
                ."sans avoir appelé cet outil.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'Mot-clé de recherche. Laisser vide pour tout lister.',
                    ],
                ],
            ],
        );
    }

    public function execute(array $arguments, Conversation $conversation): string
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        $services = Service::query()
            ->active()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('description', 'ilike', "%{$query}%")
                        ->orWhere('category', 'ilike', "%{$query}%");
                });
            })
            ->limit(25)
            ->get();

        if ($services->isEmpty()) {
            return $query === ''
                ? "Aucune prestation n'est enregistrée. Propose de transférer à un conseiller."
                : "Aucune prestation ne correspond à « {$query} ». Ne propose pas de tarif : "
                  ."indique que tu vas faire vérifier par un conseiller.";
        }

        $lines = $services->map(function (Service $service) {
            $line = "- {$service->name} : {$service->formattedPrice()}";

            if ($service->duration_minutes) {
                $line .= " ({$service->duration_minutes} min)";
            }

            if ($service->description) {
                $line .= " — ".mb_substr($service->description, 0, 200);
            }

            return $line;
        })->implode("\n");

        return "Prestations correspondantes :\n".$lines
            ."\n\nCe sont les seuls tarifs officiels. N'en invente aucun autre.";
    }
}
