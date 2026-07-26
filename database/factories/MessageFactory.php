<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'wamid'           => 'wamid.'.Str::upper(Str::random(32)),
            'direction'       => MessageDirection::Inbound,
            'sender_type'     => SenderType::Contact,
            'type'            => MessageType::Text,
            'body'            => $this->faker->sentence(),
            'status'          => MessageStatus::Delivered,
            'delivered_at'    => now(),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'direction'   => MessageDirection::Outbound,
            'sender_type' => SenderType::Ai,
            'status'      => MessageStatus::Sent,
            'sent_at'     => now(),
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'direction'    => MessageDirection::Outbound,
            'sender_type'  => SenderType::Agent,
            'status'       => MessageStatus::Queued,
            // Pas encore de wamid : il est attribué par Meta à l'envoi.
            'wamid'        => null,
            'sent_at'      => null,
            'delivered_at' => null,
        ]);
    }
}
