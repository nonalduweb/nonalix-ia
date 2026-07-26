<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentLevel;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Incident d'exploitation, visible depuis admin.nonalixia.com.
 *
 * Les occurrences identiques sont agrégées sur (tenant, source, code) : sans
 * cela, une panne de fournisseur IA produirait des milliers de lignes en
 * quelques minutes et rendrait la table inexploitable au moment précis où on
 * en a besoin.
 */
class Incident extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id', 'level', 'source', 'code', 'title', 'context',
        'occurrences', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'level'         => IncidentLevel::class,
            'context'       => 'array',
            'occurrences'   => 'integer',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at'  => 'immutable_datetime',
            'resolved_at'   => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Enregistre une occurrence : crée l'incident ou incrémente le compteur.
     *
     * Upsert atomique — plusieurs workers peuvent signaler le même incident
     * en parallèle sans provoquer de violation de contrainte.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(
        ?string $tenantId,
        IncidentLevel $level,
        string $source,
        string $code,
        string $title,
        array $context = [],
    ): void {
        $now = now();

        DB::table('incidents')->upsert(
            [[
                'id'            => (string) \Illuminate\Support\Str::uuid7(),
                'tenant_id'     => $tenantId,
                'level'         => $level->value,
                'source'        => $source,
                'code'          => $code,
                'title'         => mb_substr($title, 0, 250),
                'context'       => json_encode($context, JSON_THROW_ON_ERROR),
                'occurrences'   => 1,
                'first_seen_at' => $now,
                'last_seen_at'  => $now,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]],
            ['tenant_id', 'source', 'code'],
            [
                'occurrences'  => DB::raw('incidents.occurrences + 1'),
                'last_seen_at' => DB::raw('excluded.last_seen_at'),
                'context'      => DB::raw('excluded.context'),
                'level'        => DB::raw('excluded.level'),
                // Une nouvelle occurrence rouvre un incident déjà résolu.
                'resolved_at'  => DB::raw('NULL'),
                'updated_at'   => DB::raw('excluded.updated_at'),
            ],
        );
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')->orderByDesc('last_seen_at');
    }
}
