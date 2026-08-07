<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\LeadStatus;
use App\Models\Agent;
use App\Models\Lead;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
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
            'templates' => $this->getAutomationsLibrary(),
        ]);
    }

    /** Installe une configuration d'agent depuis la bibliothèque. */
    public function install(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_key' => ['required', 'string', 'in:'.implode(',', array_keys($this->getAutomationsLibrary()))],
        ]);

        $tenant = $this->context->currentOrFail();
        $agent = $tenant->activeAgent() ?? Agent::query()->first();

        // Installer un modèle réécrit le prompt, le nom et les outils de
        // l'agent : c'est un acte d'encadrement, soumis à la même policy que
        // l'édition depuis Configuration › Agent IA.
        if ($agent === null) {
            abort_unless($request->user()->can('create', Agent::class), 403);

            $agent = new Agent([
                'name'          => 'Assistant',
                'provider'      => config('ai.default'),
                'model'         => config('ai.providers.'.config('ai.default').'.default_model'),
                'temperature'   => config('ai.agent.default_temperature'),
                'max_tokens'    => config('ai.agent.default_max_tokens'),
                'memory_window' => config('ai.agent.memory_window'),
                'rag_top_k'     => config('ai.agent.rag_top_k'),
                'rag_min_score' => config('ai.agent.rag_min_score'),
            ]);
            $agent->tenant_id = $request->user()->tenant_id;
            $agent->save();
        } else {
            abort_unless($request->user()->can('update', $agent), 403);
        }

        $library = $this->getAutomationsLibrary();
        $template = $library[$validated['template_key']] ?? null;

        if ($template === null) {
            return back()->withErrors(['template' => 'Modèle d\'automatisation introuvable.']);
        }

        // Un seul agent actif par entreprise : même invariant que dans
        // Settings\AgentController.
        Agent::query()->whereKeyNot($agent->id)->where('is_active', true)->update(['is_active' => false]);

        // On remplace le profil de l'agent par la configuration pré-packagée
        $agent->forceFill([
            'name'             => $template['name'],
            'persona'          => $template['persona'],
            'greeting_message' => $template['greeting_message'],
            'fallback_message' => $template['fallback_message'],
            'system_prompt'    => $template['system_prompt'],
            'enabled_tools'    => $template['enabled_tools'],
            'is_active'        => true,
        ])->save();

        $this->audit->log('agent.template_installed', $agent, ['template' => $validated['template_key']]);

        return redirect()->route('settings.agent.index')->with('success', sprintf('Modèle « %s » installé avec succès sur votre agent.', $template['title']));
    }

    /** Bibliothèque d'automatisations prêtes à installer. */
    private function getAutomationsLibrary(): array
    {
        return [
            'restaurant' => [
                'title'       => 'Restaurant / Prise de Table',
                'description' => 'Idéal pour gérer les réservations de tables, les horaires et l\'envoi automatique du menu par PDF.',
                'industry'    => 'Restauration',
                'name'        => 'Léon - Maître d\'Hôtel',
                'persona'     => 'hôte d\'accueil virtuel',
                'greeting_message' => 'Bonjour ! Bienvenue au restaurant Chez Léon. Je peux réserver une table pour vous ou vous envoyer notre menu. Que puis-je faire pour vous ?',
                'fallback_message' => 'Je n\'arrive pas à enregistrer votre demande. Un équipier prend le relais pour valider votre réservation.',
                'system_prompt' => "Tu es Léon, le maître d'hôtel virtuel du restaurant Chez Léon. Ton rôle est de répondre chaleureusement aux clients, de donner nos horaires d'ouverture et notre adresse, et surtout de réserver des tables.\n\nCONSIGNES :\n1. Pour réserver, demande la date, l'heure et le nombre de convives.\n2. Dès que tu as ces informations, appelle l'outil `book_appointment` pour enregistrer la table.\n3. Si le client demande la carte ou le menu, appelle l'outil `send_document` avec le paramètre 'catalogue' pour lui transmettre.",
                'enabled_tools' => ['request_human_handover', 'book_appointment', 'get_business_hours', 'send_document'],
            ],
            'real_estate' => [
                'title'       => 'Agence Immobilière / Visites',
                'description' => 'Qualifie les critères de recherche des acheteurs (budget, secteur, type de bien) et planifie les visites.',
                'industry'    => 'Immobilier',
                'name'        => 'Antoine - Agent Immobilier',
                'persona'     => 'assistant immobilier virtuel',
                'greeting_message' => 'Bonjour ! Je suis Antoine, conseiller chez Nonalix Immo. Vous recherchez un appartement ou une maison à acheter ou louer ? Dites-moi tout !',
                'fallback_message' => 'Je note vos critères. Un conseiller humain de notre agence va vous rappeler sous peu.',
                'system_prompt' => "Tu es Antoine, un assistant immobilier virtuel pour l'agence. Ton rôle est d'accueillir les porteurs de projets d'achat ou de location, de qualifier précisément leurs critères et de planifier des visites.\n\nCONSIGNES :\n1. Qualifie le type de bien (maison/appartement), le budget maximal, le secteur géographique souhaité et le nombre de pièces.\n2. Dès que tu as le besoin principal et le budget, appelle l'outil `qualify_lead` pour enregistrer le prospect.\n3. Si le client souhaite visiter un bien en particulier, demande ses disponibilités et planifie la visite avec l'outil `book_appointment`.",
                'enabled_tools' => ['request_human_handover', 'qualify_lead', 'book_appointment'],
            ],
            'clinic' => [
                'title'       => 'Cabinet Médical & Cliniques',
                'description' => 'Permet aux patients de planifier un rendez-vous médical, d\'obtenir vos horaires et de collecter des documents.',
                'industry'    => 'Santé',
                'name'        => 'Julie - Secrétaire Médicale',
                'persona'     => 'secrétaire médicale virtuelle',
                'greeting_message' => 'Bonjour, Cabinet médical du Dr. Martin. Je suis Julie. Souhaitez-vous planifier une consultation ou nous poser une question ?',
                'fallback_message' => 'Je n\'ai pas accès à l\'agenda pour cette spécialité. Notre secrétariat vous recontacte immédiatement.',
                'system_prompt' => "Tu es Julie, secrétaire médicale virtuelle pour le Cabinet Médical. Ton rôle est d'assister les patients dans la planification de leurs rendez-vous et de répondre aux questions pratiques (accès, horaires).\n\nCONSIGNES :\n1. Demande le motif de consultation et le jour souhaité.\n2. Appelle l'outil `book_appointment` pour bloquer le rendez-vous.\n3. Si le patient a besoin de la feuille de route d'accès au cabinet, appelle l'outil `send_document` avec le paramètre 'plaquette'.",
                'enabled_tools' => ['request_human_handover', 'book_appointment', 'get_business_hours', 'send_document'],
            ],
            'ecommerce' => [
                'title'       => 'Service Client E-commerce',
                'description' => 'Aide les clients à suivre leur colis (numéro de commande), obtenir le catalogue et qualifie les intentions d\'achat.',
                'industry'    => 'E-commerce',
                'name'        => 'Eva - Support Client',
                'persona'     => 'conseillère e-commerce virtuelle',
                'greeting_message' => 'Bonjour ! Je suis Eva du service client. Je peux suivre votre colis, vérifier un stock ou vous envoyer notre catalogue. Comment puis-je vous aider ?',
                'fallback_message' => 'Je transmets votre dossier de commande à notre support humain. Vous recevrez une réponse d\'ici quelques minutes.',
                'system_prompt' => "Tu es Eva, conseillère client e-commerce. Ton objectif est de guider les acheteurs, résoudre les problèmes de livraison et capturer les intentions d'achat.\n\nCONSIGNES :\n1. Si le client veut suivre son colis, demande la référence de sa commande (ex: CMD-12345) et appelle l'outil `check_order_status`.\n2. Si le client cherche un produit spécifique, présente nos offres et appelle `create_prospect` pour qualifier l'intérêt.\n3. Pour lui transmettre le catalogue général, utilise `send_document` avec 'catalogue'.",
                'enabled_tools' => ['request_human_handover', 'check_order_status', 'send_document', 'create_prospect'],
            ],
            'travel_agency' => [
                'title'       => 'Agence de Voyage / Devis',
                'description' => 'Idéal pour qualifier les séjours (destination, budget, dates) et envoyer une proposition de devis estimatif.',
                'industry'    => 'Tourisme',
                'name'        => 'Marc - Conseiller Voyage',
                'persona'     => 'expert voyage virtuel',
                'greeting_message' => 'Bonjour ! Prêt pour votre prochaine évasion ? Je suis Marc. Quelle est votre destination de rêve ?',
                'fallback_message' => 'Votre projet de voyage est magnifique ! Un conseiller spécialisé prend la main pour concevoir votre programme sur-mesure.',
                'system_prompt' => "Tu es Marc, conseiller de voyage virtuel. Ton rôle est de faire rêver les clients tout en qualifiant rigoureusement leur projet de voyage pour leur envoyer un devis.\n\nCONSIGNES :\n1. Demande la destination souhaitée, les dates ou la période de départ, le nombre de voyageurs (adultes/enfants) et le budget global approximatif.\n2. Enregistre le projet de voyage dans le CRM avec `create_prospect`.\n3. Dès que les détails du voyage sont clairs, appelle `generate_quote` pour déclencher la création et l'envoi du devis.",
                'enabled_tools' => ['request_human_handover', 'create_prospect', 'generate_quote'],
            ],
        ];
    }
}
