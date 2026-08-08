<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Models\Document;
use App\Services\AI\AiProviderManager;
use App\Services\Tenancy\TenantContext;
use App\Data\AI\ChatRequest;
use App\Data\AI\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Facades\Log;

class ConfigCopilotController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AiProviderManager $providers
    ) {}

    /** Traite la question de l'administrateur concernant la configuration de son espace. */
    public function ask(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.view'), 403);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $tenant = $this->context->current();
        if (! $tenant) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        $agent = $tenant->activeAgent();
        if (! $agent) {
            return response()->json(['error' => 'Aucun agent actif disponible.'], 422);
        }

        $provider = $this->providers->chatFor($agent);
        $question = $validated['question'];

        // Récupérer des statistiques simples d'onboarding
        $whatsappConnected = $tenant->whatsappAccounts()->where('status', 'connected')->exists() ? 'oui' : 'non';
        $widgetConfigured  = ! empty($agent->settings['theme_color']) ? 'oui' : 'non';
        $documentsCount = Document::query()->searchable()->count();

        $planName = $tenant->plan?->name ?? 'aucun';

        $systemPrompt = <<<TEXT
Tu es le Copilote Nonalix, un assistant virtuel expert conçu pour guider l'administrateur dans la configuration technique de Nonalix IA.
Voici l'état actuel de son entreprise (nom : {$tenant->name}) :
- Statut de l'abonnement : {$tenant->status->value}
- Plan souscrit : {$planName}
- Numéro WhatsApp connecté : {$whatsappConnected}
- Widget Web configuré : {$widgetConfigured}
- Documents dans la base de connaissances : {$documentsCount} document(s) importé(s)

Règles de réponse :
1. Sois extrêmement poli, encourageant et très clair (en français).
2. Fournis des explications étape par étape et des listes à puces.
3. Rappelle que le virement de renouvellement de facturation se fait par Mobile Money (Wave, MTN, Moov) au numéro support officiel +225 05 66 36 03 03 et que le code d'accès est reçu par WhatsApp/mail pour être rentré dans l'onglet Facturation.
4. Si la question n'a aucun rapport avec la configuration technique de Nonalix (ex: météo, recettes, programmation générale), réponds gentiment que ton rôle se limite à l'onboarding Nonalix IA.
TEXT;

        try {
            $chatRequest = new ChatRequest(
                model: $agent->model ?: $provider->defaultModel(),
                messages: [
                    ChatMessage::user($question)
                ],
                system: $systemPrompt,
                temperature: 0.3,
                maxTokens: 1000
            );

            $response = $provider->chat($chatRequest);

            // Appel facture : il compte, comme toute generation.
            app(\App\Services\Billing\QuotaService::class)->increment($tenant->id, 'ai_requests');
            
            return response()->json([
                'answer' => $response->content
            ]);
        } catch (Throwable $e) {
            Log::error("Erreur de chat copilote : " . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors de la communication avec le moteur d\'intelligence artificielle.'
            ], 500);
        }
    }
}
