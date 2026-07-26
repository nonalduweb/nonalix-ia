<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status->value,
            'channel'    => $this->channel,
            'ai_enabled' => $this->ai_enabled,

            // Exposé explicitement : c'est l'information qui détermine si un
            // intégrateur peut envoyer un message libre ou doit passer par un
            // template. La lui cacher provoquerait des échecs incompréhensibles.
            'service_window' => [
                'open'       => $this->isWithinServiceWindow(),
                'expires_at' => $this->window_expires_at?->toIso8601String(),
            ],

            'handover' => [
                'at'     => $this->handover_at?->toIso8601String(),
                'reason' => $this->handover_reason,
            ],

            'unread_count'    => $this->unread_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),

            'contact' => $this->whenLoaded('contact', fn () => [
                'id'    => $this->contact->id,
                'wa_id' => $this->contact->wa_id,
                'name'  => $this->contact->displayName(),
                'opt_in_status' => $this->contact->opt_in_status->value,
            ]),

            'assigned_user' => $this->whenLoaded('assignedUser', fn () => [
                'id'   => $this->assignedUser?->id,
                'name' => $this->assignedUser?->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
