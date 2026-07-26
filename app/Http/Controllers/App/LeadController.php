<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Lead::class), 403);

        return Inertia::render('Leads/Index', [
            'leads' => Lead::query()
                ->with(['contact:id,name,profile_name,wa_id', 'assignedUser:id,name'])
                ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_user_id', $request->user()->id))
                ->orderByDesc('score')
                ->orderByDesc('created_at')
                ->paginate(40)
                ->withQueryString(),
            'filters'  => $request->only(['status', 'mine']),
            'statuses' => array_map(
                static fn (LeadStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                LeadStatus::cases(),
            ),
            'operators' => User::query()
                ->ofTenant((string) $request->user()->tenant_id)
                ->select('id', 'name')
                ->get(),
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless($request->user()->can('view', $lead), 403);

        return Inertia::render('Leads/Show', [
            'lead' => $lead->load(['contact', 'conversation', 'assignedUser:id,name']),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($request->user()->can('update', $lead), 403);

        $validated = $request->validate([
            'status'           => ['required', Rule::enum(LeadStatus::class)],
            'score'            => ['nullable', 'integer', 'min:0', 'max:100'],
            'assigned_user_id' => ['nullable', 'uuid'],
            'lost_reason'      => ['nullable', 'string', 'max:160'],
            'next_action_at'   => ['nullable', 'date'],
        ]);

        // Le commercial cible doit appartenir au tenant : un identifiant forgé
        // attribuerait sinon le prospect à un utilisateur d'une autre entreprise.
        if (! empty($validated['assigned_user_id'])) {
            abort_unless(
                User::query()->ofTenant((string) $lead->tenant_id)->whereKey($validated['assigned_user_id'])->exists(),
                422,
            );
        }

        $lead->fill($validated);

        // Une qualification humaine écrase celle produite par l'IA : elle est
        // plus fiable, et la distinction doit rester visible.
        if ($lead->isDirty('status') && $validated['status'] === LeadStatus::Qualified->value) {
            $lead->qualified_at = now();
            $lead->qualified_by = 'user';
        }

        $lead->save();

        $this->audit->logUpdate('lead.updated', $lead);

        return back()->with('success', 'Prospect mis à jour.');
    }
}
