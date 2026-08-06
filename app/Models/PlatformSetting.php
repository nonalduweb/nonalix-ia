<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Réglage de la plateforme, saisi depuis la super-administration.
 *
 * Modèle central : ces valeurs valent pour toute la plateforme et
 * n'appartiennent à aucune entreprise.
 */
class PlatformSetting extends Model
{
    use HasUuidPrimaryKey;

    /** Les lectures sont fréquentes (chaque appel IA) et les écritures rares. */
    private const CACHE_TTL = 300;

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        // Chiffré au repos : ces valeurs sont des jetons de fournisseurs IA.
        return ['value' => 'encrypted'];
    }

    protected $hidden = ['value'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Valeur d'un réglage, ou null s'il n'est pas défini. */
    public static function get(string $key): ?string
    {
        $value = Cache::remember(
            self::cacheKey($key),
            self::CACHE_TTL,
            // Sentinelle : sans elle, un réglage absent serait relu en base à
            // chaque appel, le cache ne sachant pas mémoriser « rien ».
            fn () => static::query()->where('key', $key)->value('value') ?? '',
        );

        return $value === '' ? null : $value;
    }

    public static function put(string $key, ?string $value, ?string $userId = null): void
    {
        // Chaîne vide = suppression : un champ vidé dans l'interface doit
        // effacer le réglage, pas enregistrer une clé vide qui masquerait
        // celle du .env.
        if ($value === null || trim($value) === '') {
            static::query()->where('key', $key)->delete();
        } else {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $userId],
            );
        }

        Cache::forget(self::cacheKey($key));
    }

    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    private static function cacheKey(string $key): string
    {
        return "platform_setting:{$key}";
    }
}
