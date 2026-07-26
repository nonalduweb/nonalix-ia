<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'conversation_id', 'wamid', 'direction', 'sender_type', 'sender_user_id',
        'type', 'body', 'media', 'template_id', 'context_wamid',
        'status', 'error', 'sent_at', 'delivered_at', 'read_at', 'failed_at',
        'ai_meta',
    ];

    protected function casts(): array
    {
        return [
            'direction'    => MessageDirection::class,
            'sender_type'  => SenderType::class,
            'type'         => MessageType::class,
            'status'       => MessageStatus::class,
            'media'        => 'array',
            'error'        => 'array',
            'ai_meta'      => 'array',
            'sent_at'      => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'read_at'      => 'immutable_datetime',
            'failed_at'    => 'immutable_datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * Applique un statut de livraison reçu par webhook.
     *
     * Meta ne garantit pas l'ordre d'arrivée : un `delivered` peut parvenir
     * après un `read`. On refuse tout retour en arrière, sinon l'interface
     * afficherait « distribué » sur un message déjà lu.
     *
     * @return bool  true si le statut a effectivement changé
     */
    public function applyStatus(MessageStatus $status, ?array $error = null): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        match ($status) {
            MessageStatus::Sent      => $this->sent_at      ??= now()->toImmutable(),
            MessageStatus::Delivered => $this->delivered_at ??= now()->toImmutable(),
            MessageStatus::Read      => $this->read_at      ??= now()->toImmutable(),
            MessageStatus::Failed    => $this->failed_at    ??= now()->toImmutable(),
            default                  => null,
        };

        if ($error !== null) {
            $this->error = $error;
        }

        return true;
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', MessageDirection::Inbound->value);
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', MessageDirection::Outbound->value);
    }

    /** Messages retenus pour la mémoire conversationnelle envoyée au LLM. */
    public function scopeForMemory(Builder $query): Builder
    {
        return $query->where('sender_type', '!=', SenderType::System->value)
            ->whereNotNull('body')
            ->orderByDesc('created_at');
    }
}
