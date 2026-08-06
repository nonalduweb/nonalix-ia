<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Contracts\Knowledge\DocumentExtractor;
use App\Data\Knowledge\Chunk;
use App\Enums\DocumentStatus;
use App\Models\Agent;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\AI\AiProviderManager;
use App\Services\Billing\QuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Chaîne d'ingestion d'un document : extraction → découpage → vectorisation.
 *
 * Le statut du document reflète l'étape en cours, ce qui rend l'attente
 * lisible côté client : une ingestion de 200 pages prend plusieurs minutes, et
 * une barre figée sans explication est perçue comme une panne.
 */
class DocumentIngestionService
{
    /** @param array<int, DocumentExtractor> $extractors */
    public function __construct(
        private readonly array $extractors,
        private readonly RecursiveTextChunker $chunker,
        private readonly AiProviderManager $providers,
        private readonly VectorSearchService $vectors,
        private readonly QuotaService $quotas,
    ) {}

    public function ingest(Document $document): void
    {
        try {
            $document->update(['status' => DocumentStatus::Extracting]);

            $extracted = $this->extractorFor($document)->extract($document);

            if ($extracted->isEmpty()) {
                throw new RuntimeException('Aucun texte n\'a pu être extrait du document.');
            }

            $document->update(['status' => DocumentStatus::Chunking]);

            $chunks = $this->chunker->chunk($extracted->text, $extracted->metadata);

            if ($chunks === []) {
                throw new RuntimeException('Le découpage n\'a produit aucun fragment.');
            }

            // Réindexage : on repart d'une base propre, sinon les anciens
            // fragments resteraient interrogeables à côté des nouveaux.
            DocumentChunk::query()->where('document_id', $document->id)->delete();

            $document->update(['status' => DocumentStatus::Embedding]);

            $this->embedAndStore($document, $chunks);

            $document->update([
                'status'             => DocumentStatus::Ready,
                'chunks_count'       => count($chunks),
                'tokens_count'       => array_sum(array_map(static fn (Chunk $c) => $c->tokens, $chunks)),
                'embedding_provider' => config('ai.embeddings.provider'),
                'embedding_model'    => config('ai.embeddings.model'),
                'processed_at'       => now(),
                'error'              => null,
            ]);

            $this->quotas->increment($document->tenant_id, 'documents_stored');
        } catch (Throwable $e) {
            Log::channel('ai')->error('Échec d\'ingestion documentaire.', [
                'tenant_id'   => $document->tenant_id,
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);

            // Le message d'erreur est affiché au client : il doit être
            // compréhensible et actionnable (« ce PDF est un scan », etc.).
            $document->markFailed($e->getMessage());
        }
    }

    /**
     * Clé du client, si elle est utilisable pour les embeddings.
     *
     * Elle n'est retenue que si l'agent parle au MÊME fournisseur que celui
     * configuré pour les embeddings : une clé Anthropic ne signerait pas un
     * appel OpenAI. Sinon on retombe sur la clé de la plateforme.
     */
    private function tenantEmbeddingKey(): ?string
    {
        $agent = Agent::query()->where('is_active', true)->first();

        if ($agent?->api_key === null) {
            return null;
        }

        return $agent->provider->value === config('ai.embeddings.provider')
            ? $agent->api_key
            : null;
    }

    /**
     * Vectorise par lots et persiste.
     *
     * Les fragments sont écrits AVANT d'obtenir leurs vecteurs : si la
     * vectorisation échoue à mi-parcours, le texte reste en base et un
     * réindexage n'a pas à tout refaire depuis le fichier source.
     *
     * @param  array<int, Chunk>  $chunks
     */
    private function embedAndStore(Document $document, array $chunks): void
    {
        $provider  = $this->providers->embeddings($this->tenantEmbeddingKey());
        $batchSize = min(
            $provider->maxBatchSize(),
            (int) config('nonalix.knowledge.embedding_batch_size', 64),
        );

        foreach (array_chunk($chunks, $batchSize) as $batch) {
            $records = [];

            DB::transaction(function () use ($document, $batch, &$records) {
                foreach ($batch as $chunk) {
                    $records[] = DocumentChunk::create([
                        'document_id' => $document->id,
                        'position'    => $chunk->position,
                        'content'     => $chunk->content,
                        'tokens'      => $chunk->tokens,
                        'metadata'    => $chunk->metadata,
                    ]);
                }
            });

            $result = $provider->embed(array_map(
                static fn (Chunk $c) => $c->content,
                $batch,
            ));

            foreach ($records as $index => $record) {
                $vector = $result->vectors[$index] ?? null;

                if ($vector === null) {
                    continue;
                }

                $this->vectors->storeEmbedding($record->id, $document->tenant_id, $vector);
            }
        }
    }

    private function extractorFor(Document $document): DocumentExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($document)) {
                return $extractor;
            }
        }

        throw new RuntimeException(sprintf(
            'Aucun extracteur disponible pour le format « %s ».',
            $document->source_type,
        ));
    }
}
