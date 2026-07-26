<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WebhookEventStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purge du journal brut des webhooks.
 *
 * Cette table grossit vite : chaque message et chaque statut y laisse une
 * ligne contenant le payload complet. Passé la fenêtre de rétention, elle n'a
 * plus d'utilité — l'information utile est déjà dans `messages`.
 */
class PurgeProcessedWebhookEvents extends Command
{
    protected $signature = 'nonalix:purge-webhook-events {--days= : Rétention en jours}';

    protected $description = 'Supprime les webhooks traités au-delà de la rétention configurée.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('nonalix.retention.webhook_events_days', 30));

        $cutoff  = now()->subDays($days);
        $deleted = 0;

        // Suppression par lots : un DELETE massif prendrait un verrou long sur
        // une table sollicitée en permanence par les webhooks entrants.
        do {
            $batch = DB::table('webhook_events')
                ->whereIn('status', [
                    WebhookEventStatus::Processed->value,
                    WebhookEventStatus::Ignored->value,
                ])
                ->where('received_at', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("{$deleted} événement(s) supprimé(s) (rétention : {$days} jours).");

        return self::SUCCESS;
    }
}
