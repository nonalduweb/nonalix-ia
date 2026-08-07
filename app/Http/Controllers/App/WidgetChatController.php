<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\OptInStatus;
use App\Enums\SenderType;
use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WidgetChatController
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    /** Renvoie la configuration de l'agent et l'historique des messages. */
    public function config(Request $request, string $tenantId): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
        } catch (Throwable) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        return $this->context->runAs($tenant, function () use ($tenant, $request) {
            $agent = $tenant->activeAgent();
            $sessionId = $request->query('session_id');
            $messages = [];

            if ($sessionId) {
                $contact = Contact::where('wa_id', 'web_' . $sessionId)->first();
                if ($contact) {
                    // Même critère que `chat()` : la conversation web en cours
                    // est celle qui n'est pas close. Sans ce filtre, l'historique
                    // affiché pouvait être celui d'une conversation clôturée.
                    $conversation = Conversation::where('contact_id', $contact->id)
                        ->where('channel', 'web')
                        ->whereNull('closed_at')
                        ->first();

                    if ($conversation) {
                        $messages = $conversation->messages()
                            ->orderBy('created_at')
                            ->limit(50)
                            ->get()
                            ->map(fn ($m) => [
                                'body'       => $m->body,
                                'direction'  => $m->direction->value,
                                'created_at' => $m->created_at->toIso8601String(),
                            ]);
                    }
                }
            }

            return response()->json([
                'agent_name'       => $agent?->name ?? 'Assistant',
                'persona'          => $agent?->persona ?? 'Assistant Virtuel',
                'greeting_message' => $agent?->greeting_message ?? 'Bonjour ! Comment puis-je vous aider ?',
                'theme_color'      => $agent?->settings['theme_color'] ?? '#005c4b',
                'messages'         => $messages,
            ]);
        });
    }

    /** Reçoit un message du visiteur web et déclenche l'agent IA. */
    public function chat(Request $request, string $tenantId): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
        } catch (Throwable) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:120'],
            'body'       => ['required', 'string', 'max:5000'],
        ]);

        return $this->context->runAs($tenant, function () use ($tenant, $validated) {
            $sessionId = $validated['session_id'];
            $body = $validated['body'];

            // 1. Trouver ou créer le contact virtuel pour le web chat
            $contact = Contact::firstOrCreate(
                ['wa_id' => 'web_' . $sessionId],
                [
                    'name'          => 'Visiteur Web',
                    'profile_name'  => 'Navigateur Web',
                    'opt_in_status' => OptInStatus::OptedIn,
                ]
            );

            // 2. Trouver ou créer la conversation web active
            $conversation = Conversation::where('contact_id', $contact->id)
                ->where('channel', 'web')
                ->whereNull('closed_at')
                ->first();

            if (! $conversation) {
                // Aucun compte WhatsApp : une conversation web n'en a pas
                // besoin, la colonne est nullable depuis la migration 000028.
                // Ne JAMAIS emprunter le compte d'un autre tenant pour
                // satisfaire la clé étrangère — c'est une fuite de données.
                $conversation = Conversation::create([
                    'contact_id'          => $contact->id,
                    'whatsapp_account_id' => null,
                    'channel'             => 'web',
                    'status'              => ConversationStatus::Open,
                    'ai_enabled'          => true,
                ]);
            }

            // 3. Enregistrer le message entrant
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction'       => MessageDirection::Inbound,
                'sender_type'     => SenderType::Contact,
                'type'            => MessageType::Text,
                'body'            => $body,
                'status'          => MessageStatus::Delivered,
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
            ])->save();

            // Diffuser aux opérateurs pour la mise à jour temps réel de l'inbox
            MessageCreated::dispatch($message);
            ConversationUpdated::dispatch($conversation);

            // 4. Déclencher le job de réponse de l'agent IA
            GenerateAgentReplyJob::dispatch($tenant->id, $conversation->id, $body);

            return response()->json(['status' => 'queued']);
        });
    }
}
