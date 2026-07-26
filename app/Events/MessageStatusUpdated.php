<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Changement de statut de livraison (envoyé, distribué, lu, échec). */
class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Message $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(
            "tenant.{$this->message->tenant_id}.conversation.{$this->message->conversation_id}"
        )];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'     => $this->message->id,
            'status' => $this->message->status->value,
            // L'opérateur doit voir POURQUOI un message a échoué, pas
            // seulement qu'il a échoué.
            'error'  => $this->message->status->value === 'failed'
                ? ($this->message->error['message'] ?? null)
                : null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.status';
    }
}
