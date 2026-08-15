<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Prestation vendue par l'entreprise.
 *
 * C'est la SEULE source de tarifs communiquée à l'agent IA. Les prix ne sont
 * jamais laissés à la génération : un montant inventé par un LLM engage
 * commercialement le client.
 */
class Service extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name', 'description', 'price_cents', 'price_type', 'currency',
        'duration_minutes', 'category', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'price_cents'      => 'integer',
            'duration_minutes' => 'integer',
            'is_active'        => 'boolean',
            'position'         => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    /**
     * Libellé de prix prêt à être lu par un humain — et par l'agent.
     *
     * Délégué à Money : ce libellé part tel quel dans le prompt et dans la
     * réponse de l'agent. Un facteur cent appliqué au franc CFA y annonçait
     * « 150,00 XOF » pour une prestation facturée 15 000 F CFA.
     */
    public function formattedPrice(): string
    {
        if ($this->price_type === 'quote' || $this->price_cents === null) {
            return 'sur devis';
        }

        $amount = Money::format($this->price_cents, $this->currency);

        return match ($this->price_type) {
            'from'   => 'à partir de '.$amount,
            'hourly' => $amount.' / heure',
            default  => $amount,
        };
    }
}
