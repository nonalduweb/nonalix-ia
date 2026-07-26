<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsAppAccountStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Numéro WhatsApp Business connecté par une entreprise.
 *
 * Porte les secrets Meta du client. Tous chiffrés au repos : une copie de la
 * base ne suffit pas à envoyer des messages en son nom.
 */
class WhatsAppAccount extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'waba_id', 'phone_number_id', 'display_phone_number', 'verified_name',
        'business_id', 'access_token', 'app_secret', 'webhook_verify_token',
        'quality_rating', 'messaging_limit', 'status',
    ];

    protected $hidden = ['access_token', 'app_secret', 'webhook_verify_token'];

    protected function casts(): array
    {
        return [
            'access_token'         => 'encrypted',
            'app_secret'           => 'encrypted',
            'webhook_verify_token' => 'encrypted',
            'status'               => WhatsAppAccountStatus::class,
            'connected_at'         => 'immutable_datetime',
            'last_verified_at'     => 'immutable_datetime',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function canSend(): bool
    {
        return $this->status->canSend()
            && $this->access_token !== null
            && $this->phone_number_id !== null;
    }

    /** URL de callback à déclarer dans la console Meta du client. */
    public function webhookUrl(): string
    {
        return sprintf(
            'https://%s/webhooks/whatsapp/%s',
            config('nonalix.domains.api'),
            $this->tenant_id,
        );
    }

    /**
     * Résout le compte à partir du seul identifiant présent dans un webhook.
     *
     * Sans cloisonnement, car à ce stade le tenant n'est pas encore établi :
     * c'est précisément cette requête qui le détermine. L'unicité GLOBALE de
     * `phone_number_id` rend le résultat non ambigu.
     */
    public static function resolveByPhoneNumberId(string $phoneNumberId): ?self
    {
        return static::withoutTenantScope()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }
}
