<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\AiProvider;
use App\Models\Agent;
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
    ) {}

    public function edit(Request $request): Response
    {
        $agent = $this->currentAgent();

        abort_unless($request->user()->can('view', $agent), 403);

        return Inertia::render('Settings/Agent', [
            'agent'     => $agent->makeHidden('api_key'),
            'hasApiKey' => $agent->api_key !== null,
            'providers' => AiProvider::options(),
            // Le catalogue d'outils est exposé pour que le client choisisse
            // lesquels activer, avec leur description exacte.
            'tools' => collect(app('nonalix.agent.tools'))
                ->map(fn ($tool) => [
                    'name'        => $tool->name(),
                    'description' => $tool->definition()->description,
                ])->values(),
            'defaults' => config('ai.agent'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $agent = $this->currentAgent();

        abort_unless($request->user()->can('update', $agent), 403);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'provider'          => ['required', Rule::enum(AiProvider::class)],
            'model'             => ['required', 'string', 'max:80'],
            'api_key'           => ['nullable', 'string', 'max:255'],
            'temperature'       => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens'        => ['required', 'integer', 'min:64', 'max:8192'],
            'system_prompt'     => ['nullable', 'string', 'max:8000'],
            'persona'           => ['nullable', 'string', 'max:120'],
            'tone'              => ['nullable', 'string', 'max:40'],
            'language'          => ['required', 'string', 'max:10'],
            'greeting_message'  => ['nullable', 'string', 'max:1000'],
            'fallback_message'  => ['nullable', 'string', 'max:1000'],
            // Au-delà de ~30 messages, le coût par tour explose sans gain de
            // pertinence : la borne est un garde-fou économique.
            'memory_window'     => ['required', 'integer', 'min:2', 'max:30'],
            'rag_enabled'       => ['boolean'],
            'rag_top_k'         => ['required', 'integer', 'min:1', 'max:20'],
            'rag_min_score'     => ['required', 'numeric', 'min:0', 'max:1'],
            'handover_keywords'   => ['array', 'max:30'],
            'handover_keywords.*' => ['string', 'max:60'],
            'enabled_tools'       => ['array'],
            'enabled_tools.*'     => ['string', Rule::in(array_keys(app('nonalix.agent.tools')))],
            'active_hours_only' => ['boolean'],
            'is_active'         => ['boolean'],
        ]);

        // Champ laissé vide = on conserve la clé existante, plutôt que de
        // l'effacer parce que le formulaire ne la renvoie jamais.
        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $agent->fill($validated)->save();

        $this->audit->logUpdate('agent.updated', $agent);

        return back()->with('success', 'Configuration de l\'agent enregistrée.');
    }

    /**
     * Aperçu du prompt système réellement envoyé au modèle.
     *
     * Fonctionnalité de transparence : le client doit pouvoir vérifier ce que
     * son agent « sait » avant de le laisser parler à ses clients.
     */
    public function preview(Request $request): RedirectResponse
    {
        $agent = $this->currentAgent();

        abort_unless($request->user()->can('update', $agent), 403);

        $prompt = app(\App\Services\AI\PromptBuilder::class)->build($agent);

        return back()->with('success', 'Aperçu généré.')
            ->with('promptPreview', $prompt);
    }

    /**
     * L'agent du tenant, créé à la volée avec des valeurs sûres si l'entreprise
     * n'en a pas encore configuré.
     */
    private function currentAgent(): Agent
    {
        return Agent::query()->firstOr(function () {
            $agent = new Agent([
                'name'              => 'Assistant',
                'provider'          => config('ai.default'),
                'model'             => config('ai.providers.'.config('ai.default').'.default_model'),
                'temperature'       => config('ai.agent.default_temperature'),
                'max_tokens'        => config('ai.agent.default_max_tokens'),
                'memory_window'     => config('ai.agent.memory_window'),
                'rag_top_k'         => config('ai.agent.rag_top_k'),
                'rag_min_score'     => config('ai.agent.rag_min_score'),
                'handover_keywords' => ['humain', 'conseiller', 'agent', 'quelqu\'un'],
                'enabled_tools'     => ['request_human_handover', 'list_services', 'get_business_hours'],
                'is_active'         => false,
            ]);

            $agent->save();

            return $agent;
        });
    }
}
