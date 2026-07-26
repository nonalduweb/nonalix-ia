<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Contracts\AI\AgentTool;
use App\Data\AI\ToolDefinition;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Services\Audit\AuditLogger;

/**
 * Transfert de la conversation à un opérateur humain.
 *
 * Outil le plus important du lot : c'est la soupape qui empêche l'agent de
 * s'obstiner sur une demande qu'il ne sait pas traiter. Il coupe l'IA sur la
 * conversation et la place en file d'attente humaine.
 */
class RequestHumanHandoverTool implements AgentTool
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function name(): string
    {
        return 'request_human_handover';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Transfère la conversation à un conseiller humain. À utiliser dès "
                ."que le contact demande une personne, exprime du mécontentement, "
                ."aborde un litige ou une réclamation, ou pose une question à "
                ."laquelle tu ne peux pas répondre de façon fiable.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'reason' => [
                        'type'        => 'string',
                        'description' => 'Motif court du transfert, destiné au conseiller.',
                        'enum'        => [
                            'demande_explicite', 'question_hors_perimetre',
                            'reclamation', 'negociation_tarifaire',
                            'incident_technique', 'autre',
                        ],
                    ],
                    'summary' => [
                        'type'        => 'string',
                        'description' => 'Résumé en une ou deux phrases de ce que veut le contact.',
                    ],
                ],
                'required' => ['reason'],
            ],
        );
    }

    public function execute(array $arguments, Conversation $conversation): string
    {
        $reason = (string) ($arguments['reason'] ?? 'autre');

        $conversation->forceFill([
            'ai_enabled'      => false,
            'handover_at'     => now(),
            'handover_reason' => mb_substr($reason, 0, 60),
            // `pending` fait remonter la conversation dans la file des
            // conversations en attente d'un humain.
            'status'          => ConversationStatus::Pending,
        ])->save();

        $this->audit->log('conversation.handover_requested', $conversation, context: [
            'reason'  => $reason,
            'summary' => $arguments['summary'] ?? null,
            'by'      => 'ai',
        ]);

        // Renvoyé au modèle : il doit maintenant l'annoncer au contact, sans
        // promettre de délai que l'entreprise ne pourrait pas tenir.
        return "Transfert effectué. Informe le contact qu'un conseiller va prendre "
            ."le relais, sans annoncer de délai précis. N'ajoute aucune autre réponse.";
    }
}
