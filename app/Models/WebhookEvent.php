<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookEventStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal brut d'un webhook entrant.
 *
 * N'utilise PAS BelongsToTenant : une requête dont la signature est invalide
 * doit être tracée avant même qu'on ait le droit de faire confiance au tenant
 * indiqué dans l'URL. Le cloisonnement se fait explicitement en lecture.
 */
class WebhookEvent extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'provider', 'event_type', 'idempotency_key',
        'signature_valid', 'payload', 'status', 'attempts', 'error',
        'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'         => 'array',
            'signature_valid' => 'boolean',
            'status'          => WebhookEventStatus::class,
            'attempts'        => 'integer',
            'received_at'     => 'immutable_datetime',
            'processed_at'    => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Clé d'idempotence d'un événement Meta.
     *
     * Meta rejoue les webhooks tant qu'il n'a pas reçu de 200, et peut livrer
     * plusieurs fois le même événement. La clé combine tenant, identifiant de
     * message, type et statut : un même wamid peut légitimement produire
     * plusieurs événements (sent, delivered, read), qui doivent chacun être
     * traités une fois et une seule.
     */
    public static function makeIdempotencyKey(
        ?string $tenantId,
        string $eventType,
        string $reference,
        ?string $status = null,
    ): string {
        return hash('sha256', implode('|', [
            $tenantId ?? 'unknown',
            $eventType,
            $reference,
            $status ?? '',
        ]));
    }

    public function markProcessed(): void
    {
        $this->forceFill([
            'status'       => WebhookEventStatus::Processed,
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status'   => WebhookEventStatus::Failed,
            'error'    => mb_substr($error, 0, 2000),
            'attempts' => $this->attempts + 1,
        ])->save();
    }
}
