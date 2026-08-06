<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Nouveau message dans une conversation.
 *
 * Diffusé sur deux canaux : le fil concerné (pour l'opérateur qui l'a ouvert)
 * et la boîte de réception (pour réordonner la liste chez tous les autres).
 *
 * N'EMPORTE AUCUN MODÈLE ÉLOQUENT : voir MessageStatusUpdated. Un worker qui
 * recharge un modèle cloisonné sans contexte de tenant fait échouer la
 * diffusion, et le message n'apparaît jamais en direct.
 */
class MessageCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public readonly string $tenantId;

    /** @var array<string, mixed> */
    public readonly array $payload;

    public function __construct(Message $message)
    {
        $this->tenantId = (string) $message->tenant_id;

        // Charge utile volontairement réduite : `ai_meta` (prompts, fragments
        // RAG, coûts) ne doit pas transiter par un WebSocket vers un navigateur.
        $this->payload = [
            'id'              => (string) $message->id,
            'conversation_id' => (string) $message->conversation_id,
            'direction'       => $message->direction->value,
            'sender_type'     => $message->sender_type->value,
            'type'            => $message->type->value,
            'body'            => $message->body,
            'status'          => $message->status->value,
            'created_at'      => $message->created_at?->toIso8601String(),
        ];
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.conversation.{$this->payload['conversation_id']}"),
            new PrivateChannel("tenant.{$this->tenantId}.conversations"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }
}
