<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Bon d'entrée émis par NONALIX, ouvrant la création d'une entreprise.
 *
 * Modèle central, non cloisonné : un code existe avant tout tenant, et c'est
 * précisément lui qui en autorise la naissance.
 */
class AccessCode extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code', 'plan_id', 'label', 'max_uses', 'used_count',
        'trial_days', 'expires_at', 'revoked_at', 'created_by', 'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'max_uses'   => 'integer',
            'used_count' => 'integer',
            'trial_days' => 'integer',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(AccessCodeRedemption::class);
    }

    /**
     * Engendre un code lisible à l'oral et à l'écrit.
     *
     * L'alphabet exclut I, O, 0, 1 : un code se dicte au téléphone ou se
     * recopie depuis un écran, et ces caractères se confondent. Groupé par
     * quatre pour rester déchiffrable.
     */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $blocks = [];

            for ($b = 0; $b < 3; $b++) {
                $block = '';

                for ($i = 0; $i < 4; $i++) {
                    // random_int, et non rand() : la prévisibilité d'un code
                    // permettrait d'en deviner d'autres et d'ouvrir un compte.
                    $block .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }

                $blocks[] = $block;
            }

            $code = implode('-', $blocks);
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /** Normalise une saisie utilisateur : casse, espaces, tirets manquants. */
    public static function normalize(string $input): string
    {
        $clean = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');

        return implode('-', str_split($clean, 4));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        // max_uses = 0 signifie « sans limite ».
        return $this->max_uses > 0 && $this->used_count >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isExhausted();
    }

    /** Raison du refus, à des fins d'affichage côté administration seulement. */
    public function unusableReason(): ?string
    {
        return match (true) {
            $this->isRevoked()   => 'révoqué',
            $this->isExpired()   => 'expiré',
            $this->isExhausted() => 'épuisé',
            default              => null,
        };
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $q) => $q->where('max_uses', 0)->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
