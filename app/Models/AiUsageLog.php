<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiProvider;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Détail d'un appel à un fournisseur IA.
 *
 * Base de la facturation à l'usage et du suivi de marge : sans cette table, on
 * ne sait pas ce que coûte réellement un client.
 */
class AiUsageLog extends Model
{
    use BelongsToTenant;
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'agent_id', 'provider', 'model', 'operation',
        'input_tokens', 'output_tokens', 'cost_micros', 'latency_ms',
        'status', 'error_code', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'provider'      => AiProvider::class,
            'input_tokens'  => 'integer',
            'output_tokens' => 'integer',
            // Micro-centimes d'euro. Entier : aucun arrondi flottant sur un coût.
            'cost_micros'   => 'integer',
            'latency_ms'    => 'integer',
            'created_at'    => 'immutable_datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** Coût en euros, pour affichage uniquement. */
    public function costInEuros(): float
    {
        return $this->cost_micros / 100_000_000;
    }
}
