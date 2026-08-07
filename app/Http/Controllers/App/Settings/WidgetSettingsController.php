<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Models\Agent;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WidgetSettingsController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** Formulaire de configuration du widget site web. */
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Agent::class), 403);

        $tenant = $this->context->currentOrFail();
        $agent = $tenant->activeAgent();

        return Inertia::render('Settings/Widget', [
            'tenantId' => $tenant->id,
            // `app.url` désigne le SITE COMMERCIAL : le construire à partir de
            // là donnerait un snippet pointant vers un domaine où les routes
            // du widget n'existent pas (404 silencieux chez le client).
            // Le widget est servi et interrogé sur le domaine de l'espace
            // client, seul endroit où routes/widget.php est enregistré.
            'baseUrl'  => $this->widgetBaseUrl(),
            'agent'    => $agent,
        ]);
    }

    /** Met à jour la couleur et les paramètres du widget pour l'agent actif. */
    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->context->currentOrFail();
        $agent = $tenant->activeAgent();

        if ($agent === null) {
            return back()->withErrors(['theme_color' => 'Veuillez d\'abord configurer et activer un Agent IA.']);
        }

        // L'apparence du widget est une donnée de l'agent : même barrière que
        // pour toute autre modification de sa configuration.
        abort_unless($request->user()->can('update', $agent), 403);

        $validated = $request->validate([
            'theme_color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
        ]);

        $settings = $agent->settings ?? [];
        $settings['theme_color'] = $validated['theme_color'];

        $agent->forceFill(['settings' => $settings])->save();

        $this->audit->log('agent.widget_settings_updated', $agent);

        return back()->with('success', 'Paramètres du widget enregistrés.');
    }

    /**
     * Origine sur laquelle `widget.js` est servi et son API répond.
     *
     * Le schéma suit celui de l'application : en local le domaine de test est
     * en clair, en production tout est en https.
     */
    private function widgetBaseUrl(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return $scheme.'://'.config('nonalix.domains.app');
    }
}
