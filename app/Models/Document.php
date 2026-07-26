<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document source de la base de connaissances.
 *
 * Le cycle de vie (pending → extracting → chunking → embedding → ready) est
 * exposé tel quel au client : une ingestion qui prend du temps doit être
 * visible, pas silencieuse.
 */
class Document extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = [
        'title', 'source_type', 'source_url', 'storage_path', 'mime_type',
        'size_bytes', 'checksum', 'status', 'error', 'chunks_count',
        'tokens_count', 'embedding_provider', 'embedding_model',
        'processed_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'status'       => DocumentStatus::class,
            'size_bytes'   => 'integer',
            'chunks_count' => 'integer',
            'tokens_count' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => DocumentStatus::Failed,
            'error'  => mb_substr($reason, 0, 2000),
        ])->save();
    }

    /**
     * L'espace vectoriel du document correspond-il à celui configuré ?
     *
     * Un document indexé avec un ancien modèle produit des scores de
     * similarité incomparables : il doit être réindexé, pas interrogé.
     */
    public function usesCurrentEmbeddingSpace(): bool
    {
        return $this->embedding_provider === config('ai.embeddings.provider')
            && $this->embedding_model === config('ai.embeddings.model');
    }

    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Ready->value);
    }
}
