<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TenantStatus;
use App\Models\AiUsageLog;
use App\Models\Incident;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Vue d'ensemble de la plateforme, tous clients confondus. */
class DashboardController
{
    public function __invoke(): Response
    {
        $since = now()->subDays(30);

        return Inertia::render('Admin/Dashboard', [
            'tenants' => [
                'total'     => Tenant::query()->count(),
                'active'    => Tenant::query()->where('status', TenantStatus::Active->value)->count(),
                'trial'     => Tenant::query()->where('status', TenantStatus::Trial->value)->count(),
                'suspended' => Tenant::query()->where('status', TenantStatus::Suspended->value)->count(),
                'new_30d'   => Tenant::query()->where('created_at', '>=', $since)->count(),
            ],

            'users' => [
                'total' => User::query()->whereNotNull('tenant_id')->count(),
                'staff' => User::query()->platformStaff()->count(),
            ],

            // Agrégats bruts, sans cloisonnement : c'est le seul endroit où
            // c'est légitime, et aucun contenu de message n'est lu ici.
            'volume' => [
                'messages_30d' => Message::withoutTenantScope()->where('created_at', '>=', $since)->count(),
                'ai_cost_30d_micros' => (int) AiUsageLog::withoutTenantScope()
                    ->where('created_at', '>=', $since)
                    ->sum('cost_micros'),
            ],

            'incidents' => Incident::query()
                ->unresolved()
                ->with('tenant:id,name')
                ->limit(15)
                ->get(),

            // Les dix clients les plus coûteux en IA : c'est là que la marge
            // se joue, et donc là qu'il faut regarder en premier.
            'topConsumers' => AiUsageLog::withoutTenantScope()
                ->select('tenant_id', DB::raw('SUM(cost_micros) AS cost_micros'), DB::raw('COUNT(*) AS calls'))
                ->where('created_at', '>=', $since)
                ->groupBy('tenant_id')
                ->orderByDesc('cost_micros')
                ->limit(10)
                ->with('tenant:id,name')
                ->get(),
        ]);
    }
}
