<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => false,
    ],

    'channels' => [

        'stack' => [
            'driver'   => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'daily,stderr')),
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver'    => 'daily',
            'path'      => storage_path('logs/nonalix.log'),
            'level'     => env('LOG_LEVEL', 'debug'),
            'days'      => (int) env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        'stderr' => [
            'driver'    => 'monolog',
            'level'     => env('LOG_LEVEL', 'debug'),
            'handler'   => StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'with'      => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        // Canal dédié aux échanges WhatsApp : conservé plus longtemps, il sert
        // à arbitrer les litiges de livraison avec Meta.
        'whatsapp' => [
            'driver'    => 'daily',
            'path'      => storage_path('logs/whatsapp.log'),
            'level'     => 'info',
            'days'      => 30,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        // Canal dédié aux appels IA : coûts, latences, erreurs fournisseurs.
        'ai' => [
            'driver'    => 'daily',
            'path'      => storage_path('logs/ai.log'),
            'level'     => 'info',
            'days'      => 30,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/emergency.log'),
        ],
    ],
];
