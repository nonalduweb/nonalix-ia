<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Contracts\AI\AgentTool;
use App\Data\AI\ToolDefinition;
use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Models\Lead;

/**
 * Enregistre la qualification d'un prospect à partir de la conversation.
 *
 * Le score est borné et la qualification est marquée `qualified_by = ai` :
 * une équipe commerciale ne doit jamais confondre une estimation produite par
 * un modèle avec une qualification validée par un collègue.
 */
class QualifyLeadTool implements AgentTool
{
    public function name(): string
    {
        return 'qualify_lead';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Enregistre les informations de qualification d'un prospect "
                ."lorsque le contact a exprimé un besoin concret. À appeler une fois "
                ."que tu as recueilli au moins le besoin principal.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'need' => [
                        'type'        => 'string',
                        'description' => 'Besoin exprimé par le contact, dans ses termes.',
                    ],
                    'budget' => [
                        'type'        => 'string',
                        'description' => 'Budget évoqué, si mentionné. Ne pas déduire.',
                    ],
                    'timeframe' => [
                        'type'        => 'string',
                        'description' => 'Échéance envisagée, si mentionnée.',
                    ],
                    'contact_name' => [
                        'type'        => 'string',
                        'description' => 'Nom du contact, s\'il l\'a donné.',
                    ],
                    'score' => [
                        'type'        => 'integer',
                        'description' => 'Maturité du prospect de 0 à 100. 50 ou plus = qualifié.',
                        'minimum'     => 0,
                        'maximum'     => 100,
                    ],
                    'intent' => [
                        'type'        => 'string',
                        'description' => 'Intention principale en quelques mots.',
                    ],
                ],
                'required' => ['need', 'score'],
            ],
        );
    }

    public function execute(array $arguments, Conversation $conversation): string
    {
        // Les arguments viennent d'un modèle probabiliste : on borne, on
        // filtre, on ne fait confiance à rien.
        $score = (int) ($arguments['score'] ?? 0);
        $score = max(0, min(100, $score));

        $answers = array_filter([
            'need'         => $this->clean($arguments['need'] ?? null),
            'budget'       => $this->clean($arguments['budget'] ?? null),
            'timeframe'    => $this->clean($arguments['timeframe'] ?? null),
            'contact_name' => $this->clean($arguments['contact_name'] ?? null),
        ], static fn ($v) => $v !== null);

        $lead = Lead::query()
            ->where('contact_id', $conversation->contact_id)
            ->open()
            ->first();

        if ($lead === null) {
            $lead = new Lead([
                'contact_id'      => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'status'          => LeadStatus::New,
                'source'          => 'whatsapp_ai',
            ]);
            $lead->save();
        }

        $lead->applyAiQualification($answers, $score, $this->clean($arguments['intent'] ?? null));

        // Le nom donné en conversation enrichit la fiche contact, mais
        // n'écrase jamais un nom déjà saisi par un opérateur.
        if (isset($answers['contact_name']) && $conversation->contact->name === null) {
            $conversation->contact->update(['name' => $answers['contact_name']]);
        }

        return "Qualification enregistrée. Poursuis la conversation normalement, "
            ."sans mentionner cet enregistrement au contact.";
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 500);
    }
}
