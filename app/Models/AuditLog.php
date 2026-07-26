<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Journal d'audit, strictement insert-only.
 *
 * `updating` et `deleting` sont bloqués au niveau du modèle : un journal
 * modifiable ne vaut rien en cas d'incident de sécurité. La purge de rétention
 * passe par une requête SQL dédiée, pas par Eloquent.
 *
 * Pas de BelongsToTenant : les actions des super-admins NONALIX n'appartiennent
 * à aucun tenant, et l'administration doit pouvoir tout relire.
 */
class AuditLog extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
        'changes', 'ip_address', 'user_agent', 'context', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes'    => 'array',
            'context'    => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RuntimeException('Le journal d\'audit est immuable.');
        });

        static::deleting(static function (): never {
            throw new RuntimeException('Le journal d\'audit ne peut pas être supprimé.');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
