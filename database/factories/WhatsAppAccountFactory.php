<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WhatsAppAccountStatus;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WhatsAppAccount> */
class WhatsAppAccountFactory extends Factory
{
    protected $model = WhatsAppAccount::class;

    public function definition(): array
    {
        return [
            'waba_id'              => (string) $this->faker->unique()->numerify('###############'),
            'phone_number_id'      => (string) $this->faker->unique()->numerify('###############'),
            'display_phone_number' => '+33'.$this->faker->numerify('#########'),
            'verified_name'        => $this->faker->company(),
            // Valeurs factices : aucun test ne doit dépendre d'un vrai jeton
            // Meta, et aucun secret réel ne doit figurer dans le dépôt.
            'access_token'         => 'test-access-token-'.Str::random(24),
            'app_secret'           => 'test-app-secret-'.Str::random(24),
            'webhook_verify_token' => Str::random(48),
            'status'               => WhatsAppAccountStatus::Connected,
            'connected_at'         => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'       => WhatsAppAccountStatus::Pending,
            'connected_at' => null,
        ]);
    }
}
