<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AI\ChatProvider;
use App\Contracts\AI\EmbeddingProvider;
use App\Enums\AiProvider;
use App\Exceptions\AiProviderException;
use App\Models\Agent;
use App\Services\AI\Providers\AnthropicChatProvider;
use App\Services\AI\Providers\GeminiChatProvider;
use App\Services\AI\Providers\GeminiEmbeddingProvider;
use App\Services\AI\Providers\OpenAiChatProvider;
use App\Services\AI\Providers\OpenAiEmbeddingProvider;
use InvalidArgumentException;

/**
 * Fabrique les implémentations de fournisseurs IA.
 *
 * C'est le seul endroit de l'application qui connaît les classes concrètes.
 * Ajouter un fournisseur = une implémentation + une entrée dans le `match`
 * de cette classe. Aucun service métier n'est impacté.
 */
class AiProviderManager
{
    /** @var array<string, ChatProvider> */
    private array $chatCache = [];

    /**
     * Fournisseur de chat pour un agent donné.
     *
     * La clé du tenant, si elle existe, prime sur celle de la plateforme :
     * un client qui apporte sa propre clé consomme son propre quota et paie
     * directement son fournisseur.
     */
    public function chatFor(Agent $agent): ChatProvider
    {
        return $this->chat($agent->provider, $agent->api_key);
    }

    public function chat(AiProvider|string|null $provider = null, ?string $apiKeyOverride = null): ChatProvider
    {
        $provider = $this->normalize($provider ?? config('ai.default'));

        // Une clé spécifique au tenant ne doit jamais être mise en cache :
        // l'instance suivante servirait une autre entreprise avec sa clé.
        if ($apiKeyOverride !== null) {
            return $this->makeChatProvider($provider, $apiKeyOverride);
        }

        return $this->chatCache[$provider->value] ??= $this->makeChatProvider($provider, null);
    }

    /**
     * Fournisseur de repli.
     *
     * Sollicité quand le fournisseur principal échoue de façon définitive.
     * Retourne `null` si aucun repli n'est configuré ou si le repli est le
     * fournisseur qui vient déjà d'échouer.
     */
    public function fallbackChat(AiProvider $failed): ?ChatProvider
    {
        $fallback = config('ai.fallback');

        if ($fallback === null || $fallback === '' || $fallback === $failed->value) {
            return null;
        }

        try {
            return $this->chat($fallback);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fournisseur d'embeddings.
     *
     * Le MODÈLE et les DIMENSIONS restent pilotés par la plateforme : tous les
     * vecteurs doivent vivre dans le même espace, sinon les scores de
     * similarité deviennent incomparables entre documents.
     *
     * La CLÉ, elle, peut venir du client. Deux comptes OpenAI appelant le même
     * modèle produisent des vecteurs identiques : la clé n'influe en rien sur
     * l'espace vectoriel, seulement sur qui paie. Les lier était une confusion
     * — et elle empêchait un client ayant fourni sa propre clé d'indexer le
     * moindre document, alors que le site le lui promet explicitement.
     *
     * @param  string|null  $apiKeyOverride  clé du client, si elle porte sur
     *                                       le même fournisseur que les embeddings
     */
    public function embeddings(?string $apiKeyOverride = null): EmbeddingProvider
    {
        $provider   = $this->normalize(config('ai.embeddings.provider'));
        $model      = (string) config('ai.embeddings.model');
        $dimensions = (int) config('ai.embeddings.dimensions');

        $key = $apiKeyOverride ?? $this->requireApiKey($provider);

        return match ($provider) {
            AiProvider::OpenAI => new OpenAiEmbeddingProvider(
                $key, $this->baseUrl($provider), $model, $dimensions,
            ),
            AiProvider::Gemini => new GeminiEmbeddingProvider(
                $key, $this->baseUrl($provider), $model, $dimensions,
            ),
            AiProvider::Anthropic => throw new InvalidArgumentException(
                'Anthropic ne fournit pas d\'API d\'embeddings. Choisir OpenAI ou Gemini '
                .'pour AI_EMBEDDING_PROVIDER.'
            ),
        };
    }

    private function makeChatProvider(AiProvider $provider, ?string $apiKeyOverride): ChatProvider
    {
        $apiKey = $apiKeyOverride ?? $this->requireApiKey($provider);

        return match ($provider) {
            AiProvider::OpenAI => new OpenAiChatProvider(
                $apiKey,
                $this->baseUrl($provider),
                config('ai.providers.openai.organization'),
            ),
            AiProvider::Anthropic => new AnthropicChatProvider(
                $apiKey,
                $this->baseUrl($provider),
                (string) config('ai.providers.anthropic.version'),
            ),
            AiProvider::Gemini => new GeminiChatProvider(
                $apiKey,
                $this->baseUrl($provider),
            ),
        };
    }

    private function normalize(AiProvider|string $provider): AiProvider
    {
        if ($provider instanceof AiProvider) {
            return $provider;
        }

        return AiProvider::tryFrom($provider)
            ?? throw new InvalidArgumentException("Fournisseur IA inconnu : « {$provider} ».");
    }

    private function requireApiKey(AiProvider $provider): string
    {
        $key = config("ai.providers.{$provider->value}.api_key");

        if (! is_string($key) || $key === '') {
            // Message destiné au CLIENT, qui n'a aucun accès au serveur :
            // l'envoyer renseigner une variable d'environnement le laissait
            // sans recours. La clé de son agent est, elle, à sa portée.
            throw AiProviderException::permanent(
                sprintf(
                    'Aucune clé %s n\'est disponible. Renseignez la vôtre dans '
                    .'Configuration → Agent IA, ou contactez %s.',
                    $provider->label(),
                    config('nonalix.support_email'),
                ),
                $provider->value,
            );
        }

        return $key;
    }

    private function baseUrl(AiProvider $provider): string
    {
        return (string) config("ai.providers.{$provider->value}.base_url");
    }
}
