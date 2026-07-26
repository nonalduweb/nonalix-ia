<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\OptInStatus;
use App\Models\ConsentLog;
use App\Models\Contact;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Contact::class), 403);

        return Inertia::render('Contacts/Index', [
            'contacts' => Contact::query()
                ->when($request->string('q')->toString() !== '', fn ($q) => $q->search($request->string('q')->toString()))
                ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('opt_in_status', $request->string('status')))
                ->orderByDesc('last_message_at')
                ->paginate(40)
                ->withQueryString(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Request $request, Contact $contact): Response
    {
        abort_unless($request->user()->can('view', $contact), 403);

        return Inertia::render('Contacts/Show', [
            'contact'       => $contact,
            'conversations' => $contact->conversations()->latest('last_message_at')->limit(20)->get(),
            'leads'         => $contact->leads()->latest()->get(),
            'consentLogs'   => $contact->consentLogs()->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($request->user()->can('update', $contact), 403);

        $validated = $request->validate([
            'name'         => ['nullable', 'string', 'max:160'],
            'email'        => ['nullable', 'email', 'max:190'],
            'attributes'   => ['nullable', 'array'],
        ]);

        $contact->fill($validated)->save();

        $this->audit->logUpdate('contact.updated', $contact);

        return back()->with('success', 'Contact mis à jour.');
    }

    /**
     * Désinscription manuelle, à la demande du contact.
     *
     * Effet immédiat et tracé : c'est la preuve à produire en cas de
     * réclamation ou de contrôle.
     */
    public function optOut(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($request->user()->can('optOut', $contact), 403);

        $contact->forceFill([
            'opt_in_status' => OptInStatus::OptedOut,
            'opt_out_at'    => now(),
            'opt_in_source' => 'dashboard',
        ])->save();

        ConsentLog::create([
            'contact_id' => $contact->id,
            'action'     => 'opt_out',
            'channel'    => 'whatsapp',
            'source'     => 'dashboard',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $this->audit->log('contact.opted_out', $contact);

        return back()->with('success', 'Contact désinscrit.');
    }
}
