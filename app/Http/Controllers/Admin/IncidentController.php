<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Incident;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Incidents', [
            'incidents' => Incident::query()
                ->with('tenant:id,name', 'resolver:id,name')
                ->when($request->string('level')->toString() !== '', fn ($q) => $q->where('level', $request->string('level')))
                ->when($request->string('source')->toString() !== '', fn ($q) => $q->where('source', $request->string('source')))
                ->when(! $request->boolean('resolved'), fn ($q) => $q->whereNull('resolved_at'))
                ->orderByDesc('last_seen_at')
                ->paginate(50)
                ->withQueryString(),
            'filters' => $request->only(['level', 'source', 'resolved']),
        ]);
    }

    public function resolve(Request $request, Incident $incident): RedirectResponse
    {
        $incident->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ])->save();

        $this->audit->log('platform.incident_resolved', $incident);

        // L'incident se rouvrira automatiquement à la prochaine occurrence
        // (voir Incident::record) : marquer résolu n'efface pas le problème.
        return back()->with('success', 'Incident marqué comme résolu.');
    }
}
