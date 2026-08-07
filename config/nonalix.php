<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Domaines des quatre espaces
    |---------------------------------------------------------------------------
    | Une seule application sert les quatre sous-domaines. Ces valeurs sont
    | consommées par bootstrap/app.php pour construire les groupes de routes,
    | ce qui rend les environnements interchangeables sans toucher au code.
    */

    'domains' => [
        'marketing' => env('NONALIX_DOMAIN_MARKETING', 'nonalixia.test'),
        'app'       => env('NONALIX_DOMAIN_APP', 'app.nonalixia.test'),
        'admin'     => env('NONALIX_DOMAIN_ADMIN', 'admin.nonalixia.test'),
        'api'       => env('NONALIX_DOMAIN_API', 'api.nonalixia.test'),
    ],

    'support_email' => env('NONALIX_SUPPORT_EMAIL', 'support@nonalixia.com'),

    /*
    |---------------------------------------------------------------------------
    | Multi-tenant
    |---------------------------------------------------------------------------
    */

    'tenancy' => [
        // Modèles centraux, volontairement exemptés du scope de tenant.
        // Le test d'architecture s'appuie sur cette liste : y ajouter un modèle
        // est une décision explicite, jamais un oubli.
        'central_models' => [
            App\Models\Tenant::class,
            App\Models\Plan::class,
        ],

        // Les tenants dans ces états ne peuvent plus utiliser la plateforme.
        'blocked_statuses' => ['suspended', 'closed'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Quotas
    |---------------------------------------------------------------------------
    */

    'quotas' => [
        'enforce'         => (bool) env('QUOTA_ENFORCEMENT', true),
        'alert_threshold' => (int) env('QUOTA_ALERT_THRESHOLD', 80),

        // Métriques suivies en Phase 1.
        'metrics' => [
            'messages_sent',
            'messages_received',
            'ai_requests',
            'ai_input_tokens',
            'ai_output_tokens',
            'documents_stored',
            'storage_bytes',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Sécurité
    |---------------------------------------------------------------------------
    */

    'security' => [
        // Rôles pour lesquels la 2FA est obligatoire avant tout accès.
        'two_factor_required_roles' => array_filter(explode(
            ',', (string) env('TWO_FACTOR_REQUIRED_ROLES', 'super-admin,owner,admin')
        )),

        // Durée maximale d'une session d'impersonation par le support NONALIX.
        'impersonation_ttl_minutes' => 60,
    ],

    /*
    |---------------------------------------------------------------------------
    | Rétention des données
    |---------------------------------------------------------------------------
    */

    'retention' => [
        'webhook_events_days' => 30,
        'media_days'          => 90,
        'ai_usage_logs_days'  => 395,
        'audit_logs_days'     => 730,
    ],

    /*
    |---------------------------------------------------------------------------
    | Base de connaissances
    |---------------------------------------------------------------------------
    */

    'knowledge' => [
        'chunk_size'          => 900,   // caractères visés par fragment
        'chunk_overlap'       => 150,   // chevauchement, préserve le contexte aux coutures
        'max_document_bytes'  => 32 * 1024 * 1024,
        'allowed_mime_types'  => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/plain',
            'text/markdown',
        ],
        'embedding_batch_size' => 64,
    ],

    /*
    |---------------------------------------------------------------------------
    | Valeurs par défaut d'une nouvelle entreprise
    |---------------------------------------------------------------------------
    |
    | Le marché principal est l'Afrique de l'Ouest : proposer Europe/Paris et
    | l'euro obligeait chaque client à corriger deux champs, et un fuseau
    | erroné fait répondre l'agent « nous sommes fermés » aux mauvaises heures.
    |
    | Paramétrable : une implantation sur un autre marché change ces valeurs
    | sans toucher au code.
    */

    'defaults' => [
        'timezone' => env('NONALIX_DEFAULT_TIMEZONE', 'Africa/Abidjan'),
        'currency' => env('NONALIX_DEFAULT_CURRENCY', 'XOF'),
        'country'  => env('NONALIX_DEFAULT_COUNTRY', 'CI'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Devises proposées
    |---------------------------------------------------------------------------
    */

    'currencies' => [
        'XOF' => 'XOF (FCFA)',
        'XAF' => 'XAF (FCFA)',
        'EUR' => 'EUR (€)',
        'MAD' => 'MAD (dirham)',
        'GHS' => 'GHS (cedi)',
        'NGN' => 'NGN (naira)',
        'USD' => 'USD ($)',
        'CHF' => 'CHF',
        'CAD' => 'CAD ($)',
    ],
];
