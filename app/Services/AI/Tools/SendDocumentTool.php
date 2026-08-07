<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Data\AI\ToolDefinition;

class SendDocumentTool extends BaseN8nTool
{
    public function name(): string
    {
        return 'send_document';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Transmet un document officiel au contact (ex: plaquette de présentation, catalogue produit, tarifs).",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'document_type' => [
                        'type'        => 'string',
                        'description' => 'Type de document demandé.',
                        'enum'        => ['catalogue', 'plaquette', 'tarifs'],
                    ],
                ],
                'required' => ['document_type'],
            ],
        );
    }
}
