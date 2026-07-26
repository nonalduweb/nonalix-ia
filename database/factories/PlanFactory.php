<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name'        => Str::title($name),
            'slug'        => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price_cents' => 4900,
            'currency'    => 'EUR',
            'interval'    => 'month',
            // Quotas généreux par défaut : un test ne doit jamais échouer
            // parce qu'il a atteint une limite qu'il ne teste pas.
            'quotas' => [
                'messages_sent'     => 100_000,
                'messages_received' => 100_000,
                'ai_requests'       => 100_000,
                'ai_input_tokens'   => 10_000_000,
                'ai_output_tokens'  => 10_000_000,
                'documents_stored'  => 1_000,
            ],
            'features'       => ['rag' => true, 'api_access' => true],
            'overage_policy' => 'block',
            'is_active'      => true,
            'is_public'      => true,
            'position'       => 0,
        ];
    }

    /** Plan volontairement restrictif, pour tester le blocage des quotas. */
    public function limited(array $quotas = []): static
    {
        return $this->state(fn () => [
            'quotas' => array_merge(['messages_sent' => 1, 'ai_requests' => 1], $quotas),
        ]);
    }
}
