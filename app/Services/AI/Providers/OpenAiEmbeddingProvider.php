<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\EmbeddingProvider;
use App\Data\AI\EmbeddingResult;
use App\Data\AI\TokenUsage;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderException;
use App\Services\AI\Concerns\EstimatesCost;
use App\Services\AI\Concerns\SendsHttpRequests;

class OpenAiEmbeddingProvider implements EmbeddingProvider
{
    use EstimatesCost;
    use SendsHttpRequests;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function name(): AiProvider
    {
        return AiProvider::OpenAI;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function maxBatchSize(): int
    {
        return 96;
    }

    public function embed(array $texts): EmbeddingResult
    {
        if ($texts === []) {
            return new EmbeddingResult([], new TokenUsage, $this->model, $this->name(), $this->dimensions);
        }

        $startedAt = microtime(true);

        $response = $this->sendWithRetries(
            fn () => $this->http()
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/embeddings", [
                    'model' => $this->model,
                    'input' => array_values($texts),
                    // Tronque le vecteur côté API : garantit que la dimension
                    // correspond exactement à la colonne pgvector.
                    'dimensions' => $this->dimensions,
                ]),
            $this->name()->value,
            $this->model,
        );

        $body = $response->json();
        $data = $body['data'] ?? [];

        // L'API peut renvoyer les vecteurs dans le désordre : on les remet en
        // ordre d'index, car ils sont associés positionnellement aux fragments.
        usort($data, static fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        $vectors = array_map(
            static fn (array $item) => array_map('floatval', $item['embedding'] ?? []),
            $data,
        );

        if (count($vectors) !== count($texts)) {
            throw AiProviderException::permanent(
                sprintf('Réponse incomplète : %d vecteurs pour %d textes.', count($vectors), count($texts)),
                $this->name()->value,
                $this->model,
            );
        }

        $usage = new TokenUsage(inputTokens: (int) ($body['usage']['prompt_tokens'] ?? 0));

        return new EmbeddingResult(
            vectors: $vectors,
            usage: $usage,
            model: $this->model,
            provider: $this->name(),
            dimensions: $this->dimensions,
            latencyMs: (int) ((microtime(true) - $startedAt) * 1000),
            costMicros: $this->estimateCost($usage, $this->model),
        );
    }
}
