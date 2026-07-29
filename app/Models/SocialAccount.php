<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattachement d'un compte externe (Google) à un utilisateur Nonalix.
 */
class SocialAccount extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id', 'provider', 'provider_id', 'email', 'avatar_url', 'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
