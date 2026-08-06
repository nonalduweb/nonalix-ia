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

    /*
     * Clé étrangère déclarée EXPLICITEMENT sur ces deux relations.
     *
     * Laravel la déduit du nom de la classe parente : Str::snake() applique
     * « WhatsAppAccount » → « whats_app_account », le A majuscule de « App »
     * créant un mot supplémentaire. Il cherchait donc whats_app_account_id
     * quand la colonne s'appelle whatsapp_account_id, et toute lecture des
     * modèles ou des conversations d'un compte partait en erreur SQL.
     *
     * Le sens inverse (belongsTo depuis MessageTemplate) fonctionnait, lui :
     * la clé y est déduite du nom de la MÉTHODE, `whatsappAccount`, qui ne
     * contient pas de majuscule fautive. D'où un défaut qui ne se manifestait
     * que dans un sens.
     */

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class, 'whatsapp_account_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'whatsapp_account_id');
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
