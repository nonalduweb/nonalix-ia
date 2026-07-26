<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AccessCode;
use App\Models\Plan;
use App\Services\Audit\AuditLogger;
use App\Support\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Émission des codes d'accès, côté NONALIX.
 *
 * Un code ouvre la création d'une entreprise sur un pack donné. C'est le seul
 * levier commercial du MVP : il n'y a pas de paiement en ligne, la validation
 * passe donc par l'émission manuelle d'un code.
 */
class AccessCodeController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/AccessCodes', [
            'codes' => AccessCode::query()
                ->with(['plan:id,name,slug', 'creator:id,name'])
                ->withCount('redemptions')
                ->latest()
                ->get()
                ->map(fn (AccessCode $code) => [
                    'id'         => $code->id,
                    'code'       => $code->code,
                    'label'      => $code->label,
                    'plan'       => $code->plan?->name,
                    'trialDays'  => $code->trial_days,
                    'maxUses'    => $code->max_uses,
                    'usedCount'  => $code->used_count,
                    'expiresAt'  => $code->expires_at?->toIso8601String(),
                    'revokedAt'  => $code->revoked_at?->toIso8601String(),
                    'usable'     => $code->isUsable(),
                    'reason'     => $code->unusableReason(),
                    'createdBy'  => $code->creator?->name,
                    'createdAt'  => $code->created_at?->toIso8601String(),
                    // Lien prêt à être transmis au prospect : le code y est
                    // pré-rempli, ce qui évite une recopie fautive.
                    'shareUrl'   => Domain::app('register?code='.$code->code),
                ]),

            // Tous les packs, y compris non publics : un code peut ouvrir une
            // offre négociée qui n'apparaît pas sur le site commercial.
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'name', 'slug', 'price_cents']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id'    => ['required', 'uuid', 'exists:plans,id'],
            'label'      => ['nullable', 'string', 'max:160'],
            // 0 = illimité. Assumé, mais à réserver aux opérations encadrées.
            'max_uses'   => ['required', 'integer', 'min:0', 'max:1000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $created = [];

        for ($i = 0; $i < $validated['quantity']; $i++) {
            $created[] = AccessCode::create([
                'code'       => AccessCode::generateCode(),
                'plan_id'    => $validated['plan_id'],
                'label'      => $validated['label'] ?? null,
                'max_uses'   => $validated['max_uses'],
                'trial_days' => $validated['trial_days'],
                'expires_at' => $validated['expires_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        }

        foreach ($created as $code) {
            $this->audit->log('platform.access_code_created', $code, [
                'after' => [
                    'code'     => $code->code,
                    'plan_id'  => $code->plan_id,
                    'max_uses' => $code->max_uses,
                ],
            ]);
        }

        return back()->with('success', count($created) > 1
            ? count($created).' codes générés.'
            : 'Code généré : '.$created[0]->code);
    }

    /**
     * Révocation plutôt que suppression : le code reste lisible dans le
     * journal et dans les consommations déjà enregistrées.
     */
    public function revoke(Request $request, AccessCode $accessCode): RedirectResponse
    {
        if ($accessCode->isRevoked()) {
            return back()->with('success', 'Ce code était déjà révoqué.');
        }

        $accessCode->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        $this->audit->log('platform.access_code_revoked', $accessCode, [
            'before' => ['code' => $accessCode->code],
        ]);

        return back()->with('success', 'Code révoqué.');
    }
}
