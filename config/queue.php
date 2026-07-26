<?php

return [

    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver'        => 'database',
            'connection'    => env('DB_QUEUE_CONNECTION'),
            'table'         => env('DB_QUEUE_TABLE', 'jobs'),
            'queue'         => env('DB_QUEUE', 'default'),
            'retry_after'   => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit'  => false,
        ],

        'redis' => [
            'driver'      => 'redis',
            'connection'  => env('REDIS_QUEUE_CONNECTION', 'queue'),
            'queue'       => env('REDIS_QUEUE', 'default'),
            // Doit rester supérieur au timeout du job le plus long
            // (knowledge: 600 s) pour ne pas relancer un job encore en cours.
            'retry_after'  => (int) env('REDIS_QUEUE_RETRY_AFTER', 660),
            'block_for'    => 5,
            // Les jobs ne sont dispatchés qu'après commit de la transaction :
            // un worker ne peut pas lire une entité qui n'existe pas encore.
            'after_commit' => true,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'job_batches',
    ],

    'failed' => [
        'driver'   => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'failed_jobs',
    ],
];
