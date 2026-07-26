<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'wamid'       => $this->wamid,
            'direction'   => $this->direction->value,
            'sender_type' => $this->sender_type->value,
            'type'        => $this->type->value,
            'body'        => $this->body,
            'status'      => $this->status->value,

            // Seul le message d'erreur remonte, jamais la réponse Meta brute :
            // elle peut contenir des identifiants internes.
            'error' => $this->when(
                $this->error !== null,
                fn () => ['message' => $this->error['message'] ?? null],
            ),

            'timestamps' => [
                'sent_at'      => $this->sent_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'read_at'      => $this->read_at?->toIso8601String(),
            ],

            // `ai_meta` (prompts, coûts, fragments RAG) n'est JAMAIS exposé
            // par l'API : ce sont des données d'exploitation internes.

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
