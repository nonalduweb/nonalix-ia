<?php

use App\Console\Commands\PurgeExpiredMedia;
use App\Console\Commands\PurgeProcessedWebhookEvents;
use App\Console\Commands\ReconcileUsageCounters;
use Illuminate\Support\Facades\Schedule;

/*
|------------------------------------------------------------------------------
| Tâches planifiées
|------------------------------------------------------------------------------
| Exécutées par le conteneur `scheduler` (php artisan schedule:work).
| `withoutOverlapping` partout : ces commandes peuvent être longues et une
| double exécution fausserait les compteurs.
*/

// Réconciliation des compteurs Redis vers `usage_counters`.
// Fréquence élevée : c'est la source de vérité de la facturation à l'usage.
Schedule::command(ReconcileUsageCounters::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Purge du journal brut des webhooks (rétention configurable).
Schedule::command(PurgeProcessedWebhookEvents::class)
    ->dailyAt('03:15')
    ->withoutOverlapping();

// Purge des médias WhatsApp expirés (stockage + référence en base).
Schedule::command(PurgeExpiredMedia::class)
    ->dailyAt('03:45')
    ->withoutOverlapping();

// Nettoyage des jobs échoués trop anciens (Horizon conserve 7 jours).
Schedule::command('queue:prune-failed', ['--hours' => 168])
    ->daily();

Schedule::command('queue:prune-batches', ['--hours' => 48])
    ->daily();
