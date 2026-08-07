<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Data\AI\ToolDefinition;

class SendEmailTool extends BaseN8nTool
{
    public function name(): string
    {
        return 'send_email';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Envoie un e-mail au contact. À appeler lorsque le contact demande à recevoir des informations, un récapitulatif ou un document par courriel.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'email' => [
                        'type'        => 'string',
                        'description' => 'Adresse e-mail du contact.',
                    ],
                    'subject' => [
                        'type'        => 'string',
                        'description' => 'Objet de l\'e-mail.',
                    ],
                    'body' => [
                        'type'        => 'string',
                        'description' => 'Contenu du message de l\'e-mail.',
                    ],
                ],
                'required' => ['email', 'subject', 'body'],
            ],
        );
    }
}
