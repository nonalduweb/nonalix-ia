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
use Illuminate\Support\Str;

/**
 * Adaptateur Google Gemini.
 *
 * Particularités absorbées ici :
 *   - le rôle « assistant » s'appelle `model` ;
 *   - les messages sont des `contents[].parts[]` ;
 *   - les outils sont regroupés sous `tools[0].functionDeclarations` ;
 *   - les appels d'outils n'ont PAS d'identifiant : on en génère un localement
 *     pour rester compatible avec le format neutre, et on ne le renvoie pas.
 */
class GeminiChatProvider implements ChatProvider
{
    use EstimatesCost;
    use SendsHttpRequests;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public function name(): AiProvider
    {
        return AiProvider::Gemini;
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function defaultModel(): string
    {
        return (string) config('ai.providers.gemini.default_model', 'gemini-2.5-flash');
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $payload = [
            'contents'         => $this->buildContents($request->messages),
            'generationConfig' => [
                'temperature'     => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ],
        ];

        if ($request->system !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $request->system]],
            ];
        }

        if ($request->hasTools()) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(
                    static fn (ToolDefinition $t) => $t->toGeminiFormat(),
                    $request->tools,
                ),
            ]];
        }

        $startedAt = microtime(true);

        $response = $this->sendWithRetries(
            fn () => $this->http()
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post("{$this->baseUrl}/models/{$request->model}:generateContent", $payload),
            $this->name()->value,
            $request->model,
        );

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
        $body      = $response->json();
        $candidate = $body['candidates'][0] ?? [];

        $text      = null;
        $toolCalls = [];

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $text = ($text ?? '').$part['text'];
            }

            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    // Gemini ne fournit pas d'identifiant d'appel : on en
                    // synthétise un pour respecter le format neutre.
                    id: 'gemini-'.Str::uuid7()->toString(),
                    name: (string) ($part['functionCall']['name'] ?? ''),
                    arguments: is_array($part['functionCall']['args'] ?? null)
                        ? $part['functionCall']['args']
                        : [],
                );
            }
        }

        $usage = new TokenUsage(
            inputTokens: (int) ($body['usageMetadata']['promptTokenCount'] ?? 0),
            outputTokens: (int) ($body['usageMetadata']['candidatesTokenCount'] ?? 0),
        );

        return new ChatResponse(
            content: $text,
            toolCalls: $toolCalls,
            usage: $usage,
            model: $request->model,
            provider: $this->name(),
            finishReason: $candidate['finishReason'] ?? null,
            latencyMs: $latencyMs,
            costMicros: $this->estimateCost($usage, $request->model),
        );
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function buildContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            if ($message->role === 'tool') {
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name'     => $message->name ?? 'tool',
                            // La réponse doit être un objet, pas une chaîne.
                            'response' => ['result' => $message->content ?? ''],
                        ],
                    ]],
                ];

                continue;
            }

            $parts = [];

            if ($message->content !== null && trim($message->content) !== '') {
                $parts[] = ['text' => $message->content];
            }

            foreach ($message->toolCalls as $call) {
                $parts[] = [
                    'functionCall' => [
                        'name' => $call->name,
                        'args' => $call->arguments === [] ? (object) [] : $call->arguments,
                    ],
                ];
            }

            if ($parts === []) {
                continue;
            }

            $contents[] = [
                'role'  => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => $parts,
            ];
        }

        return $contents;
    }
}
