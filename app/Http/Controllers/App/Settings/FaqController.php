<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Models\Faq;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        // Les FAQ suivent la même politique d'accès que les prestations :
        // elles alimentent le même prompt.
        abort_unless($request->user()->can('viewAny', Service::class), 403);

        return Inertia::render('Settings/Faqs', [
            'faqs' => Faq::query()->orderBy('position')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Service::class), 403);

        $faq = Faq::create($this->validated($request));

        $this->audit->log('faq.created', $faq);

        return back()->with('success', 'Question ajoutée.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        abort_unless($request->user()->can('update', $faq), 403);

        $faq->fill($this->validated($request))->save();

        $this->audit->logUpdate('faq.updated', $faq);

        return back()->with('success', 'Question mise à jour.');
    }

    public function destroy(Request $request, Faq $faq): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $faq), 403);

        $this->audit->log('faq.deleted', $faq);
        $faq->delete();

        return back()->with('success', 'Question supprimée.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question'  => ['required', 'string', 'max:500'],
            // Plafonné : chaque réponse entre intégralement dans le prompt
            // système, donc dans le coût de chaque tour de conversation.
            'answer'    => ['required', 'string', 'max:3000'],
            'category'  => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
            'position'  => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
