<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'contact_id', 'conversation_id', 'status', 'score', 'qualification',
        'intent', 'source', 'assigned_user_id', 'qualified_at', 'qualified_by',
        'lost_reason', 'next_action_at',
    ];

    protected function casts(): array
    {
        return [
            'status'         => LeadStatus::class,
            'score'          => 'integer',
            'qualification'  => 'array',
            'qualified_at'   => 'immutable_datetime',
            'next_action_at' => 'immutable_datetime',
        ];
    }

    protected $attributes = [
        'qualification' => '{}',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Enregistre une qualification produite par l'agent IA.
     *
     * `qualified_by` distingue l'automatique de l'humain : une équipe
     * commerciale ne traite pas de la même façon un score calculé par un LLM
     * et une qualification validée par un collègue.
     */
    public function applyAiQualification(array $answers, int $score, ?string $intent = null): void
    {
        $this->forceFill([
            'qualification' => array_merge($this->qualification ?? [], $answers),
            'score'         => max(0, min(100, $score)),
            'intent'        => $intent ?? $this->intent,
            'status'        => $score >= 50 ? LeadStatus::Qualified : LeadStatus::Unqualified,
            'qualified_at'  => now(),
            'qualified_by'  => 'ai',
        ])->save();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            LeadStatus::Won->value,
            LeadStatus::Lost->value,
            LeadStatus::Unqualified->value,
        ]);
    }
}
