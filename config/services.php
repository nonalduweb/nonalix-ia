<?php

return [

    /*
    | Services tiers non couverts par config/ai.php et config/whatsapp.php.
    | Aucun secret en dur : uniquement des lectures d'environnement.
    */

    /*
    |---------------------------------------------------------------------------
    | Connexion Google
    |---------------------------------------------------------------------------
    |
    | Identifiants OAuth issus de la console Google Cloud. L'URI de redirection
    | doit y être déclarée à l'identique, sans quoi Google refuse l'échange.
    |
    | Sans GOOGLE_CLIENT_ID, le bouton n'est pas affiché : mieux vaut aucune
    | option qu'un bouton qui mène à une erreur.
    */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

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
