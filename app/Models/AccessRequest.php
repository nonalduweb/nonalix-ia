<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

/**
 * Demande d'accès déposée par un prospect.
 *
 * Modèle central, non cloisonné : elle existe avant tout tenant, et c'est son
 * approbation qui autorise la création d'une entreprise.
 */
class AccessRequest extends Model
{
    use HasUuidPrimaryKey;
    // Notifiable : le code approuvé part vers le prospect, qui n'a pas encore
    // de compte utilisateur — la demande porte donc elle-même l'adresse.
    use Notifiable;

    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'company', 'contact_name', 'email', 'phone', 'plan_id',
        'message', 'status', 'access_code_id', 'review_note',
        'reviewed_by', 'reviewed_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Destinataire des notifications : l'adresse saisie dans la demande. */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }
}
