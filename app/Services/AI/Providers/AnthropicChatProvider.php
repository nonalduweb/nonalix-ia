<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\ChatProvider;
use App\Data\AI\ChatMessage;
use App\Data\AI\ChatRequest;
use App\Data\AI\ChatResponse;
use App\Data\AI\TokenUsage;
use App\Data\AI\ToolCall;
use App\Data\AI\ToolDefinition;
use App\Enums\AiProvider;
use App\Services\AI\Concerns\EstimatesCost;
use App\Services\AI\Concerns\SendsHttpRequests;

/**
 * Adaptateur Anthropic (Claude).
 *
 * Trois différences structurelles avec OpenAI, toutes absorbées ici :
 *   - le prompt système est un paramètre de premier niveau, pas un message ;
 *   - le contenu est une LISTE de blocs typés (`text`, `tool_use`, `tool_result`)
 *     et non une chaîne ;
 *   - les résultats d'outils sont des blocs à l'intérieur d'un message `user`,
 *     alors qu'OpenAI utilise un rôle `tool` dédié.
 */
class AnthropicChatProvider implements ChatProvider
{
    use EstimatesCost;
    use SendsHttpRequests;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $version,
    ) {}

    public function name(): AiProvider
    {
        return AiProvider::Anthropic;
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function defaultModel(): string
    {
        return (string) config('ai.providers.anthropic.default_model', 'claude-sonnet-5');
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $payload = [
            'model'       => $request->model,
            'max_tokens'  => $request->maxTokens,
            'temperature' => $request->temperature,
            'messages'    => $this->buildMessages($request->messages),
        ];

        if ($request->system !== null) {
            $payload['system'] = $request->system;
        }

        if ($request->hasTools()) {
            $payload['tools'] = array_map(
                static fn (ToolDefinition $t) => $t->toAnthropicFormat(),
                $request->tools,
            );
        }

        $startedAt = microtime(true);

        $response = $this->sendWithRetries(
            fn () => $this->http()
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => $this->version,
                ])
                ->post("{$this->baseUrl}/v1/messages", $payload),
            $this->name()->value,
            $request->model,
        );

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
        $body      = $response->json();

        $text      = null;
        $toolCalls = [];

        foreach ($body['content'] ?? [] as $block) {
            match ($block['type'] ?? null) {
                'text'     => $text = ($text ?? '').$block['text'],
                'tool_use' => $toolCalls[] = new ToolCall(
                    id: (string) ($block['id'] ?? ''),
                    name: (string) ($block['name'] ?? ''),
                    arguments: is_array($block['input'] ?? null) ? $block['input'] : [],
                ),
                default    => null,
            };
        }

        $usage = new TokenUsage(
            inputTokens: (int) ($body['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['output_tokens'] ?? 0),
        );

        return new ChatResponse(
            content: $text,
            toolCalls: $toolCalls,
            usage: $usage,
            model: $body['model'] ?? $request->model,
            provider: $this->name(),
            finishReason: $body['stop_reason'] ?? null,
            latencyMs: $latencyMs,
            costMicros: $this->estimateCost($usage, $request->model),
        );
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            // Un résultat d'outil est un bloc `tool_result` porté par un
            // message `user`. Les résultats consécutifs sont fusionnés dans un
            // seul message : l'API refuse deux messages `user` d'affilée.
            if ($message->role === 'tool') {
                $block = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $message->toolCallId,
                    'content'     => $message->content ?? '',
                ];

                $last = array_key_last($formatted);

                if ($last !== null && $formatted[$last]['role'] === 'user'
                    && ($formatted[$last]['content'][0]['type'] ?? null) === 'tool_result') {
                    $formatted[$last]['content'][] = $block;

                    continue;
                }

                $formatted[] = ['role' => 'user', 'content' => [$block]];

                continue;
            }

            $content = [];

            if ($message->content !== null && trim($message->content) !== '') {
                $content[] = ['type' => 'text', 'text' => $message->content];
            }

            foreach ($message->toolCalls as $call) {
                $content[] = [
                    'type'  => 'tool_use',
                    'id'    => $call->id,
                    'name'  => $call->name,
                    'input' => $call->arguments === [] ? (object) [] : $call->arguments,
                ];
            }

            // Anthropic rejette un message au contenu vide.
            if ($content === []) {
                continue;
            }

            $formatted[] = ['role' => $message->role, 'content' => $content];
        }

        return $formatted;
    }
}
