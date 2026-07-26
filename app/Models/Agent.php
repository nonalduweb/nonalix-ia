<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiProvider;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuration de l'agent IA d'une entreprise.
 *
 * Tout ce qui définit le comportement de l'agent est ici, en base : changer de
 * fournisseur, de modèle ou de ton ne demande aucun déploiement.
 */
class Agent extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name', 'provider', 'model', 'api_key', 'temperature', 'max_tokens',
        'system_prompt', 'persona', 'tone', 'language',
        'greeting_message', 'fallback_message',
        'memory_window', 'rag_enabled', 'rag_top_k', 'rag_min_score',
        'handover_keywords', 'enabled_tools', 'active_hours_only',
        'is_active', 'settings',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'provider'          => AiProvider::class,
            // Clé propre au tenant : chiffrée au repos.
            'api_key'           => 'encrypted',
            'temperature'       => 'float',
            'max_tokens'        => 'integer',
            'memory_window'     => 'integer',
            'rag_enabled'       => 'boolean',
            'rag_top_k'         => 'integer',
            'rag_min_score'     => 'float',
            'handover_keywords' => 'array',
            'enabled_tools'     => 'array',
            'active_hours_only' => 'boolean',
            'is_active'         => 'boolean',
            'settings'          => 'array',
        ];
    }

    protected $attributes = [
        'handover_keywords' => '[]',
        'enabled_tools'     => '[]',
        'settings'          => '{}',
    ];

    /**
     * Le message contient-il un mot-clé de transfert vers un humain ?
     *
     * Vérifié AVANT tout appel au LLM : un client qui demande explicitement un
     * conseiller ne doit pas attendre une génération, ni risquer que l'agent
     * décide de répondre lui-même.
     */
    public function detectsHandoverRequest(?string $text): bool
    {
        if ($text === null || $this->handover_keywords === []) {
            return false;
        }

        $haystack = mb_strtolower($text);

        foreach ($this->handover_keywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    public function allowsTool(string $tool): bool
    {
        return in_array($tool, $this->enabled_tools ?? [], true);
    }

    public function effectiveFallbackMessage(): string
    {
        return $this->fallback_message
            ?: "Je ne suis pas en mesure de répondre pour le moment. "
              ."Un conseiller vous recontacte au plus vite.";
    }
}
