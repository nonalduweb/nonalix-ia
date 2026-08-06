<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\AI\ChatMessage;
use App\Data\AI\ChatRequest;
use App\Enums\AiProvider;
use App\Models\PlatformSetting;
use App\Services\AI\AiProviderManager;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clés IA de la plateforme, saisies depuis la super-administration.
 *
 * Elles ne vivaient que dans le `.env` du serveur : un exploitant sans accès
 * SSH ne pouvait en renseigner aucune, et l'absence de clé ne se manifestait
 * qu'au moment où un client échouait à indexer un document.
 *
 * Ces clés servent de SOCLE : un client qui fournit la sienne, dans les
 * réglages de son agent, consomme son propre quota et prime sur celle-ci.
 */
class PlatformKeyController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AiProviderManager $providers,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/PlatformKeys', [
            'providers' => collect(AiProvider::cases())->map(fn (AiProvider $p) => [
                'value' => $p->value,
                'label' => $p->label(),
                // On n'expose JAMAIS la valeur, même tronquée : la présence
                // suffit à piloter l'interface, et un fragment de jeton reste
                // un fragment de secret.
                'configured' => PlatformSetting::has("ai.{$p->value}.api_key"),
                // Distingue une clé saisie ici d'une clé héritée du serveur :
                // sans ce repère, on ne sait pas laquelle on est en train de
                // remplacer.
                'fromEnv' => ! PlatformSetting::has("ai.{$p->value}.api_key")
                    && filled(config("ai.providers.{$p->value}.api_key")),
                'embeddings' => $p->value === config('ai.embeddings.provider'),
            ])->all(),

            'defaultProvider'   => config('ai.default'),
            'fallbackProvider'  => config('ai.fallback'),
            'embeddingModel'    => config('ai.embeddings.model'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', array_column(AiProvider::cases(), 'value'))],
            // Nullable : un champ vidé efface la clé et redonne la main au
            // `.env`, ce qui est le seul moyen de revenir en arrière.
            'api_key'  => ['nullable', 'string', 'max:500'],
        ]);

        PlatformSetting::put(
            "ai.{$validated['provider']}.api_key",
            $validated['api_key'] ?? null,
            $request->user()->id,
        );

        // La valeur ne figure PAS dans le journal : l'audit trace le geste,
        // jamais le secret.
        $this->audit->log('platform.ai_key_updated', context: [
            'provider' => $validated['provider'],
            'action'   => filled($validated['api_key'] ?? null) ? 'enregistrée' : 'effacée',
        ]);

        return back()->with('success', filled($validated['api_key'] ?? null)
            ? 'Clé enregistrée. Testez-la pour valider.'
            : 'Clé effacée. La valeur du serveur reprend la main si elle existe.');
    }

    /** Appel réel au fournisseur, pour valider la clé avant de compter dessus. */
    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', array_column(AiProvider::cases(), 'value'))],
        ]);

        $provider = AiProvider::from($validated['provider']);

        try {
            // Un échange minimal, sur le fournisseur VISÉ : passer par
            // embeddings() aurait testé le fournisseur configuré pour les
            // vecteurs, pas celui dont on veut valider la clé.
            $chat = $this->providers->chat($provider);

            $chat->chat(new ChatRequest(
                model: $chat->defaultModel(),
                messages: [new ChatMessage(role: 'user', content: 'ping')],
                maxTokens: 5,
            ));

            return back()->with('success', "Clé {$provider->label()} valide.");
        } catch (\Throwable $e) {
            return back()->withErrors([
                'api_key' => "Clé {$provider->label()} refusée : ".mb_substr($e->getMessage(), 0, 200),
            ]);
        }
    }
}
