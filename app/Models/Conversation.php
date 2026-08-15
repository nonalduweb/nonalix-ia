<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'contact_id', 'whatsapp_account_id', 'agent_id', 'channel', 'status', 'ai_enabled',
        'assigned_user_id', 'last_message_at', 'last_inbound_at',
        'window_expires_at', 'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'status'            => ConversationStatus::class,
            'ai_enabled'        => 'boolean',
            'handover_at'       => 'immutable_datetime',
            'last_message_at'   => 'immutable_datetime',
            'last_inbound_at'   => 'immutable_datetime',
            'window_expires_at' => 'immutable_datetime',
            'closed_at'         => 'immutable_datetime',
            'unread_count'      => 'integer',
        ];
    }

    // -- Relations -------------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'id', 'conversation_id');
    }

    // -- Fenêtre de service ----------------------------------------------------

    /**
     * La fenêtre de 24 h est-elle encore ouverte ?
     *
     * En dehors, Meta n'accepte que les templates approuvés. On vérifie de
     * notre côté plutôt que de laisser Meta refuser : un envoi rejeté dégrade
     * la note de qualité du numéro du client.
     */
    public function isWithinServiceWindow(?CarbonImmutable $at = null): bool
    {
        if ($this->window_expires_at === null) {
            return false;
        }

        return $this->window_expires_at->isAfter($at ?? CarbonImmutable::now());
    }

    /**
     * L'opérateur peut-il écrire librement sur cette conversation ?
     *
     * La fenêtre de 24 h est une règle de Meta, pas une règle de Nonalix : elle
     * n'a aucun sens sur le widget web ni par courrier. Or `window_expires_at`
     * n'est posée que par le webhook WhatsApp — les conversations des autres
     * canaux l'avaient donc toujours à null, et l'interface y désactivait la
     * saisie. Un opérateur ne pouvait pas répondre à un visiteur de son propre
     * site.
     */
    public function isWritable(): bool
    {
        return $this->channel !== 'whatsapp' || $this->isWithinServiceWindow();
    }

    /** Recalcule la fenêtre à partir d'un message entrant. */
    public function refreshServiceWindow(CarbonImmutable $inboundAt): void
    {
        $hours = (int) config('whatsapp.service_window_hours', 24);

        $this->forceFill([
            'last_inbound_at'   => $inboundAt,
            'window_expires_at' => $inboundAt->addHours($hours),
        ]);
    }

    // -- État ------------------------------------------------------------------

    /** L'agent IA doit-il répondre sur cette conversation ? */
    public function shouldAiRespond(): bool
    {
        return $this->ai_enabled
            && $this->handover_at === null
            && $this->status->isActive();
    }

    public function isHandedOver(): bool
    {
        return $this->handover_at !== null;
    }

    // -- Portées ---------------------------------------------------------------

    public function scopeInbox(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ConversationStatus::Open->value,
            ConversationStatus::Pending->value,
        ])->orderByDesc('last_message_at');
    }

    public function scopeAssignedTo(Builder $query, string $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeAwaitingHuman(Builder $query): Builder
    {
        return $query->whereNotNull('handover_at')
            ->where('status', ConversationStatus::Pending->value);
    }
}
