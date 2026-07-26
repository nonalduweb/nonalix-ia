<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\DocumentStatus;
use App\Models\DocumentChunk;
use App\Services\AI\AiProviderManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recherche sémantique dans la base de connaissances d'un tenant.
 *
 * La requête est écrite en SQL brut : Eloquent ne connaît pas l'opérateur de
 * distance `<=>` de pgvector, et charger une colonne de 1536 flottants par
 * ligne via l'ORM serait de toute façon absurde.
 *
 * Le filtre `tenant_id` est appliqué en dur dans la clause WHERE, en plus du
 * scope applicatif : cette requête contourne Eloquent, donc le cloisonnement
 * doit être explicite ici.
 */
class VectorSearchService
{
    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<int, array{
     *     chunk_id: string, document_id: string, document_title: string,
     *     content: string, metadata: array<string, mixed>, score: float
     * }>
     */
    public function search(string $query, int $topK = 5, float $minScore = 0.75): array
    {
        $tenantId = $this->context->id();

        if ($tenantId === null || trim($query) === '') {
            return [];
        }

        try {
            $vector = $this->providers->embeddings()->embed([$query])->first();
        } catch (Throwable $e) {
            // Une panne d'embeddings ne doit pas faire échouer la réponse :
            // l'agent répondra sans RAG plutôt que de ne pas répondre.
            Log::channel('ai')->warning('Recherche vectorielle indisponible.', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }

        if ($vector === null) {
            return [];
        }

        $encoded = DocumentChunk::encodeVector($vector);

        // HNSW combiné à un filtre WHERE sélectif peut renvoyer moins de
        // résultats que demandé : on élargit la liste de candidats explorés.
        DB::statement('SET LOCAL hnsw.ef_search = 100');

        $rows = DB::select(<<<'SQL'
            SELECT
                c.id            AS chunk_id,
                c.document_id   AS document_id,
                d.title         AS document_title,
                c.content       AS content,
                c.metadata      AS metadata,
                1 - (c.embedding <=> ?::vector) AS score
            FROM document_chunks c
            INNER JOIN documents d ON d.id = c.document_id
            WHERE c.tenant_id = ?
              AND d.status = ?
              AND d.deleted_at IS NULL
              AND c.embedding IS NOT NULL
              AND 1 - (c.embedding <=> ?::vector) >= ?
            ORDER BY c.embedding <=> ?::vector
            LIMIT ?
        SQL, [
            $encoded, $tenantId, DocumentStatus::Ready->value,
            $encoded, $minScore, $encoded, $topK,
        ]);

        return array_map(static fn (object $row) => [
            'chunk_id'       => $row->chunk_id,
            'document_id'    => $row->document_id,
            'document_title' => $row->document_title,
            'content'        => $row->content,
            'metadata'       => json_decode((string) $row->metadata, true) ?: [],
            'score'          => round((float) $row->score, 4),
        ], $rows);
    }

    /**
     * Écrit l'embedding d'un fragment.
     *
     * Passe par une requête paramétrée plutôt que par Eloquent : le cast
     * `::vector` est indispensable et l'ORM n'a aucun moyen de le produire.
     *
     * @param  array<int, float>  $vector
     */
    public function storeEmbedding(string $chunkId, string $tenantId, array $vector): void
    {
        DB::update(
            'UPDATE document_chunks SET embedding = ?::vector WHERE id = ? AND tenant_id = ?',
            [DocumentChunk::encodeVector($vector), $chunkId, $tenantId],
        );
    }
}
