<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Events\MessageCreated;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController
{
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('view', $conversation), 403);

        return MessageResource::collection(
            $conversation->messages()
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->integer('per_page', 50), 200))
        );
    }

    /**
     * Envoi d'un message via l'API.
     *
     * Réponse 202 et non 200 : le message est accepté et mis en file, il n'est
     * pas encore parti. L'intégrateur doit suivre le statut, pas le supposer.
     */
    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);

        $validated  = $request->validated();
        $isTemplate = ($validated['type'] ?? 'text') === 'template';

        if (! $isTemplate && ! $conversation->isWithinServiceWindow()) {
            return response()->json([
                'message' => 'La fenêtre de service de 24 h est fermée. Utilisez un modèle approuvé.',
                'code'    => 'service_window_closed',
            ], 422);
        }

        $template = $isTemplate
            ? MessageTemplate::query()->approved()->findOrFail($validated['template_id'])
            : null;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => MessageDirection::Outbound,
            'sender_type'     => SenderType::Agent,
            'sender_user_id'  => $request->user()->id,
            'type'            => $isTemplate ? MessageType::Template : MessageType::Text,
            'body'            => $validated['body'] ?? null,
            'template_id'     => $template?->id,
            'status'          => MessageStatus::Queued,
        ]);

        MessageCreated::dispatch($message);

        SendWhatsAppMessageJob::dispatch(
            tenantId: $conversation->tenant_id,
            messageId: $message->id,
            templateParameters: $validated['template_parameters'] ?? [],
        )->onQueue('whatsapp');

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(202);
    }
}
