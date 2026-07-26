<?php

use Illuminate\Support\Str;

return [

    'driver'   => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,

    // Les sessions contiennent l'identité et le contexte de tenant :
    // chiffrées au repos, sans exception.
    'encrypt'  => (bool) env('SESSION_ENCRYPT', true),

    'files'      => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'default'),
    'table'      => env('SESSION_TABLE', 'sessions'),
    'store'      => env('SESSION_STORE'),
    'lottery'    => [2, 100],

    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'nonalix'), '_').'_session'),

    'path' => '/',

    // Point initial obligatoire : app.* et admin.* partagent la session,
    // ce qui permet à un super-admin de basculer sans se réauthentifier.
    'domain' => env('SESSION_DOMAIN'),

    'secure'    => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,

    // `lax` et non `none` : aucun contexte cross-site légitime, et cela
    // protège des CSRF sur les requêtes de navigation.
    'same_site' => 'lax',

    'partitioned' => false,
];
