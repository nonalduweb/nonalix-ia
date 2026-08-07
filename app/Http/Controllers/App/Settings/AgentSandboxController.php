<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Data\AI\ChatMessage;
use App\Exceptions\QuotaExceededException;
use App\Models\Agent;
use App\Models\Conversation;
use App\Services\AI\AgentRunner;
use App\Services\Billing\QuotaService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Banc d'essai : parler à son agent avant que quiconque d'autre ne le fasse.
 *
 * Sans cet écran, la seule façon d'entendre son agent était de publier le
 * widget sur son site en production et de lui écrire depuis sa propre vitrine.
 * C'est exactement l'inverse de ce qu'attend un client : voir le résultat
 * d'abord, s'engager ensuite.
 *
 * Rien n'est persisté. Ni contact, ni conversation, ni message : l'essai ne
 * doit pas polluer la boîte de réception ni la liste des contacts de
 * l'entreprise. L'historique vit dans la session de l'utilisateur, et la
 * conversation passée au moteur n'est jamais enregistrée — `AgentRunner` ne
 * l'utilise que pour porter le tenant, et `ai_usage_logs.conversation_id` est
 * nullable, ce qui permet d'en tracer le coût sans ligne de conversation.
 *
 * Les outils sont volontairement DÉSACTIVÉS : un essai ne doit créer aucun
 * prospect réel ni envoyer d'e-mail à qui que ce soit.
 */
class AgentSandboxController
{
    /** Bornes de l'historique gardé en session, en messages. */
    private const HISTORY_LIMIT = 20;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly QuotaService $quotas,
        private readonly TenantContext $context,
    ) {}

    public function chat(Request $request, Agent $agent): JsonResponse
    {
        abort_unless($request->user()->can('view', $agent), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $tenant = $this->context->currentOrFail();

        try {
            $this->quotas->assertWithinQuota($tenant, 'ai_requests');
        } catch (QuotaExceededException $e) {
            return response()->json([
                'error' => 'Votre quota de requêtes IA est épuisé. L\'essai reprendra au renouvellement.',
            ], 429);
        }

        $key     = $this->sessionKey($agent);
        $history = $request->session()->get($key, []);

        try {
            $result = $this->runner->run(
                conversation: $this->transientConversation($tenant->id),
                agent: $agent,
                incomingText: $validated['message'],
                tools: [],
                history: $this->toChatMessages($history),
            );
        } catch (Throwable $e) {
            Log::channel('ai')->error('Échec du banc d\'essai de l\'agent.', [
                'tenant_id' => $tenant->id,
                'agent_id'  => $agent->id,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'L\'agent n\'a pas pu répondre. Vérifiez le fournisseur et la clé API dans les réglages avancés.',
            ], 502);
        }

        $reply = $result['content'] ?? $agent->effectiveFallbackMessage();

        $history[] = ['role' => 'user', 'content' => $validated['message']];
        $history[] = ['role' => 'assistant', 'content' => $reply];

        $request->session()->put($key, array_slice($history, -self::HISTORY_LIMIT));

        return response()->json([
            'reply' => $reply,
            // De quoi expliquer une réponse décevante sans ouvrir les journaux :
            // « il n'a trouvé aucun document » est la cause la plus fréquente.
            'debug' => [
                'rag_chunks' => count($result['metadata']['rag_chunks'] ?? []),
                'total_ms'   => $result['metadata']['total_ms'] ?? null,
            ],
        ]);
    }

    /** Repart d'une conversation vierge. */
    public function reset(Request $request, Agent $agent): JsonResponse
    {
        abort_unless($request->user()->can('view', $agent), 403);

        $request->session()->forget($this->sessionKey($agent));

        return response()->json(['status' => 'reset']);
    }

    private function sessionKey(Agent $agent): string
    {
        return 'agent.sandbox.'.$agent->id;
    }

    /**
     * Conversation jamais enregistrée, porteuse du seul tenant.
     *
     * `AgentRunner` s'en sert pour tracer la consommation et journaliser ; son
     * identifiant reste nul, ce que le schéma accepte.
     */
    private function transientConversation(string $tenantId): Conversation
    {
        $conversation = new Conversation(['channel' => 'essai']);
        $conversation->tenant_id = $tenantId;

        return $conversation;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, ChatMessage>
     */
    private function toChatMessages(array $history): array
    {
        return array_map(
            static fn (array $m) => $m['role'] === 'user'
                ? ChatMessage::user($m['content'])
                : ChatMessage::assistant($m['content']),
            $history,
        );
    }
}
