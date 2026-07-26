<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Events\MessageCreated;
use App\Http\Requests\SendMessageRequest;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;

/**
 * Envoi d'un message par un opérateur.
 *
 * Le contrôleur ne parle jamais à Meta : il persiste le message en `queued`
 * et confie l'envoi à un job. L'opérateur voit son message apparaître
 * immédiatement, puis les statuts remontent par webhook.
 */
class MessageController
{
    public function store(SendMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);

        $validated = $request->validated();
        $isTemplate = ($validated['type'] ?? 'text') === 'template';

        // Hors fenêtre de 24 h, seul un template approuvé peut sortir. On le
        // dit explicitement à l'opérateur plutôt que de laisser l'envoi
        // échouer silencieusement quelques secondes plus tard.
        if (! $isTemplate && ! $conversation->isWithinServiceWindow()) {
            return back()->withErrors([
                'body' => 'La fenêtre de 24 h est fermée. Utilisez un modèle de message approuvé.',
            ]);
        }

        $template = null;

        if ($isTemplate) {
            $template = MessageTemplate::query()
                ->approved()
                ->findOrFail($validated['template_id']);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => MessageDirection::Outbound,
            'sender_type'     => SenderType::Agent,
            'sender_user_id'  => $request->user()->id,
            'type'            => $isTemplate ? MessageType::Template : MessageType::Text,
            'body'            => $validated['body'] ?? null,
            'template_id'     => $template?->id,
            'context_wamid'   => $validated['reply_to'] ?? null,
            'status'          => MessageStatus::Queued,
        ]);

        // Un opérateur qui écrit reprend de fait la main : laisser l'IA
        // répondre par-dessus produirait deux réponses contradictoires.
        if ($conversation->ai_enabled) {
            $conversation->forceFill([
                'ai_enabled'      => false,
                'handover_at'     => now(),
                'handover_reason' => 'reponse_operateur',
            ])->save();
        }

        $conversation->forceFill([
            'last_message_at'  => now(),
            'assigned_user_id' => $conversation->assigned_user_id ?? $request->user()->id,
        ])->save();

        MessageCreated::dispatch($message);

        SendWhatsAppMessageJob::dispatch(
            tenantId: $conversation->tenant_id,
            messageId: $message->id,
            templateParameters: $validated['template_parameters'] ?? [],
        )->onQueue('whatsapp');

        return back();
    }
}
