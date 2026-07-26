<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'contact_id'          => Contact::factory(),
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'channel'             => 'whatsapp',
            'status'              => ConversationStatus::Open,
            'ai_enabled'          => true,
            'last_message_at'     => now(),
            'last_inbound_at'     => now(),
            // Fenêtre ouverte par défaut : la plupart des tests envoient des
            // messages, et une fenêtre fermée les ferait échouer sans rapport
            // avec ce qu'ils vérifient.
            'window_expires_at'   => now()->addHours(24),
            'unread_count'        => 0,
        ];
    }

    public function windowClosed(): static
    {
        return $this->state(fn () => [
            'last_inbound_at'   => now()->subHours(30),
            'window_expires_at' => now()->subHours(6),
        ]);
    }

    public function handedOver(): static
    {
        return $this->state(fn () => [
            'ai_enabled'      => false,
            'handover_at'     => now(),
            'handover_reason' => 'demande_explicite',
            'status'          => ConversationStatus::Pending,
        ]);
    }
}
