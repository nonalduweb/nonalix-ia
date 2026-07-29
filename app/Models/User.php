<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Utilisateur, côté client ou côté NONALIX.
 *
 * `tenant_id` NULL + `is_super_admin` = membre de l'équipe NONALIX. Cet
 * invariant est garanti par une contrainte CHECK en base, pas seulement ici.
 *
 * N'utilise PAS BelongsToTenant : le scope global s'appliquerait à
 * l'authentification elle-même, alors qu'à ce moment aucun tenant n'est encore
 * connu. Le cloisonnement des utilisateurs se fait explicitement, via
 * `scopeOfCurrentTenant` et les policies.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuidPrimaryKey;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'status', 'locale', 'avatar_path',
        'two_factor_method',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'immutable_datetime',
            'password'                => 'hashed',
            // Chiffrés au repos : un dump SQL ne permet pas de rejouer la 2FA.
            'two_factor_secret'         => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at'   => 'immutable_datetime',
            'is_super_admin'            => 'boolean',
            'status'                    => UserStatus::class,
            'last_login_at'             => 'immutable_datetime',
        ];
    }

    // -- Relations -------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    // -- Rôles et état ---------------------------------------------------------

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function belongsToTenant(?string $tenantId): bool
    {
        return $tenantId !== null && $this->tenant_id === $tenantId;
    }

    public function hasTwoFactorEnabled(): bool
    {
        if ($this->two_factor_confirmed_at === null) {
            return false;
        }

        // La méthode par e-mail n'a pas de secret à stocker : la preuve est
        // l'accès à la boîte, vérifié à chaque connexion.
        return $this->two_factor_method === 'email' || $this->two_factor_secret !== null;
    }

    public function usesEmailTwoFactor(): bool
    {
        return $this->two_factor_method === 'email';
    }

    /**
     * La 2FA est-elle obligatoire pour cet utilisateur ?
     *
     * Les super-admins y sont toujours soumis : leur compromission
     * exposerait l'ensemble des clients.
     */
    public function requiresTwoFactor(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $required = config('nonalix.security.two_factor_required_roles', []);

        return $this->roles->pluck('name')->intersect($required)->isNotEmpty();
    }

    public function canAuthenticate(): bool
    {
        return $this->status->canAuthenticate();
    }

    // -- Notifications d'authentification --------------------------------------
    // Les notifications par défaut de Laravel sont en anglais et signées
    // « Laravel ». Elles sont les premiers messages qu'un client reçoit.

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    // -- Portées ---------------------------------------------------------------

    public function scopeOfTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePlatformStaff(Builder $query): Builder
    {
        return $query->whereNull('tenant_id')->where('is_super_admin', true);
    }
}
