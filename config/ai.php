<?php

use App\Enums\AiProvider;

return [

    /*
    |---------------------------------------------------------------------------
    | Fournisseur par défaut
    |---------------------------------------------------------------------------
    | Utilisé quand un tenant n'a pas fait de choix explicite. La configuration
    | de l'agent (table `agents`) prime toujours sur cette valeur.
    */

    'default'  => env('AI_DEFAULT_PROVIDER', AiProvider::OpenAI->value),

    /*
    | Fournisseur de repli, sollicité si le fournisseur principal échoue après
    | épuisement des tentatives. `null` désactive le repli.
    */
    'fallback' => env('AI_FALLBACK_PROVIDER'),

    /*
    |---------------------------------------------------------------------------
    | Fournisseurs
    |---------------------------------------------------------------------------
    | Les clés proviennent exclusivement de l'environnement. Un tenant peut
    | fournir les siennes : elles sont stockées chiffrées et surchargent
    | ces valeurs au moment de la résolution du driver.
    */

    'providers' => [

        AiProvider::OpenAI->value => [
            'api_key'      => env('OPENAI_API_KEY'),
            'base_url'     => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'default_model' => 'gpt-4.1-mini',
            'supports_tools' => true,
        ],

        AiProvider::Anthropic->value => [
            'api_key'      => env('ANTHROPIC_API_KEY'),
            'base_url'     => rtrim(env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'), '/'),
            'version'      => env('ANTHROPIC_VERSION', '2023-06-01'),
            'default_model' => 'claude-sonnet-5',
            'supports_tools' => true,
        ],

        AiProvider::Gemini->value => [
            'api_key'      => env('GEMINI_API_KEY'),
            'base_url'     => rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
            'default_model' => 'gemini-2.5-flash',
            'supports_tools' => true,
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Embeddings
    |---------------------------------------------------------------------------
    | La dimension est FIGÉE au niveau de la plateforme : la colonne pgvector
    | est déclarée avec cette taille. La modifier impose une migration ET un
    | réindexage complet de tous les documents de tous les tenants.
    */

    'embeddings' => [
        'provider'   => env('AI_EMBEDDING_PROVIDER', AiProvider::OpenAI->value),
        'model'      => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
    ],

    /*
    |---------------------------------------------------------------------------
    | Comportement de l'agent
    |---------------------------------------------------------------------------
    */

    'agent' => [
        'max_tool_iterations' => 4,   // garde-fou anti-boucle infinie
        'default_temperature' => 0.30,
        'default_max_tokens'  => 1024,
        'memory_window'       => 12,  // messages conservés en contexte
        'rag_top_k'           => 5,
        'rag_min_score'       => 0.75,
        'lock_seconds'        => 60,  // verrou par conversation
    ],

    /*
    |---------------------------------------------------------------------------
    | Client HTTP
    |---------------------------------------------------------------------------
    */

    'http' => [
        'timeout'      => (int) env('AI_HTTP_TIMEOUT', 60),
        'connect_timeout' => 10,
        'max_retries'  => (int) env('AI_MAX_RETRIES', 3),
        'retry_base_ms' => 500,   // backoff exponentiel avec jitter
    ],

    /*
    |---------------------------------------------------------------------------
    | Tarifs (micro-centimes d'euro par million de tokens)
    |---------------------------------------------------------------------------
    | Sert au suivi de consommation. Entiers uniquement : jamais de flottant
    | pour de la monnaie. À réviser quand les grilles fournisseurs changent.
    */

    'pricing' => [
        'gpt-4.1-mini'           => ['input' => 40_000,  'output' => 160_000],
        'gpt-4.1'                => ['input' => 200_000, 'output' => 800_000],
        'text-embedding-3-small' => ['input' => 2_000,   'output' => 0],
        'claude-sonnet-5'        => ['input' => 300_000, 'output' => 1_500_000],
        'claude-haiku-4-5'       => ['input' => 80_000,  'output' => 400_000],
        'gemini-2.5-flash'       => ['input' => 30_000,  'output' => 250_000],
    ],
];
