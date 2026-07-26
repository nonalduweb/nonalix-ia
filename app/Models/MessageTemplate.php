<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'whatsapp_account_id', 'meta_template_id', 'name', 'language',
        'category', 'status', 'rejected_reason', 'components', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'synced_at'  => 'immutable_datetime',
        ];
    }

    protected $attributes = [
        'components' => '[]',
    ];

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    /** Seul un template approuvé peut sortir hors de la fenêtre de 24 h. */
    public function isUsable(): bool
    {
        return $this->status === 'approved';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Nombre de variables attendues par le corps du template.
     *
     * Envoyer un template avec le mauvais nombre de paramètres provoque un
     * rejet Meta ; on le vérifie donc avant l'appel.
     */
    public function bodyParameterCount(): int
    {
        foreach ($this->components ?? [] as $component) {
            if (($component['type'] ?? null) === 'BODY') {
                preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $component['text'] ?? '', $matches);

                return $matches[1] === [] ? 0 : max(array_map('intval', $matches[1]));
            }
        }

        return 0;
    }
}
