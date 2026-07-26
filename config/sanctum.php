<?php

use Laravel\Sanctum\Sanctum;

return [

    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', implode(',', array_filter([
        env('NONALIX_DOMAIN_APP'),
        env('NONALIX_DOMAIN_ADMIN'),
    ])))),

    // Le guard `web` sert l'interface Inertia ; l'API publique utilise
    // exclusivement des jetons porteurs.
    'guard' => ['web'],

    // Les jetons d'API n'expirent pas par défaut mais sont révocables depuis
    // le dashboard. Une expiration peut être imposée par tenant (Phase 2).
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION'),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'nlx_'),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
