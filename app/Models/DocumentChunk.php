<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fragment de document et son embedding.
 *
 * La colonne `embedding` (type pgvector) n'est jamais chargée par Eloquent :
 * 1536 flottants par ligne saturerait la mémoire sur n'importe quelle liste.
 * Les écritures et les recherches passent par VectorSearchService, en SQL.
 */
class DocumentChunk extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    public $timestamps = false;

    /**
     * `embedding` est volontairement absente : 1536 flottants ne passent pas
     * par l'assignation de masse. Son écriture se fait en SQL paramétré, via
     * VectorSearchService::storeEmbedding().
     */
    protected $fillable = [
        'document_id', 'position', 'content', 'tokens', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'position'   => 'integer',
            'tokens'     => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected $attributes = [
        'metadata' => '{}',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Encode un vecteur au format littéral attendu par pgvector : `[0.1,0.2]`.
     *
     * @param  array<int, float>  $vector
     */
    public static function encodeVector(array $vector): string
    {
        return '['.implode(',', array_map(
            static fn (float $v): string => rtrim(rtrim(sprintf('%.8F', $v), '0'), '.') ?: '0',
            $vector,
        )).']';
    }
}
