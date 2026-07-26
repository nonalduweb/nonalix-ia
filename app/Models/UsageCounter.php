<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Consommation consolidée par tenant, métrique et mois.
 *
 * Redis porte le compteur temps réel (rapide mais volatil) ; cette table est
 * la trace durable, réconciliée toutes les quinze minutes. En cas de perte de
 * Redis, on repart d'ici plutôt que de zéro.
 */
class UsageCounter extends Model
{
    use BelongsToTenant;
    use HasUuidPrimaryKey;

    protected $fillable = ['metric', 'period', 'value', 'recorded_at'];

    protected function casts(): array
    {
        return [
            'value'       => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    /** Période de facturation courante, au format AAAA-MM. */
    public static function currentPeriod(): string
    {
        return now()->format('Y-m');
    }
}
