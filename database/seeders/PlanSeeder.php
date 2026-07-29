<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Essai', 'slug' => 'essai', 'price_cents' => 0, 'currency' => 'XOF',
                'description' => '14 jours pour tester la plateforme.',
                'quotas' => [
                    'messages_sent' => 200, 'messages_received' => 500,
                    'ai_requests' => 200, 'ai_input_tokens' => 500_000,
                    'ai_output_tokens' => 200_000, 'documents_stored' => 5,
                ],
                'features' => ['rag' => true, 'api_access' => false, 'templates' => false],
                'is_public' => false, 'position' => 0,
            ],
            [
                'name' => 'Starter', 'slug' => 'starter', 'price_cents' => 15_000, 'currency' => 'XOF',
                'description' => 'Un numéro, un agent. Pour un commerce ou une petite structure.',
                'quotas' => [
                    'messages_sent' => 2_000, 'messages_received' => 5_000,
                    'ai_requests' => 2_000, 'ai_input_tokens' => 5_000_000,
                    'ai_output_tokens' => 2_000_000, 'documents_stored' => 25,
                ],
                'features' => ['rag' => true, 'api_access' => false, 'templates' => true],
                'position' => 1,
            ],
            [
                'name' => 'Business', 'slug' => 'business', 'price_cents' => 40_000, 'currency' => 'XOF',
                'description' => 'Volume soutenu, accès API et équipe de plusieurs opérateurs.',
                'quotas' => [
                    'messages_sent' => 10_000, 'messages_received' => 25_000,
                    'ai_requests' => 10_000, 'ai_input_tokens' => 25_000_000,
                    'ai_output_tokens' => 10_000_000, 'documents_stored' => 200,
                ],
                'features' => ['rag' => true, 'api_access' => true, 'templates' => true],
                'position' => 2,
            ],
            [
                'name' => 'Entreprise', 'slug' => 'entreprise', 'price_cents' => 90_000, 'currency' => 'XOF',
                'description' => 'Volumes élevés, dépassement souple, accompagnement dédié.',
                'quotas' => [
                    'messages_sent' => 50_000, 'messages_received' => 150_000,
                    'ai_requests' => 50_000, 'ai_input_tokens' => 150_000_000,
                    'ai_output_tokens' => 60_000_000, 'documents_stored' => 1_000,
                ],
                'features' => ['rag' => true, 'api_access' => true, 'templates' => true],
                // Seul plan en dépassement souple : ces clients ne doivent
                // jamais voir leur service coupé, le dépassement est facturé.
                'overage_policy' => 'soft',
                'position' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
