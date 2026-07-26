<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id'         => Tenant::factory(),
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Hash calculé une fois : bcrypt à 12 tours dans chaque factory
            // rendrait la suite de tests inutilement lente.
            'password'          => Hash::make('password'),
            'status'            => UserStatus::Active,
            'is_super_admin'    => false,
            'locale'            => 'fr',
            'remember_token'    => Str::random(10),

            // Déclarées explicitement, même à null : `Model::shouldBeStrict()`
            // est actif hors production, et un modèle issu d'une factory ne
            // contient que les attributs réellement insérés. Sans ces clés,
            // toute lecture de `two_factor_secret` lèverait une exception.
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ];
    }

    /** Membre de l'équipe NONALIX : aucun tenant, par construction. */
    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'tenant_id'      => null,
            'is_super_admin' => true,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret'         => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['ABCDE-FGHIJ', 'KLMNO-PQRST'],
            'two_factor_confirmed_at'   => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Disabled]);
    }
}
