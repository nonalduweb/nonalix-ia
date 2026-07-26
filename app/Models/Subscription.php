<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'plan_id', 'status', 'starts_at', 'ends_at', 'canceled_at',
        'external_reference', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'   => 'immutable_datetime',
            'ends_at'     => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'meta'        => 'array',
        ];
    }

    protected $attributes = ['meta' => '{}'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true)
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', ['trialing', 'active'])
            ->orderByDesc('starts_at');
    }
}
