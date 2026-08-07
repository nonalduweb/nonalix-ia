<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Contracts\AI\AgentTool;
use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Socle des outils délégués à un scénario n8n.
 *
 * L'exécution renvoie toujours un couple (succès, message) : les sous-classes
 * qui enregistrent une conséquence en base — un rendez-vous pris, un devis
 * envoyé — doivent savoir si l'action a réellement abouti. Déduire le succès
 * du texte renvoyé au modèle serait fragile : ce texte est rédigé pour le LLM,
 * il peut changer sans que personne ne pense au code qui l'inspecte.
 */
abstract class BaseN8nTool implements AgentTool
{
    abstract public function name(): string;

    public function execute(array $arguments, Conversation $conversation): string
    {
        return $this->call($arguments, $conversation)['message'];
    }

    /**
     * Déclenche le scénario n8n et rend compte de son issue.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{ok: bool, message: string}
     */
    protected function call(array $arguments, Conversation $conversation): array
    {
        // L'agent porteur de la configuration est celui qui MÈNE cette
        // conversation, pas nécessairement l'agent par défaut de l'entreprise
        // (voir GenerateAgentReplyJob). Sans cela, une conversation confiée à
        // un agent secondaire appellerait le webhook d'un autre.
        $agent = $conversation->agent ?? $conversation->tenant?->activeAgent();

        if ($agent === null) {
            return $this->failure('Action impossible : aucun agent configuré.');
        }

        $webhookUrl = $agent->settings['n8n_webhook_url'] ?? null;

        if (empty($webhookUrl)) {
            return $this->failure(
                "L'action n'a pas pu être exécutée car l'URL du webhook d'automatisation (n8n) n'est pas "
                ."configurée dans les paramètres de l'agent. Explique cela au client poliment et propose "
                ."un transfert à un humain."
            );
        }

        try {
            $payload = [
                'action'          => $this->name(),
                'tenant_id'       => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'contact_id'      => $conversation->contact_id,
                'wa_id'           => $conversation->contact?->wa_id,
                'arguments'       => $arguments,
            ];

            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'ok'      => true,
                    'message' => $data['message'] ?? $data['output'] ?? "L'action a été exécutée avec succès.",
                ];
            }

            Log::channel('ai')->error('Webhook n8n en échec.', [
                'tool'   => $this->name(),
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return $this->failure(
                "L'action a été envoyée mais le serveur d'automatisation a retourné une erreur. "
                ."Poursuis la discussion et propose un transfert humain."
            );
        } catch (Throwable $e) {
            Log::channel('ai')->error('Erreur de connexion au Webhook n8n.', [
                'tool'  => $this->name(),
                'error' => $e->getMessage(),
            ]);

            return $this->failure(
                "Impossible de se connecter au serveur d'automatisation. Poursuis la discussion et "
                ."propose un transfert humain."
            );
        }
    }

    /** @return array{ok: bool, message: string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
