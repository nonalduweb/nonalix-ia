<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\AiProvider;
use App\Models\Agent;
use App\Services\Agent\AgentTemplateLibrary;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AgentController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
        private readonly AgentTemplateLibrary $templates,
    ) {}

    /** Liste des agents IA. */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Agent::class), 403);

        $agents = Agent::query()
            ->latest()
            ->get();

        return Inertia::render('Settings/Agents/Index', [
            'agents' => $agents,

            // La galerie de métiers passe devant tant qu'aucun agent n'est
            // actif. Un nouveau client n'a aucun moyen de décider d'une
            // température ou d'un seuil de pertinence, mais il reconnaît son
            // métier : c'est par là qu'il doit entrer, pas par le formulaire.
            'templates'       => array_map(
                static fn (array $t) => [
                    'title'       => $t['title'],
                    'description' => $t['description'],
                    'industry'    => $t['industry'],
                    'name'        => $t['name'],
                ],
                $this->templates->all(),
            ),
            'hasActiveAgent' => $agents->contains('is_active', true),
        ]);
    }

    /**
     * Installe un modèle métier sur l'agent de l'entreprise.
     *
     * Implémentation unique, partagée avec la page Ventes & Automation : la
     * résolution de l'agent cible porte une vérification d'autorisation, et
     * la dupliquer serait le meilleur moyen de l'oublier d'un côté.
     */
    public function installTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_key' => ['required', 'string', Rule::in($this->templates->keys())],
        ]);

        $agent = $this->context->currentOrFail()->activeAgent() ?? Agent::query()->first();

        // Installer un modèle réécrit le prompt, le nom et les outils : c'est
        // un acte d'encadrement, soumis à la même policy que l'édition.
        if ($agent === null) {
            abort_unless($request->user()->can('create', Agent::class), 403);

            $agent = new Agent($this->platformDefaults());
            $agent->tenant_id = $request->user()->tenant_id;
            $agent->save();
        } else {
            abort_unless($request->user()->can('update', $agent), 403);
        }

        $template = $this->templates->install($agent, $validated['template_key']);

        $this->audit->log('agent.template_installed', $agent, [
            'after' => ['template' => $validated['template_key']],
        ]);

        return redirect()
            ->route('settings.agent.edit', $agent)
            ->with('success', sprintf(
                'Modèle « %s » installé. Essayez votre agent ci-dessous avant de le montrer à vos clients.',
                $template['title'],
            ));
    }

    /**
     * Réglages de plateforme d'un agent neuf.
     *
     * Fournisseur, modèle et bornes de coût : rien que le client ait à
     * choisir. Ils vivent dans config/ai.php.
     *
     * @return array<string, mixed>
     */
    private function platformDefaults(): array
    {
        $provider = config('ai.default');

        return [
            'name'          => 'Assistant',
            'provider'      => $provider,
            'model'         => config('ai.providers.'.$provider.'.default_model'),
            'temperature'   => config('ai.agent.default_temperature'),
            'max_tokens'    => config('ai.agent.default_max_tokens'),
            'memory_window' => config('ai.agent.memory_window'),
            'rag_top_k'     => config('ai.agent.rag_top_k'),
            'rag_min_score' => config('ai.agent.rag_min_score'),
        ];
    }

    /** Formulaire de création d'un agent. */
    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('create', Agent::class), 403);

        return Inertia::render('Settings/Agents/Edit', [
            'agent'     => null,
            'hasApiKey' => false,
            'providers' => AiProvider::options(),
            'tools'     => $this->getToolsCatalog(),
            'defaults'  => config('ai.agent'),
        ]);
    }

    /** Enregistrement d'un nouvel agent. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Agent::class), 403);

        $validated = $this->validateRequest($request);

        $agent = new Agent($validated);
        $agent->tenant_id = $request->user()->tenant_id;

        // Si cet agent est actif, on désactive les autres agents actifs
        if ($agent->is_active) {
            Agent::query()->where('is_active', true)->update(['is_active' => false]);
        }

        $agent->save();

        $this->audit->log('agent.created', $agent);

        return redirect()->route('settings.agent.index')->with('success', 'L\'agent IA a été créé avec succès.');
    }

    /** Édition d'un agent. */
    public function edit(Request $request, Agent $agent): Response
    {
        abort_unless($request->user()->can('view', $agent), 403);

        return Inertia::render('Settings/Agents/Edit', [
            'agent'     => $agent->makeHidden('api_key'),
            'hasApiKey' => $agent->api_key !== null,
            'providers' => AiProvider::options(),
            'tools'     => $this->getToolsCatalog(),
            'defaults'  => config('ai.agent'),
        ]);
    }

    /** Mise à jour d'un agent. */
    public function update(Request $request, Agent $agent): RedirectResponse
    {
        abort_unless($request->user()->can('update', $agent), 403);

        $validated = $this->validateRequest($request);

        // Champ laissé vide = on conserve la clé existante.
        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        // Si cet agent est activé, on désactive tous les autres
        if (!empty($validated['is_active']) && $validated['is_active'] && !$agent->is_active) {
            Agent::query()->where('is_active', true)->update(['is_active' => false]);
        }

        $agent->fill($validated)->save();

        $this->audit->logUpdate('agent.updated', $agent);

        return redirect()->route('settings.agent.index')->with('success', 'Configuration de l\'agent enregistrée.');
    }

    /** Suppression d'un agent. */
    public function destroy(Request $request, Agent $agent): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $agent), 403);

        // Il faut conserver au moins un agent
        $count = Agent::query()->count();
        abort_if($count <= 1, 422, 'Vous devez conserver au moins un agent pour votre entreprise.');

        // Si l'agent supprimé était actif, on active le premier agent restant
        if ($agent->is_active) {
            $next = Agent::query()->whereKeyNot($agent->id)->first();
            if ($next) {
                $next->update(['is_active' => true]);
            }
        }

        $agent->delete();

        $this->audit->log('agent.deleted', $agent);

        return redirect()->route('settings.agent.index')->with('success', 'Agent IA supprimé.');
    }

    /** Aperçu du prompt de l'agent. */
    public function preview(Request $request, Agent $agent): RedirectResponse
    {
        abort_unless($request->user()->can('view', $agent), 403);

        $prompt = app(\App\Services\AI\PromptBuilder::class)->build($agent);

        return back()->with('success', 'Aperçu généré.')
            ->with('promptPreview', $prompt);
    }

    private function getToolsCatalog(): array
    {
        return collect(app('nonalix.agent.tools'))
            ->map(fn ($tool) => [
                'name'        => $tool->name(),
                'description' => $tool->definition()->description,
            ])->values()->all();
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name'                     => ['required', 'string', 'max:120'],
            'provider'                 => ['required', Rule::enum(AiProvider::class)],
            'model'                    => ['required', 'string', 'max:80'],
            'api_key'                  => ['nullable', 'string', 'max:255'],
            'temperature'              => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens'               => ['required', 'integer', 'min:64', 'max:8192'],
            'system_prompt'            => ['nullable', 'string', 'max:20000'],
            'persona'                  => ['nullable', 'string', 'max:120'],
            'tone'                     => ['nullable', 'string', 'max:40'],
            'language'                 => ['required', 'string', 'max:10'],
            'greeting_message'         => ['nullable', 'string', 'max:1000'],
            'fallback_message'         => ['nullable', 'string', 'max:1000'],
            'memory_window'            => ['required', 'integer', 'min:2', 'max:30'],
            'rag_enabled'              => ['boolean'],
            'rag_top_k'                => ['required', 'integer', 'min:1', 'max:20'],
            'rag_min_score'            => ['required', 'numeric', 'min:0', 'max:1'],
            'handover_keywords'        => ['array', 'max:30'],
            'handover_keywords.*'      => ['string', 'max:60'],
            'enabled_tools'            => ['array'],
            'enabled_tools.*'          => ['string', Rule::in(array_keys(app('nonalix.agent.tools')))],
            'active_hours_only'        => ['boolean'],
            'is_active'                => ['boolean'],
            'settings'                 => ['nullable', 'array'],
            'settings.n8n_webhook_url' => ['nullable', 'url', 'max:2048'],

            // Voix. `voice_response_mode` est borne a trois valeurs : le
            // modele retombe sur `same_as_user` devant tout le reste, mais
            // autant refuser une saisie invalide des l'entree.
            'settings.voice_enabled'       => ['boolean'],
            'settings.elevenlabs_voice_id' => ['nullable', 'string', 'max:64'],
            'settings.voice_language'      => ['nullable', 'string', 'max:10'],
            'settings.voice_response_mode' => ['nullable', Rule::in(['text', 'voice', 'same_as_user'])],
        ]);
    }
}
