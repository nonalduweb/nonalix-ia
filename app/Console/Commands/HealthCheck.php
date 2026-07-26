<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Vérification de santé, utilisée par le HEALTHCHECK Docker.
 *
 * Teste les dépendances dont l'absence rend l'application inutilisable :
 * PostgreSQL, pgvector et Redis. Un conteneur qui répond en HTTP mais dont la
 * base est injoignable ne doit pas être considéré comme sain.
 */
class HealthCheck extends Command
{
    protected $signature = 'nonalix:health';

    protected $description = 'Vérifie l\'accès à PostgreSQL, pgvector et Redis.';

    public function handle(): int
    {
        $checks = [
            'postgresql' => fn () => DB::select('SELECT 1'),
            'pgvector'   => fn () => DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'")
                ?: throw new \RuntimeException('Extension vector absente.'),
            'redis'      => fn () => Redis::ping(),
        ];

        $failed = [];

        foreach ($checks as $name => $check) {
            try {
                $check();
                $this->line("  <fg=green>✓</> {$name}");
            } catch (Throwable $e) {
                $failed[] = $name;
                $this->line("  <fg=red>✗</> {$name} : {$e->getMessage()}");
            }
        }

        if ($failed !== []) {
            $this->error('Services indisponibles : '.implode(', ', $failed));

            return self::FAILURE;
        }

        $this->info('Tous les services répondent.');

        return self::SUCCESS;
    }
}
