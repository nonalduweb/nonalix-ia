<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OptInStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $waId = '33'.$this->faker->unique()->numerify('#########');

        return [
            'wa_id'           => $waId,
            'phone_number'    => '+'.$waId,
            'name'            => $this->faker->name(),
            'profile_name'    => $this->faker->firstName(),
            'opt_in_status'   => OptInStatus::Unknown,
            'attributes'      => [],
            'last_message_at' => now(),
        ];
    }

    public function optedOut(): static
    {
        return $this->state(fn () => [
            'opt_in_status' => OptInStatus::OptedOut,
            'opt_out_at'    => now(),
        ]);
    }
}
