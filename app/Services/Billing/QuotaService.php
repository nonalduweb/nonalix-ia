<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\IncidentLevel;
use App\Exceptions\QuotaExceededException;
use App\Models\Incident;
use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Compteurs de consommation et application des quotas.
 *
 * Redis porte le compteur chaud (INCRBY atomique, lecture immédiate), la table
 * `usage_counters` en est la trace durable, réconciliée périodiquement.
 *
 * Choix assumé : en cas d'indisponibilité de Redis, on LAISSE PASSER plutôt
 * que de bloquer. Un incident d'infrastructure ne doit pas couper le service
 * de tous les clients ; le manque à gagner est rattrapé à la réconciliation.
 */
class QuotaService
{
    /** Les compteurs expirent deux mois après la fin de leur période. */
    private const TTL_SECONDS = 62 * 24 * 3600;

    public function key(string $tenantId, string $metric, ?string $period = null): string
    {
        return sprintf('quota:%s:%s:%s', $tenantId, $metric, $period ?? UsageCounter::currentPeriod());
    }

    public function current(Tenant|string $tenant, string $metric): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        try {
            return (int) (Redis::get($this->key($tenantId, $metric)) ?? 0);
        } catch (\Throwable) {
            // Repli sur la valeur consolidée : moins fraîche, mais disponible.
            return (int) UsageCounter::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('metric', $metric)
                ->where('period', UsageCounter::currentPeriod())
                ->value('value');
        }
    }

    /**
     * Incrémente la consommation.
     *
     * Appelé APRÈS le succès de l'action, jamais avant : on ne facture pas une
     * requête qui a échoué.
     */
    public function increment(Tenant|string $tenant, string $metric, int $by = 1): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $key      = $this->key($tenantId, $metric);

        try {
            $value = (int) Redis::incrby($key, $by);

            // Posé à la création uniquement : ré-armer le TTL à chaque
            // incrément ferait glisser indéfiniment la fin de période.
            if ($value === $by) {
                Redis::expire($key, self::TTL_SECONDS);
            }

            $this->alertIfNearLimit($tenant, $metric, $value);

            return $value;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function remaining(Tenant $tenant, string $metric): ?int
    {
        $limit = $tenant->quotaFor($metric);

        return $limit === null ? null : max(0, $limit - $this->current($tenant, $metric));
    }

    public function hasQuota(Tenant $tenant, string $metric, int $needed = 1): bool
    {
        $limit = $tenant->quotaFor($metric);

        // Métrique non plafonnée par le plan.
        if ($limit === null) {
            return true;
        }

        // Plan en dépassement souple : on autorise et on facture.
        if (! ($tenant->plan?->blocksOnOverage() ?? true)) {
            return true;
        }

        return ($this->current($tenant, $metric) + $needed) <= $limit;
    }

    /** @throws QuotaExceededException */
    public function assertWithinQuota(Tenant $tenant, string $metric, int $needed = 1): void
    {
        if ($this->hasQuota($tenant, $metric, $needed)) {
            return;
        }

        $limit   = (int) $tenant->quotaFor($metric);
        $current = $this->current($tenant, $metric);

        Incident::record(
            tenantId: $tenant->id,
            level: IncidentLevel::Warning,
            source: 'quota',
            code: "quota_exceeded.{$metric}",
            title: "Quota « {$metric} » atteint",
            context: ['limit' => $limit, 'current' => $current],
        );

        throw new QuotaExceededException($metric, $limit, $current, $tenant->id);
    }

    /**
     * Recopie les compteurs Redis vers la base.
     *
     * Upsert avec `GREATEST` : la valeur consolidée ne peut que croître.
     * Si Redis a été vidé, une valeur plus basse n'écrase pas l'historique.
     *
     * @return int  nombre de compteurs réconciliés
     */
    public function reconcile(?string $period = null): int
    {
        $period    = $period ?? UsageCounter::currentPeriod();
        $metrics   = config('nonalix.quotas.metrics', []);
        $now       = now();
        $rows      = [];

        Tenant::query()->select('id')->chunkById(200, function ($tenants) use ($metrics, $period, $now, &$rows) {
            foreach ($tenants as $tenant) {
                foreach ($metrics as $metric) {
                    $value = (int) (Redis::get($this->key($tenant->id, $metric, $period)) ?? 0);

                    if ($value === 0) {
                        continue;
                    }

                    $rows[] = [
                        'id'          => (string) Str::uuid7(),
                        'tenant_id'   => $tenant->id,
                        'metric'      => $metric,
                        'period'      => $period,
                        'value'       => $value,
                        'recorded_at' => $now,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
        });

        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('usage_counters')->upsert(
                $batch,
                ['tenant_id', 'metric', 'period'],
                [
                    'value'       => DB::raw('GREATEST(usage_counters.value, excluded.value)'),
                    'recorded_at' => DB::raw('excluded.recorded_at'),
                    'updated_at'  => DB::raw('excluded.updated_at'),
                ],
            );
        }

        return count($rows);
    }

    /** Ouvre un incident au franchissement du seuil d'alerte (80 % par défaut). */
    private function alertIfNearLimit(Tenant|string $tenant, string $metric, int $value): void
    {
        if (! $tenant instanceof Tenant) {
            return;
        }

        $limit = $tenant->quotaFor($metric);

        if ($limit === null || $limit === 0) {
            return;
        }

        $threshold = (int) config('nonalix.quotas.alert_threshold', 80);
        $percent   = (int) floor($value / $limit * 100);

        // Uniquement au franchissement exact, pour ne pas répéter l'alerte à
        // chaque message une fois le seuil dépassé.
        if ($percent === $threshold) {
            Incident::record(
                tenantId: $tenant->id,
                level: IncidentLevel::Info,
                source: 'quota',
                code: "quota_threshold.{$metric}",
                title: "Quota « {$metric} » consommé à {$threshold} %",
                context: ['limit' => $limit, 'current' => $value],
            );
        }
    }
}
