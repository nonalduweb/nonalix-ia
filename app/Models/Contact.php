<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptInStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'wa_id', 'phone_number', 'name', 'profile_name', 'email', 'locale',
        'opt_in_status', 'opt_in_at', 'opt_out_at', 'opt_in_source',
        'attributes', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'opt_in_status'   => OptInStatus::class,
            'opt_in_at'       => 'immutable_datetime',
            'opt_out_at'      => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'blocked_at'      => 'immutable_datetime',
            'attributes'      => 'array',
        ];
    }

    protected $attributes = [
        'attributes' => '{}',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function consentLogs(): HasMany
    {
        return $this->hasMany(ConsentLog::class);
    }

    /** Libellé d'affichage : nom saisi, sinon nom de profil, sinon numéro. */
    public function displayName(): string
    {
        return $this->name ?: ($this->profile_name ?: '+'.$this->wa_id);
    }

    public function isReachable(): bool
    {
        return $this->blocked_at === null
            && $this->opt_in_status->allowsServiceMessages();
    }

    public function scopeReachable(Builder $query): Builder
    {
        return $query->whereNull('blocked_at')
            ->where('opt_in_status', '!=', OptInStatus::OptedOut->value);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('profile_name', 'ilike', "%{$term}%")
                ->orWhere('wa_id', 'like', "%{$term}%");
        });
    }
}
