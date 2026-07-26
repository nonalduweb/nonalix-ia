<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Changement d'état d'une conversation : attribution, transfert humain,
 * activation ou coupure de l'IA, fermeture.
 *
 * Permet à toute l'équipe de voir en direct qu'un collègue a pris une
 * conversation, sans que deux personnes répondent au même contact.
 */
class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Conversation $conversation) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $tenantId = $this->conversation->tenant_id;

        return [
            new PrivateChannel("tenant.{$tenantId}.conversations"),
            new PrivateChannel("tenant.{$tenantId}.conversation.{$this->conversation->id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'               => $this->conversation->id,
            'status'           => $this->conversation->status->value,
            'ai_enabled'       => $this->conversation->ai_enabled,
            'assigned_user_id' => $this->conversation->assigned_user_id,
            'handover_at'      => $this->conversation->handover_at?->toIso8601String(),
            'unread_count'     => $this->conversation->unread_count,
            'last_message_at'  => $this->conversation->last_message_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }
}
