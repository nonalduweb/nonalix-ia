<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Changement d'état d'une conversation : attribution, transfert humain,
 * activation ou coupure de l'IA, fermeture.
 *
 * Permet à toute l'équipe de voir en direct qu'un collègue a pris une
 * conversation, sans que deux personnes répondent au même contact.
 *
 * N'EMPORTE AUCUN MODÈLE ÉLOQUENT : voir MessageStatusUpdated. Un worker qui
 * recharge un modèle cloisonné sans contexte de tenant fait échouer la
 * diffusion — et deux opérateurs peuvent alors répondre en même temps, ce que
 * cet événement existe précisément pour empêcher.
 */
class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public readonly string $tenantId;

    /** @var array<string, mixed> */
    public readonly array $payload;

    public function __construct(Conversation $conversation)
    {
        $this->tenantId = (string) $conversation->tenant_id;

        $this->payload = [
            'id'               => (string) $conversation->id,
            'status'           => $conversation->status->value,
            'ai_enabled'       => $conversation->ai_enabled,
            'assigned_user_id' => $conversation->assigned_user_id,
            'handover_at'      => $conversation->handover_at?->toIso8601String(),
            'unread_count'     => $conversation->unread_count,
            'last_message_at'  => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.conversations"),
            new PrivateChannel("tenant.{$this->tenantId}.conversation.{$this->payload['id']}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }
}
