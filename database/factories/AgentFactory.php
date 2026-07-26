<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agent> */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name'          => 'Assistant',
            'provider'      => AiProvider::OpenAI,
            'model'         => 'gpt-4.1-mini',
            'temperature'   => 0.3,
            'max_tokens'    => 1024,
            'system_prompt' => 'Tu réponds aux clients de manière concise et professionnelle.',
            'tone'          => 'professionnel',
            'language'      => 'fr',
            'fallback_message'  => "Je ne peux pas répondre pour le moment, un conseiller vous recontacte.",
            'memory_window'     => 12,
            'rag_enabled'       => true,
            'rag_top_k'         => 5,
            'rag_min_score'     => 0.75,
            'handover_keywords' => ['humain', 'conseiller'],
            'enabled_tools'     => ['request_human_handover', 'list_services', 'get_business_hours'],
            'is_active'         => true,
            'settings'          => [],
        ];
    }

    public function withoutRag(): static
    {
        return $this->state(fn () => ['rag_enabled' => false]);
    }
}
