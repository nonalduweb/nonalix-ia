<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\Agent\AgentTemplateLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController
{
    public function __construct(
        private readonly AgentTemplateLibrary $templates,
    ) {}

    /** Affiche le tableau de bord commercial et la bibliothèque d'automatisations. */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Lead::class), 403);

        $since = now()->startOfDay();

        // Prospects aujourd'hui
        $leadsToday = Lead::query()->where('created_at', '>=', $since)->count();

        // Prospects qualifiés (score >= 50 ou status = qualified)
        $qualified = Lead::query()
            ->where(fn ($q) => $q->where('score', '>=', 50)->orWhere('status', LeadStatus::Qualified->value))
            ->count();

        // Prospects chauds (score >= 75)
        $hotLeads = Lead::query()
            ->where('score', '>=', 75)
            ->count();

        // RDV obtenus (appointment_booked = true dans la qualification JSON)
        $appointments = Lead::query()
            ->where('qualification->appointment_booked', true)
            ->count();

        // Devis envoyés (quote_sent = true dans la qualification JSON)
        $quotes = Lead::query()
            ->where('qualification->quote_sent', true)
            ->count();

        // Conversions (won)
        $conversions = Lead::query()
            ->where('status', LeadStatus::Won->value)
            ->count();

        return Inertia::render('SalesDashboard', [
            'metrics' => [
                'leads_today'  => $leadsToday,
                'qualified'    => $qualified,
                'hot'          => $hotLeads,
                'appointments' => $appointments,
                'quotes'       => $quotes,
                'conversions'  => $conversions,
            ],
            'templates' => $this->templates->all(),
        ]);
    }

    // L'installation d'un modèle vit dans Settings\AgentController : elle porte
    // une vérification d'autorisation, et la dupliquer ici serait le meilleur
    // moyen de l'oublier d'un côté le jour où elle évolue.
}
