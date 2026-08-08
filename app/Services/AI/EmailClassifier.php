<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Agent;
use App\Data\AI\ChatRequest;
use App\Data\AI\ChatMessage;
use Throwable;
use Illuminate\Support\Facades\Log;

class EmailClassifier
{
    public function __construct(
        private readonly AiProviderManager $providers,
    ) {}

    /**
     * Analyse l'intention, la priorité et la sensibilité d'un e-mail entrant.
     *
     * @return array{intent: string, priority: string, is_sensitive: bool, explanation: string}
     */
    public function classify(Agent $agent, string $subject, string $body): array
    {
        $provider = $this->providers->chatFor($agent);

        $prompt = <<<TEXT
Tu es un classificateur d'e-mails d'assistance pour le support client.
Analyse le sujet et le corps de l'e-mail suivant et retourne UNIQUEMENT un objet JSON valide contenant :
- "intent" : (valeurs autorisées : "devis" | "support" | "reclamation" | "rendez_vous" | "relance" | "faq" | "autre")
- "priority" : (valeurs : "low" | "normal" | "high")
- "is_sensitive" : boolean (vrai si l'e-mail contient un litige, une réclamation financière/facturation/remboursement, un contrat légal, ou une plainte complexe. Faux si c'est une demande générale sur les horaires, les tarifs, des infos ou une FAQ simple).
- "explanation" : justification courte en une phrase.

E-MAIL A ANALYSER :
Sujet : {$subject}
Contenu : {$body}

Retourne UNIQUEMENT le JSON. Pas de texte en dehors du JSON, pas de balises de code Markdown (comme ```json).
TEXT;

        try {
            $request = new ChatRequest(
                model: $agent->model ?: $provider->defaultModel(),
                messages: [ChatMessage::user($prompt)],
                system: "Tu es un parseur JSON strict. Tu ne réponds qu'avec du JSON valide.",
                temperature: 0.1,
                maxTokens: 512
            );

            $response = $provider->chat($request);
            $jsonText = trim($response->content);

            // Nettoyage des balises Markdown de code block
            $jsonText = preg_replace('/^```json\s*/i', '', $jsonText);
            $jsonText = preg_replace('/```$/', '', $jsonText);
            $jsonText = trim($jsonText);

            $data = json_decode($jsonText, true);

            if (is_array($data) && isset($data['intent'], $data['is_sensitive'])) {
                return [
                    'intent'       => (string) $data['intent'],
                    'priority'     => (string) ($data['priority'] ?? 'normal'),
                    'is_sensitive' => (bool) $data['is_sensitive'],
                    'explanation'  => (string) ($data['explanation'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            Log::error("Erreur de classification d'email : " . $e->getMessage());
        }

        // Repli par défaut en cas d'erreur de réseau ou de parsing
        return [
            'intent'       => 'autre',
            'priority'     => 'normal',
            'is_sensitive' => false,
            'explanation'  => 'Impossible de classifier l\'e-mail.',
        ];
    }
}
