<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Changement de statut de livraison (envoyé, distribué, lu, échec).
 *
 * N'EMPORTE AUCUN MODÈLE ÉLOQUENT, à dessein.
 *
 * Avec SerializesModels, la mise en file ne conserve qu'un identifiant, et le
 * worker recharge ensuite le modèle ET ses relations. Or ces modèles sont
 * cloisonnés par tenant : un worker n'ayant aucun contexte, le scope global
 * levait une exception et l'événement échouait — les statuts WhatsApp ne
 * parvenaient jamais au navigateur.
 *
 * La charge utile est donc figée à la construction, là où le contexte existe
 * encore. Plus de rechargement, plus de dépendance au tenant courant, et une
 * requête SQL de moins par diffusion.
 *
 * Le modèle reste accepté en paramètre : les points d'appel sont inchangés.
 */
class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public readonly string $tenantId;

    public readonly string $conversationId;

    public readonly string $messageId;

    public readonly string $status;

    public readonly ?string $error;

    public function __construct(Message $message)
    {
        $this->tenantId       = (string) $message->tenant_id;
        $this->conversationId = (string) $message->conversation_id;
        $this->messageId      = (string) $message->id;
        $this->status         = $message->status->value;

        // L'opérateur doit voir POURQUOI un message a échoué, pas seulement
        // qu'il a échoué.
        $this->error = $message->status->value === 'failed'
            ? ($message->error['message'] ?? null)
            : null;
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(
            "tenant.{$this->tenantId}.conversation.{$this->conversationId}"
        )];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'     => $this->messageId,
            'status' => $this->status,
            'error'  => $this->error,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.status';
    }
}
