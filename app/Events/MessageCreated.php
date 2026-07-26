<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Nouveau message dans une conversation.
 *
 * Diffusé sur deux canaux : le fil concerné (pour l'opérateur qui l'a ouvert)
 * et la boîte de réception (pour réordonner la liste chez tous les autres).
 */
class MessageCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Message $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $tenantId = $this->message->tenant_id;

        return [
            new PrivateChannel("tenant.{$tenantId}.conversation.{$this->message->conversation_id}"),
            new PrivateChannel("tenant.{$tenantId}.conversations"),
        ];
    }

    /**
     * Charge utile diffusée.
     *
     * Volontairement réduite : `ai_meta` (prompts, fragments RAG, coûts) ne
     * doit pas transiter par un WebSocket vers un navigateur.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'direction'       => $this->message->direction->value,
            'sender_type'     => $this->message->sender_type->value,
            'type'            => $this->message->type->value,
            'body'            => $this->message->body,
            'status'          => $this->message->status->value,
            'created_at'      => $this->message->created_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }
}
