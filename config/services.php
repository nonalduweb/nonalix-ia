<?php

return [

    /*
    | Services tiers non couverts par config/ai.php et config/whatsapp.php.
    | Aucun secret en dur : uniquement des lectures d'environnement.
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
    ],

    'slack' => [
        // Utilisé par Horizon et le canal d'incidents pour alerter l'équipe.
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
];
