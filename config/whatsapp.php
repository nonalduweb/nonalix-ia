<?php

return [

    /*
    |---------------------------------------------------------------------------
    | API Meta Cloud
    |---------------------------------------------------------------------------
    | Aucun identifiant ici : les jetons et secrets applicatifs appartiennent
    | au tenant et sont stockés chiffrés dans `whatsapp_accounts`.
    */

    'base_url'    => rtrim(env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'), '/'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),

    'http' => [
        'timeout'         => (int) env('WHATSAPP_TIMEOUT', 15),
        'connect_timeout' => 5,
        'max_retries'     => (int) env('WHATSAPP_MAX_RETRIES', 3),
        'retry_base_ms'   => 400,
    ],

    /*
    |---------------------------------------------------------------------------
    | Fenêtre de service
    |---------------------------------------------------------------------------
    | Règle Meta : hors des 24 h suivant le dernier message du contact, seuls
    | les templates approuvés peuvent être envoyés. Ce n'est pas un réglage
    | de confort — le contourner dégrade la qualité du numéro.
    */

    'service_window_hours' => (int) env('WHATSAPP_SERVICE_WINDOW_HOURS', 24),

    /*
    |---------------------------------------------------------------------------
    | Débit sortant
    |---------------------------------------------------------------------------
    | Limitation appliquée par `phone_number_id` via un seau Redis, en deçà des
    | plafonds Meta pour garder de la marge.
    */

    'rate_limit' => [
        'messages_per_second' => (int) env('WHATSAPP_MPS', 20),
        'burst'               => 40,
    ],

    /*
    |---------------------------------------------------------------------------
    | Webhooks
    |---------------------------------------------------------------------------
    */

    'webhooks' => [
        'signature_header'  => 'X-Hub-Signature-256',
        'signature_prefix'  => 'sha256=',
        // Rejette les signatures valides mais trop anciennes (anti-rejeu).
        'max_age_seconds'   => 300,
        // En développement seulement : accepte les payloads non signés.
        // Toute valeur autre que false en production est une faille.
        'allow_unsigned'    => (bool) env('WHATSAPP_ALLOW_UNSIGNED', false),
    ],

    /*
    |---------------------------------------------------------------------------
    | Consentement
    |---------------------------------------------------------------------------
    | Mots-clés traités AVANT tout appel à l'IA. La désinscription est
    | immédiate, inconditionnelle et tracée dans `consent_logs`.
    */

    'consent' => [
        'opt_out_keywords' => ['stop', 'stop pub', 'desabonner', 'désabonner', 'unsubscribe'],
        'opt_in_keywords'  => ['start', 'demarrer', 'démarrer', 'subscribe'],
        'opt_out_reply'    => "Vous ne recevrez plus de messages. Répondez START pour réactiver.",
        'opt_in_reply'     => "Vous êtes de nouveau abonné. Répondez STOP à tout moment pour vous désinscrire.",
    ],

    /*
    |---------------------------------------------------------------------------
    | Types de messages entrants pris en charge en Phase 1
    |---------------------------------------------------------------------------
    | Les autres types sont persistés en `unsupported` et déclenchent une
    | réponse de repli — jamais un silence.
    */

    'supported_inbound_types' => ['text', 'button', 'interactive'],

    'media' => [
        'disk'              => env('WHATSAPP_MEDIA_DISK', 'local'),
        'download_inbound'  => true,
        'max_bytes'         => 16 * 1024 * 1024,
    ],
];
