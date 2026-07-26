<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name'          => $name,
            'slug'          => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status'        => TenantStatus::Active,
            'plan_id'       => Plan::factory(),
            'trial_ends_at' => null,
            'quota_overrides' => [],
            'settings'      => [],
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'status'        => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status'            => TenantStatus::Suspended,
            'suspended_at'      => now(),
            'suspension_reason' => 'Test de suspension.',
        ]);
    }
}
