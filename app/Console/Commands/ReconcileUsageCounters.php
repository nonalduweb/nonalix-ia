<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\QuotaService;
use Illuminate\Console\Command;

class ReconcileUsageCounters extends Command
{
    protected $signature = 'nonalix:reconcile-usage {--period= : Période AAAA-MM (défaut : mois courant)}';

    protected $description = 'Recopie les compteurs de consommation Redis vers la base.';

    public function handle(QuotaService $quotas): int
    {
        $period = $this->option('period');

        $count = $quotas->reconcile(is_string($period) ? $period : null);

        $this->info("{$count} compteur(s) réconcilié(s).");

        return self::SUCCESS;
    }
}
