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

class OpenAiChatProvider implements ChatProvider
{
    use EstimatesCost;
    use SendsHttpRequests;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly ?string $organization = null,
    ) {}

    public function name(): AiProvider
    {
        return AiProvider::OpenAI;
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function defaultModel(): string
    {
        return (string) config('ai.providers.openai.default_model', 'gpt-4.1-mini');
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $payload = [
            'model'       => $request->model,
            'messages'    => $this->buildMessages($request),
            'temperature' => $request->temperature,
            'max_tokens'  => $request->maxTokens,
        ];

        if ($request->hasTools()) {
            $payload['tools'] = array_map(
                static fn (ToolDefinition $t) => $t->toOpenAiFormat(),
                $request->tools,
            );
            $payload['tool_choice'] = $request->toolChoice ?? 'auto';
        }

        $startedAt = microtime(true);

        $response = $this->sendWithRetries(
            fn () => $this->http()
                ->withToken($this->apiKey)
                ->withHeaders(array_filter([
                    'OpenAI-Organization' => $this->organization,
                ]))
                ->post("{$this->baseUrl}/chat/completions", $payload),
            $this->name()->value,
            $request->model,
        );

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
        $body      = $response->json();
        $choice    = $body['choices'][0] ?? [];
        $message   = $choice['message'] ?? [];

        $usage = new TokenUsage(
            inputTokens: (int) ($body['usage']['prompt_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['completion_tokens'] ?? 0),
        );

        return new ChatResponse(
            content: $message['content'] ?? null,
            toolCalls: $this->parseToolCalls($message['tool_calls'] ?? []),
            usage: $usage,
            model: $body['model'] ?? $request->model,
            provider: $this->name(),
            finishReason: $choice['finish_reason'] ?? null,
            latencyMs: $latencyMs,
            costMicros: $this->estimateCost($usage, $request->model),
        );
    }

    /**
     * OpenAI attend le prompt système comme premier message du tableau,
     * contrairement à Anthropic et Gemini qui le portent séparément.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(ChatRequest $request): array
    {
        $messages = [];

        if ($request->system !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }

        foreach ($request->messages as $message) {
            $messages[] = $this->formatMessage($message);
        }

        return $messages;
    }

    /** @return array<string, mixed> */
    private function formatMessage(ChatMessage $message): array
    {
        if ($message->role === 'tool') {
            return [
                'role'         => 'tool',
                'tool_call_id' => $message->toolCallId,
                'content'      => $message->content ?? '',
            ];
        }

        $formatted = ['role' => $message->role];

        // Un message d'assistant purement outillé a un contenu nul : la clé
        // doit tout de même être présente, sinon l'API rejette la requête.
        $formatted['content'] = $message->content;

        if ($message->hasToolCalls()) {
            $formatted['tool_calls'] = array_map(
                static fn (ToolCall $call) => [
                    'id'       => $call->id,
                    'type'     => 'function',
                    'function' => [
                        'name'      => $call->name,
                        'arguments' => json_encode($call->arguments, JSON_THROW_ON_ERROR),
                    ],
                ],
                $message->toolCalls,
            );
        }

        return $formatted;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawCalls
     * @return array<int, ToolCall>
     */
    private function parseToolCalls(array $rawCalls): array
    {
        return array_values(array_map(
            static fn (array $call) => ToolCall::fromJson(
                id: (string) ($call['id'] ?? ''),
                name: (string) ($call['function']['name'] ?? ''),
                json: $call['function']['arguments'] ?? null,
            ),
            $rawCalls,
        ));
    }
}
