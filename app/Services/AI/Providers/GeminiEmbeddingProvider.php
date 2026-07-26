<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\EmbeddingProvider;
use App\Data\AI\EmbeddingResult;
use App\Data\AI\TokenUsage;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderException;
use App\Services\AI\Concerns\SendsHttpRequests;

class GeminiEmbeddingProvider implements EmbeddingProvider
{
    use SendsHttpRequests;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function name(): AiProvider
    {
        return AiProvider::Gemini;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function maxBatchSize(): int
    {
        return 100;
    }

    public function embed(array $texts): EmbeddingResult
    {
        if ($texts === []) {
            return new EmbeddingResult([], new TokenUsage, $this->model, $this->name(), $this->dimensions);
        }

        $startedAt = microtime(true);

        // Gemini expose un point d'entrée batch dédié ; l'API unitaire
        // multiplierait les allers-retours sur une ingestion de document.
        $requests = array_map(fn (string $text) => [
            'model'                => "models/{$this->model}",
            'content'              => ['parts' => [['text' => $text]]],
            'taskType'             => 'RETRIEVAL_DOCUMENT',
            // Tronque à la dimension de la colonne pgvector.
            'outputDimensionality' => $this->dimensions,
        ], array_values($texts));

        $response = $this->sendWithRetries(
            fn () => $this->http()
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post("{$this->baseUrl}/models/{$this->model}:batchEmbedContents", [
                    'requests' => $requests,
                ]),
            $this->name()->value,
            $this->model,
        );

        $vectors = array_map(
            static fn (array $item) => array_map('floatval', $item['values'] ?? []),
            $response->json('embeddings') ?? [],
        );

        if (count($vectors) !== count($texts)) {
            throw AiProviderException::permanent(
                sprintf('Réponse incomplète : %d vecteurs pour %d textes.', count($vectors), count($texts)),
                $this->name()->value,
                $this->model,
            );
        }

        return new EmbeddingResult(
            vectors: $vectors,
            // Gemini ne renvoie pas le décompte de tokens sur cet endpoint.
            usage: new TokenUsage,
            model: $this->model,
            provider: $this->name(),
            dimensions: $this->dimensions,
            latencyMs: (int) ((microtime(true) - $startedAt) * 1000),
        );
    }
}
