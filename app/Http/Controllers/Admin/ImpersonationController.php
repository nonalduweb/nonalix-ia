<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Impersonation de support.
 *
 * Seul moyen légitime pour un membre de NONALIX de voir les données d'un
 * client. Trois garde-fous, non négociables :
 *   - motif obligatoire, enregistré dans le journal d'audit ;
 *   - durée limitée (config `nonalix.security.impersonation_ttl_minutes`) ;
 *   - bandeau permanent côté client (partagé par HandleInertiaRequests).
 *
 * L'alternative — désactiver le scope de tenant pour l'administration —
 * donnerait le même accès sans aucune de ces traces.
 */
class ImpersonationController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'reason'  => ['required', 'string', 'min:10', 'max:500'],
            'user_id' => ['nullable', 'uuid'],
        ]);

        // On emprunte l'identité d'un utilisateur réel du client : ses
        // permissions s'appliquent, ce qui borne ce que le support peut faire.
        $target = User::query()
            ->ofTenant($tenant->id)
            ->when(
                ! empty($validated['user_id']),
                fn ($q) => $q->whereKey($validated['user_id']),
                fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'owner')),
            )
            ->first();

        if ($target === null) {
            return back()->withErrors([
                'user_id' => 'Aucun utilisateur exploitable pour cette entreprise.',
            ]);
        }

        $impersonator = $request->user();

        $this->audit->log('platform.impersonation_started', $target, context: [
            'tenant_id'      => $tenant->id,
            'impersonator_id' => $impersonator->id,
            'reason'         => $validated['reason'],
        ]);

        $ttl = (int) config('nonalix.security.impersonation_ttl_minutes', 60);

        Auth::login($target);

        $request->session()->put([
            'impersonation.original_user_id' => $impersonator->id,
            'impersonation.tenant_name'      => $tenant->name,
            'impersonation.expires_at'       => now()->addMinutes($ttl)->timestamp,
            // La 2FA du super-admin a déjà été validée : ne pas la
            // redemander sous l'identité empruntée.
            'auth.two_factor_verified'       => true,
        ]);

        return redirect()->away(Domain::app());
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull('impersonation.original_user_id');

        if ($originalId === null) {
            return redirect()->route('admin.dashboard');
        }

        $original = User::query()->platformStaff()->find($originalId);

        $request->session()->forget([
            'impersonation.tenant_name',
            'impersonation.expires_at',
        ]);

        if ($original === null) {
            // L'identité d'origine n'existe plus : on coupe tout plutôt que de
            // laisser une session sous une identité empruntée.
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        Auth::login($original);

        $this->audit->log('platform.impersonation_stopped', context: [
            'impersonator_id' => $original->id,
        ]);

        return redirect()->away(Domain::admin());
    }
}
