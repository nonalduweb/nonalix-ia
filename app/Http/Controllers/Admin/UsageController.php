<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AiUsageLog;
use App\Models\UsageCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Consommation IA et WhatsApp, par client et par période. */
class UsageController
{
    public function __invoke(Request $request): Response
    {
        $period = $request->string('period')->toString() ?: UsageCounter::currentPeriod();

        return Inertia::render('Admin/Usage', [
            'period' => $period,

            'counters' => UsageCounter::withoutTenantScope()
                ->where('period', $period)
                ->with('tenant:id,name')
                ->orderByDesc('value')
                ->get()
                ->groupBy('tenant_id'),

            // Détail par fournisseur et modèle : sert à arbitrer les choix de
            // modèles par défaut au vu du coût réel constaté.
            'byModel' => AiUsageLog::withoutTenantScope()
                ->select(
                    'provider', 'model',
                    DB::raw('COUNT(*) AS calls'),
                    DB::raw('SUM(input_tokens) AS input_tokens'),
                    DB::raw('SUM(output_tokens) AS output_tokens'),
                    DB::raw('SUM(cost_micros) AS cost_micros'),
                    DB::raw('ROUND(AVG(latency_ms)) AS avg_latency_ms'),
                )
                ->where('created_at', '>=', now()->startOfMonth())
                ->groupBy('provider', 'model')
                ->orderByDesc('cost_micros')
                ->get(),

            'periods' => UsageCounter::withoutTenantScope()
                ->select('period')
                ->distinct()
                ->orderByDesc('period')
                ->limit(24)
                ->pluck('period'),
        ]);
    }
}
