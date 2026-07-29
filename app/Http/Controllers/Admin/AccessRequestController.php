<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AccessCode;
use App\Models\AccessRequest;
use App\Models\Plan;
use App\Notifications\AccessCodeGranted;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Traitement des demandes d'accès déposées depuis le site commercial.
 *
 * Approuver génère le code ET l'envoie au prospect : séparer les deux gestes
 * laisserait des codes émis que personne ne reçoit, ce qui s'est déjà produit
 * avec les comptes créés à la main.
 */
class AccessRequestController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/AccessRequests', [
            'requests' => AccessRequest::query()
                ->with(['plan:id,name', 'accessCode:id,code', 'reviewer:id,name'])
                // Les demandes en attente d'abord : c'est la file de travail.
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->get()
                ->map(fn (AccessRequest $r) => [
                    'id'          => $r->id,
                    'company'     => $r->company,
                    'contactName' => $r->contact_name,
                    'email'       => $r->email,
                    'phone'       => $r->phone,
                    'plan'        => $r->plan?->name,
                    'planId'      => $r->plan_id,
                    'message'     => $r->message,
                    'status'      => $r->status,
                    'code'        => $r->accessCode?->code,
                    'reviewNote'  => $r->review_note,
                    'reviewer'    => $r->reviewer?->name,
                    'createdAt'   => $r->created_at?->toIso8601String(),
                ]),

            'plans' => Plan::query()->where('is_active', true)->orderBy('position')
                ->get(['id', 'name', 'slug']),

            'pendingCount' => AccessRequest::query()->pending()->count(),
        ]);
    }

    /**
     * Approuve : génère un code dédié et le transmet au prospect.
     */
    public function approve(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        abort_unless($accessRequest->isPending(), 409, 'Cette demande est déjà traitée.');

        $validated = $request->validate([
            'plan_id'    => ['required', 'uuid', 'exists:plans,id'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'expires_in' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $code = DB::transaction(function () use ($accessRequest, $validated, $request) {
            $code = AccessCode::create([
                'code'       => AccessCode::generateCode(),
                'plan_id'    => $validated['plan_id'],
                // Rattache le code à la demande : on retrouve d'où vient
                // chaque entreprise créée.
                'label'      => 'Demande — '.$accessRequest->company,
                'max_uses'   => 1,
                'trial_days' => $validated['trial_days'],
                'expires_at' => isset($validated['expires_in'])
                    ? now()->addDays($validated['expires_in'])
                    : null,
                'created_by' => $request->user()->id,
            ]);

            $accessRequest->update([
                'status'         => AccessRequest::APPROVED,
                'access_code_id' => $code->id,
                'reviewed_by'    => $request->user()->id,
                'reviewed_at'    => now(),
            ]);

            return $code;
        });

        $this->audit->log('platform.access_request_approved', $accessRequest, [
            'after' => ['code' => $code->code, 'email' => $accessRequest->email],
        ]);

        // Hors transaction : un envoi qui échoue ne doit pas annuler
        // l'approbation. Le code reste visible dans l'administration et
        // renvoyable.
        $accessRequest->notify(new AccessCodeGranted($code->load('plan'), $accessRequest->company));

        return back()->with('success', "Code {$code->code} envoyé à {$accessRequest->email}.");
    }

    public function reject(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        abort_unless($accessRequest->isPending(), 409, 'Cette demande est déjà traitée.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $accessRequest->update([
            'status'      => AccessRequest::REJECTED,
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log('platform.access_request_rejected', $accessRequest, [
            'after' => ['email' => $accessRequest->email],
        ]);

        // Aucun e-mail : un refus automatique serait brutal et sans recours.
        // La reprise de contact reste un geste commercial, pas une mécanique.
        return back()->with('success', 'Demande refusée.');
    }

    /** Renvoie le code d'une demande déjà approuvée, si l'e-mail s'est perdu. */
    public function resend(AccessRequest $accessRequest): RedirectResponse
    {
        abort_unless($accessRequest->access_code_id !== null, 404);

        $accessRequest->notify(
            new AccessCodeGranted($accessRequest->accessCode->load('plan'), $accessRequest->company),
        );

        $this->audit->log('platform.access_code_resent', $accessRequest);

        return back()->with('success', "Code renvoyé à {$accessRequest->email}.");
    }
}
