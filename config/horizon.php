<?php

use Illuminate\Support\Str;

return [

    'domain' => env('HORIZON_DOMAIN'),
    'path'   => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'nonalix'), '_').'_horizon:'),

    // L'accès est verrouillé par la Gate `viewHorizon` (AuthServiceProvider) :
    // super-admins NONALIX uniquement.
    'middleware' => ['web', 'auth', 'super-admin'],

    'waits' => [
        'redis:webhooks'  => 30,
        'redis:whatsapp'  => 30,
        'redis:ai'        => 90,
        'redis:knowledge' => 600,
        'redis:default'   => 60,
    ],

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,   // 7 jours
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 256,

    'defaults' => [
        // Files critiques : un message WhatsApp qui attend, c'est un client
        // qui attend. Priorité maximale et parallélisme élevé.
        'realtime' => [
            'connection'   => 'redis',
            'queue'        => ['webhooks', 'whatsapp'],
            'balance'      => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'tries'        => 5,
            'timeout'      => 60,
            'nice'         => 0,
        ],

        // Génération IA : plus lente, potentiellement coûteuse, moins de
        // parallélisme pour ne pas saturer les quotas fournisseurs.
        'ai' => [
            'connection'   => 'redis',
            'queue'        => ['ai'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 6,
            'tries'        => 3,
            'timeout'      => 120,
            'nice'         => 0,
        ],

        // Ingestion documentaire : long, non urgent, ne doit jamais bloquer
        // le reste. Priorité basse (nice).
        'background' => [
            'connection'   => 'redis',
            'queue'        => ['knowledge', 'default'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'tries'        => 3,
            'timeout'      => 600,
            'nice'         => 5,
        ],
    ],

    'environments' => [
        'production' => [
            'realtime'   => ['maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 12)],
            'ai'         => ['maxProcesses' => 6],
            'background' => ['maxProcesses' => 4],
        ],

        'local' => [
            'realtime'   => ['maxProcesses' => 3, 'minProcesses' => 1],
            'ai'         => ['maxProcesses' => 2, 'minProcesses' => 1],
            'background' => ['maxProcesses' => 2, 'minProcesses' => 1],
        ],
    ],
];
