<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AI\AgentTool;
use App\Data\AI\ChatMessage;
use App\Data\AI\ChatRequest;
use App\Data\AI\ChatResponse;
use App\Data\AI\TokenUsage;
use App\Data\AI\ToolCall;
use App\Enums\AiProvider;
use App\Enums\IncidentLevel;
use App\Exceptions\AiProviderException;
use App\Models\Agent;
use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\Incident;
use App\Services\Billing\QuotaService;
use App\Services\Knowledge\VectorSearchService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestre un tour de conversation de l'agent IA.
 *
 * Enchaînement : construction du contexte → appel du modèle → exécution des
 * outils demandés → réinjection → jusqu'à obtenir un texte, dans la limite
 * d'un nombre d'itérations borné.
 *
 * Ne persiste rien et n'envoie rien : cette classe produit un résultat, le job
 * appelant décide quoi en faire. C'est ce qui la rend testable sans WhatsApp.
 */
class AgentRunner
{
    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly PromptBuilder $prompts,
        private readonly ConversationMemory $memory,
        private readonly VectorSearchService $search,
        private readonly QuotaService $quotas,
    ) {}

    /**
     * @param  array<string, AgentTool>  $tools  indexés par nom
     * @return array{content: ?string, metadata: array<string, mixed>, handover: bool}
     */
    public function run(
        Conversation $conversation,
        Agent $agent,
        string $incomingText,
        array $tools = [],
    ): array {
        $startedAt  = microtime(true);
        $totalUsage = new TokenUsage;
        $usedTools  = [];

        // 1. Fragments de la base de connaissances.
        $chunks = $agent->rag_enabled
            ? $this->search->search(
                query: $incomingText,
                topK: $agent->rag_top_k ?? (int) config('ai.agent.rag_top_k', 5),
                minScore: $agent->rag_min_score ?? (float) config('ai.agent.rag_min_score', 0.75),
            )
            : [];

        // 2. Prompt système et historique.
        $provider = $this->providers->chatFor($agent);

        $request = new ChatRequest(
            model: $agent->model ?: $provider->defaultModel(),
            messages: [
                ...$this->memory->forConversation($conversation, $agent),
                ChatMessage::user($incomingText),
            ],
            system: $this->prompts->build($agent, $chunks),
            // Un fournisseur sans tool-calling reçoit une requête sans outils :
            // l'agent fonctionne en mode dégradé plutôt que d'échouer.
            tools: $provider->supportsTools()
                ? array_map(static fn (AgentTool $t) => $t->definition(), array_values($tools))
                : [],
            temperature: (float) $agent->temperature,
            maxTokens: (int) $agent->max_tokens,
        );

        $maxIterations = (int) config('ai.agent.max_tool_iterations', 4);
        $response      = null;

        // 3. Boucle outils, bornée : sans cette limite, un modèle qui rappelle
        //    indéfiniment le même outil consommerait le quota du client.
        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $response   = $this->callProvider($provider, $request, $agent, $conversation);
            $totalUsage = $totalUsage->plus($response->usage);

            $this->recordUsage($conversation, $agent, $response);

            if (! $response->hasToolCalls()) {
                break;
            }

            $request = $request->appending(
                $response->toAssistantMessage(),
                ...$this->executeToolCalls($response->toolCalls, $tools, $conversation, $usedTools),
            );
        }

        // Le modèle a épuisé ses itérations sans produire de texte : on ne
        // laisse jamais le contact sans réponse.
        $content = $response?->hasContent() === true
            ? $response->content
            : ($usedTools === [] ? $agent->effectiveFallbackMessage() : null);

        return [
            'content'  => $content,
            'handover' => in_array('request_human_handover', $usedTools, true),
            'metadata' => [
                'provider'      => $response?->provider->value ?? $agent->provider->value,
                'model'         => $response?->model ?? $agent->model,
                'usage'         => $totalUsage->toArray(),
                'tools_used'    => $usedTools,
                'rag_chunks'    => array_column($chunks, 'chunk_id'),
                'rag_scores'    => array_column($chunks, 'score'),
                'total_ms'      => (int) ((microtime(true) - $startedAt) * 1000),
                'finish_reason' => $response?->finishReason,
            ],
        ];
    }

    /**
     * Appelle le fournisseur, avec bascule sur le repli en cas d'échec définitif.
     *
     * Le repli n'est tenté qu'une fois : si les deux fournisseurs sont en
     * panne, insister ne ferait qu'allonger le délai avant le message de repli.
     */
    private function callProvider(
        \App\Contracts\AI\ChatProvider $provider,
        ChatRequest $request,
        Agent $agent,
        Conversation $conversation,
    ): ChatResponse {
        try {
            return $provider->chat($request);
        } catch (AiProviderException $e) {
            Log::channel('ai')->error('Échec du fournisseur IA.', array_merge($e->context(), [
                'tenant_id'       => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
            ]));

            Incident::record(
                tenantId: $conversation->tenant_id,
                level: $e->retryable ? IncidentLevel::Warning : IncidentLevel::Error,
                source: 'ai',
                code: 'provider_failure.'.$provider->name()->value,
                title: sprintf('Fournisseur %s en échec', $provider->name()->label()),
                context: $e->context(),
            );

            $fallback = $this->providers->fallbackChat($provider->name());

            if ($fallback === null) {
                throw $e;
            }

            Log::channel('ai')->warning('Bascule sur le fournisseur de repli.', [
                'from' => $provider->name()->value,
                'to'   => $fallback->name()->value,
            ]);

            // Le modèle de l'agent n'existe pas chez le fournisseur de repli :
            // on repasse sur le modèle par défaut de ce dernier.
            return $fallback->chat($request->withMessages($request->messages));
        }
    }

    /**
     * Exécute les outils demandés et produit les messages de résultat.
     *
     * Un outil qui échoue ne fait pas échouer le tour : son erreur est
     * renvoyée au modèle, qui peut alors s'excuser ou transférer. Une exception
     * remontée ici laisserait le contact sans réponse du tout.
     *
     * @param  array<int, ToolCall>       $calls
     * @param  array<string, AgentTool>   $tools
     * @param  array<int, string>         $usedTools  (référence, enrichie)
     * @return array<int, ChatMessage>
     */
    private function executeToolCalls(
        array $calls,
        array $tools,
        Conversation $conversation,
        array &$usedTools,
    ): array {
        $results = [];

        foreach ($calls as $call) {
            $tool = $tools[$call->name] ?? null;

            if ($tool === null) {
                $results[] = ChatMessage::tool(
                    $call->id,
                    $call->name,
                    "Cet outil n'est pas disponible. Réponds sans l'utiliser.",
                );

                continue;
            }

            try {
                $output      = $tool->execute($call->arguments, $conversation);
                $usedTools[] = $call->name;
            } catch (Throwable $e) {
                Log::channel('ai')->error('Échec d\'un outil de l\'agent.', [
                    'tool'            => $call->name,
                    'conversation_id' => $conversation->id,
                    'error'           => $e->getMessage(),
                ]);

                $output = "L'outil a rencontré une erreur. Poursuis sans cette "
                    ."information et propose un transfert si nécessaire.";
            }

            $results[] = ChatMessage::tool($call->id, $call->name, $output);
        }

        return $results;
    }

    /** Trace la consommation et le coût, appel par appel. */
    private function recordUsage(Conversation $conversation, Agent $agent, ChatResponse $response): void
    {
        AiUsageLog::create([
            'conversation_id' => $conversation->id,
            'agent_id'        => $agent->id,
            'provider'        => $response->provider->value,
            'model'           => $response->model,
            'operation'       => 'chat',
            'input_tokens'    => $response->usage->inputTokens,
            'output_tokens'   => $response->usage->outputTokens,
            'cost_micros'     => $response->costMicros,
            'latency_ms'      => $response->latencyMs,
            'status'          => 'success',
            'created_at'      => now(),
        ]);

        $tenantId = $conversation->tenant_id;

        $this->quotas->increment($tenantId, 'ai_requests');
        $this->quotas->increment($tenantId, 'ai_input_tokens', $response->usage->inputTokens);
        $this->quotas->increment($tenantId, 'ai_output_tokens', $response->usage->outputTokens);
    }

    /** Fournisseur par défaut, utilisé quand l'agent n'en précise aucun. */
    public function defaultProvider(): AiProvider
    {
        return AiProvider::from((string) config('ai.default'));
    }
}
