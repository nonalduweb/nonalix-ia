<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Data\AI\ToolDefinition;
use App\Models\Conversation;
use App\Models\Lead;

class GenerateQuoteTool extends BaseN8nTool
{
    public function name(): string
    {
        return 'generate_quote';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Génère et envoie une estimation ou un devis personnalisé au contact. À appeler quand le contact demande un chiffrage ou une proposition de prix.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'client_name' => [
                        'type'        => 'string',
                        'description' => 'Nom du client ou de son entreprise.',
                    ],
                    'items' => [
                        'type'        => 'string',
                        'description' => 'Description détaillée des services ou produits demandés.',
                    ],
                    'estimated_amount' => [
                        'type'        => 'string',
                        'description' => 'Montant estimé (optionnel).',
                    ],
                ],
                'required' => ['client_name', 'items'],
            ],
        );
    }

    public function execute(array $arguments, Conversation $conversation): string
    {
        $result = $this->call($arguments, $conversation);

        // Voir BookAppointmentTool : on n'inscrit le marqueur que sur une
        // confirmation effective de n8n.
        if ($result['ok']) {
            $lead = Lead::query()
                ->where('contact_id', $conversation->contact_id)
                ->open()
                ->first();

            if ($lead) {
                $qual = $lead->qualification ?? [];
                $qual['quote_sent'] = true;
                $qual['quote_amount'] = $arguments['estimated_amount'] ?? null;
                $lead->update(['qualification' => $qual]);
            }
        }

        return $result['message'];
    }
}
