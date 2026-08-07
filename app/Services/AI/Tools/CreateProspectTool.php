<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Data\AI\ToolDefinition;

class CreateProspectTool extends BaseN8nTool
{
    public function name(): string
    {
        return 'create_prospect';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Enregistre un nouveau prospect dans le CRM. À appeler dès que le contact exprime un besoin ou un intérêt pour vos services/produits et que vous avez son nom et son besoin.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'name' => [
                        'type'        => 'string',
                        'description' => 'Nom et/ou prénom du prospect.',
                    ],
                    'need' => [
                        'type'        => 'string',
                        'description' => 'Besoin principal exprimé.',
                    ],
                    'budget' => [
                        'type'        => 'string',
                        'description' => 'Budget évoqué par le contact (optionnel).',
                    ],
                ],
                'required' => ['name', 'need'],
            ],
        );
    }
}
