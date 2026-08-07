<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Data\AI\ToolDefinition;

class CheckOrderStatusTool extends BaseN8nTool
{
    public function name(): string
    {
        return 'check_order_status';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Vérifie l'état ou le suivi d'une commande en cours à partir de sa référence ou numéro.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'order_id' => [
                        'type'        => 'string',
                        'description' => 'Numéro ou référence de la commande (ex: CMD-12345).',
                    ],
                ],
                'required' => ['order_id'],
            ],
        );
    }
}
