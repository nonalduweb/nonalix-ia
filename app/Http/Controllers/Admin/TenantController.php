<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\QuotaService;
use App\Services\Tenancy\RoleProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion des entreprises clientes, côté NONALIX.
 *
 * Ces écrans travaillent en transversal, sur le modèle central `Tenant` qui
 * n'est pas cloisonné. Les données MÉTIER d'un client ne sont jamais
 * consultées ici : seulement des agrégats et de l'administratif. Voir le
 * contenu d'une conversation exige une impersonation tracée.
 */
class TenantController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuotaService $quotas,
        private readonly RoleProvisioner $roles,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => Tenant::query()
                ->with('plan:id,name,slug')
                ->withCount('users')
                ->when($request->string('q')->toString() !== '', function ($q) use ($request) {
                    $term = $request->string('q')->toString();
                    $q->where(fn ($b) => $b->where('name', 'ilike', "%{$term}%")
                        ->orWhere('slug', 'ilike', "%{$term}%"));
                })
                ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')))
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'filters'  => $request->only(['q', 'status']),
            'statuses' => array_map(
                static fn (TenantStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                TenantStatus::cases(),
            ),
            // Alimente le sélecteur du formulaire de création : saisir un UUID
            // de plan à la main serait une source d'erreur inutile.
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'name', 'slug', 'price_cents', 'currency']),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant->load('plan', 'subscriptions.plan'),
            'users'  => User::query()->ofTenant($tenant->id)->with('roles:id,name')->get(),
            'usage'  => collect(config('nonalix.quotas.metrics'))
                ->mapWithKeys(fn (string $metric) => [$metric => [
                    'used'  => $this->quotas->current($tenant, $metric),
                    'limit' => $tenant->quotaFor($metric),
                ]])->all(),
            'plans' => Plan::query()->orderBy('position')->get(['id', 'name', 'slug']),
        ]);
    }

    /** Création d'un compte client avec son premier `owner`. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:160'],
            'slug'        => ['required', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug'],
            'plan_id'     => ['required', 'uuid', 'exists:plans,id'],
            'owner_name'  => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'trial_days'  => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $tenant = Tenant::create([
            'name'          => $validated['name'],
            'slug'          => $validated['slug'],
            'plan_id'       => $validated['plan_id'],
            'status'        => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays($validated['trial_days'] ?? 14),
        ]);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $validated['owner_name'],
            'email'     => $validated['owner_email'],
            'password'  => Hash::make(Str::random(48)),
            'status'    => \App\Enums\UserStatus::Invited,
        ]);

        // Les rôles sont cloisonnés par tenant : le provisionnement force le
        // contexte, faute de quoi les rôles seraient créés côté plateforme.
        //
        // Les quatre rôles sont créés d'emblée, et non le seul `owner` :
        // l'entreprise pourra inviter un `admin` ou un `agent` sans qu'un rôle
        // vide soit fabriqué au passage.
        $this->roles->provisionAll($tenant->id);
        $owner->assignRole(\Spatie\Permission\Models\Role::findOrCreate('owner', 'web'));

        $this->audit->log('platform.tenant_created', $tenant, [
            'after' => ['name' => $tenant->name, 'owner' => $owner->email],
        ]);

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Entreprise créée.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:160'],
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ]);

        $tenant->fill($validated)->save();

        $this->audit->logUpdate('platform.tenant_updated', $tenant);

        return back()->with('success', 'Entreprise mise à jour.');
    }

    /** Suspension : l'accès est coupé, les données sont conservées. */
    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            // Motif obligatoire : une suspension sans justification écrite est
            // ingérable côté support et indéfendable côté client.
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $tenant->forceFill([
            'status'            => TenantStatus::Suspended,
            'suspended_at'      => now(),
            'suspension_reason' => $validated['reason'],
            'suspended_by'      => $request->user()->id,
        ])->save();

        $this->audit->log('platform.tenant_suspended', $tenant, context: $validated);

        return back()->with('success', 'Compte suspendu.');
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $tenant->forceFill([
            'status'            => TenantStatus::Active,
            'suspended_at'      => null,
            'suspension_reason' => null,
            'suspended_by'      => null,
        ])->save();

        $this->audit->log('platform.tenant_reactivated', $tenant);

        return back()->with('success', 'Compte réactivé.');
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
        ]);

        $tenant->fill($validated)->save();

        $this->audit->logUpdate('platform.tenant_plan_changed', $tenant);

        return back()->with('success', 'Plan modifié.');
    }

    /** Dérogations de quotas accordées au cas par cas. */
    public function overrideQuotas(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'quota_overrides'   => ['present', 'array'],
            'quota_overrides.*' => ['integer', 'min:0'],
        ]);

        $allowed = config('nonalix.quotas.metrics', []);

        // Seules les métriques connues sont acceptées : une clé libre créerait
        // un quota fantôme que rien ne consommerait jamais.
        $tenant->quota_overrides = array_intersect_key(
            $validated['quota_overrides'],
            array_flip($allowed),
        );
        $tenant->save();

        $this->audit->logUpdate('platform.tenant_quotas_overridden', $tenant);

        return back()->with('success', 'Quotas mis à jour.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->audit->log('platform.tenant_deleted', $tenant, ['before' => ['name' => $tenant->name]]);

        // Soft delete : la purge définitive intervient à J+30 par une commande
        // planifiée, ce qui laisse une fenêtre de rétractation.
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('success', 'Entreprise supprimée.');
    }
}
