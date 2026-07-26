<?php

return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host'    => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port'    => (int) env('REVERB_SERVER_PORT', 8080),
            'path'    => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => 10_000,
            'scaling' => [
                // Mise à l'échelle horizontale via Redis pub/sub : plusieurs
                // instances Reverb diffusent aux mêmes abonnés.
                'enabled'  => (bool) env('REVERB_SCALING_ENABLED', false),
                'channel'  => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server'   => [
                    'url'      => env('REDIS_URL'),
                    'host'     => env('REDIS_HOST', '127.0.0.1'),
                    'port'     => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                ],
            ],
            'pulse_ingest_interval' => 15,
            'telescope_ingest_interval' => 15,
        ],
    ],

    'apps' => [
        'provider' => 'config',
        'apps' => [
            [
                'key'             => env('REVERB_APP_KEY'),
                'secret'          => env('REVERB_APP_SECRET'),
                'app_id'          => env('REVERB_APP_ID'),
                'options' => [
                    'host'   => env('REVERB_HOST'),
                    'port'   => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                // Seuls les domaines de la plateforme peuvent ouvrir un socket.
                'allowed_origins' => array_filter([
                    env('NONALIX_DOMAIN_APP'),
                    env('NONALIX_DOMAIN_ADMIN'),
                ]),
                'ping_interval'   => 60,
                'activity_timeout' => 30,
                'max_message_size' => 10_000,
            ],
        ],
    ],
];
