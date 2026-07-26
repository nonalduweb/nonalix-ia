<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = ['day_of_week', 'opens_at', 'closes_at', 'is_closed'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed'   => 'boolean',
        ];
    }

    public const DAYS = [
        0 => 'dimanche', 1 => 'lundi', 2 => 'mardi', 3 => 'mercredi',
        4 => 'jeudi',    5 => 'vendredi', 6 => 'samedi',
    ];

    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    /**
     * L'entreprise est-elle ouverte à cet instant ?
     *
     * `$moment` doit déjà être exprimé dans le fuseau de l'entreprise : les
     * horaires sont saisis en heure locale, alors que tout le reste de la base
     * est en UTC. La conversion est de la responsabilité de l'appelant, pour
     * qu'elle soit visible plutôt que cachée ici.
     *
     * @param  Collection<int, self>  $hours
     */
    public static function isOpenAt(Collection $hours, CarbonImmutable $moment): bool
    {
        $today = $hours->where('day_of_week', (int) $moment->dayOfWeek);

        if ($today->isEmpty()) {
            return false;
        }

        $time = $moment->format('H:i:s');

        foreach ($today as $slot) {
            if ($slot->is_closed || $slot->opens_at === null || $slot->closes_at === null) {
                continue;
            }

            if ($time >= $slot->opens_at && $time <= $slot->closes_at) {
                return true;
            }
        }

        return false;
    }
}
