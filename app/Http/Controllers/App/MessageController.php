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
use App\Jobs\AI\SendEmailMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Envoi d'un message par un opérateur.
 *
 * Le contrôleur gère la persistance et le routage des messages selon le canal
 * (WhatsApp, E-mail, Web Chat).
 */
class MessageController
{
    public function store(SendMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);

        $validated = $request->validated();
        $isTemplate = ($validated['type'] ?? 'text') === 'template';

        // Pour WhatsApp : hors fenêtre de 24 h, seul un template approuvé peut sortir.
        if ($conversation->channel === 'whatsapp' && ! $isTemplate && ! $conversation->isWithinServiceWindow()) {
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

        // Un opérateur qui écrit reprend de fait la main : couper l'IA
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

        // Envoyer selon le canal de communication
        if ($conversation->channel === 'email') {
            SendEmailMessageJob::dispatch($conversation->tenant_id, $message->id)->onQueue('email');
        } elseif ($conversation->channel === 'whatsapp') {
            SendWhatsAppMessageJob::dispatch(
                tenantId: $conversation->tenant_id,
                messageId: $message->id,
                templateParameters: $validated['template_parameters'] ?? [],
            )->onQueue('whatsapp');
        } else { // web
            $message->update(['status' => MessageStatus::Delivered]);
        }

        return back();
    }

    /** Permet à l'opérateur de valider, modifier et envoyer un brouillon généré par l'IA. */
    public function sendDraft(Request $request, Message $message): RedirectResponse
    {
        $conversation = $message->conversation;
        abort_unless($request->user()->can('reply', $conversation), 403);
        abort_unless($message->status === MessageStatus::Draft, 422, 'Ce message n\'est pas un brouillon.');

        // L'opérateur peut retoucher le brouillon avant de l'envoyer. Le corps
        // reste une saisie utilisateur : il se valide comme les autres.
        $validated = $request->validate([
            'body' => ['sometimes', 'string', 'max:4096'],
        ]);

        if (array_key_exists('body', $validated)) {
            $message->body = $validated['body'];
        }

        $message->status = MessageStatus::Queued;
        $message->sender_user_id = $request->user()->id; // Reprise de paternité par l'opérateur
        $message->save();

        MessageCreated::dispatch($message);

        // Envoyer selon le canal de communication
        if ($conversation->channel === 'email') {
            SendEmailMessageJob::dispatch($conversation->tenant_id, $message->id)->onQueue('email');
        } elseif ($conversation->channel === 'whatsapp') {
            SendWhatsAppMessageJob::dispatch(
                tenantId: $conversation->tenant_id,
                messageId: $message->id
            )->onQueue('whatsapp');
        } else { // web
            $message->update(['status' => MessageStatus::Delivered]);
        }

        return back()->with('success', 'Brouillon validé et envoyé avec succès.');
    }
}
