<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Plans', [
            'plans'   => Plan::query()->withCount('tenants')->orderBy('position')->get(),
            'metrics' => config('nonalix.quotas.metrics'),
        ]);
    }

    public function show(Plan $plan): Response
    {
        return Inertia::render('Admin/PlanShow', [
            'plan'    => $plan->loadCount('tenants'),
            'metrics' => config('nonalix.quotas.metrics'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->validated($request));

        $this->audit->log('platform.plan_created', $plan);

        return back()->with('success', 'Plan créé.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->fill($this->validated($request, $plan))->save();

        // Les quotas s'appliquent immédiatement à tous les clients du plan :
        // l'audit est indispensable pour retracer un changement de limites.
        $this->audit->logUpdate('platform.plan_updated', $plan);

        return back()->with('success', 'Plan mis à jour.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // La contrainte SQL le refuserait de toute façon (RESTRICT) ; on
        // renvoie un message compréhensible plutôt qu'une erreur de base.
        if ($plan->tenants()->exists()) {
            return back()->withErrors([
                'plan' => 'Ce plan est utilisé par au moins une entreprise et ne peut pas être supprimé.',
            ]);
        }

        $this->audit->log('platform.plan_deleted', $plan);
        $plan->delete();

        return back()->with('success', 'Plan supprimé.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'slug'           => ['required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description'    => ['nullable', 'string', 'max:1000'],
            'price_cents'    => ['required', 'integer', 'min:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'interval'       => ['required', Rule::in(['month', 'year'])],
            'quotas'         => ['present', 'array'],
            'quotas.*'       => ['integer', 'min:0'],
            'features'       => ['present', 'array'],
            'overage_policy' => ['required', Rule::in(['block', 'soft'])],
            'is_active'      => ['boolean'],
            'is_public'      => ['boolean'],
            'position'       => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
    }
}
