<?php

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],

        // Invitations : même table, mais durée de vie bien plus longue.
        //
        // 60 minutes conviennent à une réinitialisation demandée à l'instant ;
        // une invitation, elle, attend qu'un client ouvre sa boîte, parfois le
        // lendemain. Expirée, elle produirait un compte définitivement
        // inaccessible — c'est exactement ce que ce flux doit empêcher.
        //
        // Le throttle est levé : c'est NONALIX qui déclenche l'envoi, pas un
        // visiteur anonyme, et il faut pouvoir renvoyer une invitation perdue.
        'invitations' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60 * 24 * 7,
            'throttle' => 0,
        ],
    ],

    // Délai avant qu'une action sensible (changement de 2FA, régénération de
    // jeton API, suppression de compte) ne redemande le mot de passe.
    'password_timeout' => (int) env('AUTH_PASSWORD_TIMEOUT', 10800),
];
