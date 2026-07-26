<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Data\AI\ChatRequest;
use App\Data\AI\ChatResponse;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderException;

/**
 * Contrat de génération conversationnelle.
 *
 * C'est LA frontière qui rend les fournisseurs interchangeables : aucun code
 * métier n'importe une classe OpenAI, Anthropic ou Gemini. Ajouter un
 * fournisseur consiste à écrire une implémentation et à l'enregistrer dans
 * AiProviderManager — rien d'autre ne change.
 */
interface ChatProvider
{
    public function name(): AiProvider;

    /**
     * Produit une réponse. L'implémentation doit :
     *   - traduire ChatRequest vers son format natif et retour ;
     *   - réessayer les erreurs transitoires (429, 5xx, réseau) ;
     *   - renseigner usage, latence et coût estimé ;
     *   - lever AiProviderException pour toute erreur définitive.
     *
     * @throws AiProviderException
     */
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * Le fournisseur gère-t-il l'appel d'outils ?
     *
     * Un fournisseur qui répond `false` reçoit des requêtes sans outils :
     * l'agent fonctionne en mode dégradé plutôt que d'échouer.
     */
    public function supportsTools(): bool;

    /** Modèle utilisé si l'agent n'en précise aucun. */
    public function defaultModel(): string;
}
