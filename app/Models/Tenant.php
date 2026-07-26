<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * L'entreprise cliente : racine de toute l'isolation.
 *
 * Modèle CENTRAL — il n'utilise volontairement pas BelongsToTenant, sans quoi
 * il ne pourrait jamais être chargé (le scope aurait besoin d'un tenant pour
 * charger le tenant). Il est déclaré dans nonalix.tenancy.central_models et le
 * test d'architecture s'appuie sur cette liste.
 */
class Tenant extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'status', 'trial_ends_at', 'plan_id',
        'quota_overrides', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'status'          => TenantStatus::class,
            'trial_ends_at'   => 'immutable_datetime',
            'suspended_at'    => 'immutable_datetime',
            'quota_overrides' => 'array',
            'settings'        => 'array',
        ];
    }

    // -- Relations -------------------------------------------------------------

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(WhatsAppAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    // -- État ------------------------------------------------------------------

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    public function isOnTrial(): bool
    {
        return $this->status === TenantStatus::Trial
            && $this->trial_ends_at?->isFuture() === true;
    }

    /**
     * Quotas effectifs : ceux du plan, écrasés par les dérogations accordées
     * au cas par cas par NONALIX.
     *
     * @return array<string, int>
     */
    public function effectiveQuotas(): array
    {
        return array_merge(
            $this->plan?->quotas ?? [],
            $this->quota_overrides ?? [],
        );
    }

    public function quotaFor(string $metric): ?int
    {
        $quotas = $this->effectiveQuotas();

        // Absent du plan = pas de limite. Une limite nulle se déclare
        // explicitement avec la valeur 0.
        return isset($quotas[$metric]) ? (int) $quotas[$metric] : null;
    }

    /** Le premier agent actif — un seul est exploité en Phase 1. */
    public function activeAgent(): ?Agent
    {
        return $this->agents()->where('is_active', true)->first();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
