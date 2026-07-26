<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue commercial. Modèle central : partagé par tous les tenants.
 */
class Plan extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name', 'slug', 'description', 'price_cents', 'currency', 'interval',
        'quotas', 'features', 'overage_policy', 'is_active', 'is_public', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quotas'      => 'array',
            'features'    => 'array',
            'price_cents' => 'integer',
            'is_active'   => 'boolean',
            'is_public'   => 'boolean',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('position');
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    /** Le dépassement bloque-t-il le service, ou est-il simplement facturé ? */
    public function blocksOnOverage(): bool
    {
        return $this->overage_policy === 'block';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
