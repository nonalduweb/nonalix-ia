<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Models\Service;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue des prestations.
 *
 * Ces données sont injectées dans le prompt de l'agent : chaque modification
 * change immédiatement ce que l'IA annonce aux clients. D'où l'audit
 * systématique et la restriction aux rôles d'encadrement.
 */
class ServiceController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Service::class), 403);

        return Inertia::render('Settings/Services', [
            'services' => Service::query()->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Service::class), 403);

        $service = Service::create($this->validated($request));

        $this->audit->log('service.created', $service, ['after' => $service->only(['name', 'price_cents'])]);

        return back()->with('success', 'Prestation ajoutée.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->can('update', $service), 403);

        $service->fill($this->validated($request))->save();

        $this->audit->logUpdate('service.updated', $service);

        return back()->with('success', 'Prestation mise à jour.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $service), 403);

        $this->audit->log('service.deleted', $service, ['before' => $service->only(['name', 'price_cents'])]);

        $service->delete();

        return back()->with('success', 'Prestation supprimée.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:160'],
            'description'      => ['nullable', 'string', 'max:2000'],
            // Un tarif absent est légitime : c'est le cas « sur devis ».
            'price_cents'      => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'price_type'       => ['required', Rule::in(['fixed', 'from', 'hourly', 'quote'])],
            'currency'         => ['required', 'string', 'size:3'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'category'         => ['nullable', 'string', 'max:80'],
            'is_active'        => ['boolean'],
            'position'         => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
