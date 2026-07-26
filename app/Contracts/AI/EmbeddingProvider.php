<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Data\AI\EmbeddingResult;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderException;

/**
 * Contrat de vectorisation, séparé de la génération.
 *
 * Séparé volontairement : Anthropic n'expose pas d'API d'embeddings. Un même
 * tenant peut donc converser avec Claude tout en indexant sa base de
 * connaissances via OpenAI — ce qui serait impossible avec une interface unique.
 */
interface EmbeddingProvider
{
    public function name(): AiProvider;

    /**
     * Vectorise un lot de textes. L'ordre de sortie DOIT correspondre à
     * l'ordre d'entrée : les vecteurs sont associés positionnellement aux
     * fragments de document.
     *
     * @param  array<int, string>  $texts
     *
     * @throws AiProviderException
     */
    public function embed(array $texts): EmbeddingResult;

    /**
     * Dimension produite.
     *
     * Doit correspondre à `ai.embeddings.dimensions`, sinon l'insertion
     * pgvector échouera : la colonne a une taille fixe.
     */
    public function dimensions(): int;

    /** Taille de lot maximale acceptée par l'API en un seul appel. */
    public function maxBatchSize(): int;
}
