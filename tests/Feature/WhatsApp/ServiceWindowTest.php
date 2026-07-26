<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);
    $this->account = WhatsAppAccount::factory()->create();
});

/*
| Règle Meta : hors des 24 h suivant le dernier message du contact, seul un
| template approuvé peut être envoyé. On bloque côté Nonalix plutôt que de
| laisser Meta refuser — un rejet dégrade la note de qualité du numéro.
*/

it('considère la fenêtre ouverte dans les 24 h suivant un message entrant', function () {
    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->create();

    expect($conversation->isWithinServiceWindow())->toBeTrue();
});

it('considère la fenêtre fermée au-delà de 24 h', function () {
    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->windowClosed()->create();

    expect($conversation->isWithinServiceWindow())->toBeFalse();
});

it('recalcule la fenêtre à chaque message entrant', function () {
    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->windowClosed()->create();

    $conversation->refreshServiceWindow(now()->toImmutable());
    $conversation->save();

    expect($conversation->fresh()->isWithinServiceWindow())->toBeTrue();
});

it('refuse un message libre hors fenêtre depuis l\'interface', function () {
    Queue::fake();

    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->windowClosed()->create();

    $this->actingAsTenantUser($this->user)
        ->post("/conversations/{$conversation->id}/messages", ['body' => 'Bonjour'])
        ->assertSessionHasErrors('body');

    Queue::assertNotPushed(SendWhatsAppMessageJob::class);
});

it('refuse un message libre hors fenêtre via l\'API, avec un code exploitable', function () {
    Queue::fake();

    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->windowClosed()->create();

    $this->actingAsTenantUser($this->user)
        ->postJson("http://api.nonalixia.test/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Bonjour',
        ])
        ->assertStatus(422)
        ->assertJson(['code' => 'service_window_closed']);
});

it('accepte un message libre lorsque la fenêtre est ouverte', function () {
    Queue::fake();

    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->create();

    $this->actingAsTenantUser($this->user)
        ->post("/conversations/{$conversation->id}/messages", ['body' => 'Bonjour'])
        ->assertSessionHasNoErrors();

    expect(Message::query()->where('conversation_id', $conversation->id)->first()->status)
        ->toBe(MessageStatus::Queued);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('coupe l\'agent IA lorsqu\'un opérateur répond', function () {
    Queue::fake();

    $conversation = Conversation::factory()->for($this->account, 'whatsappAccount')->create(['ai_enabled' => true]);

    $this->actingAsTenantUser($this->user)
        ->post("/conversations/{$conversation->id}/messages", ['body' => 'Je prends la suite.']);

    // Sans cela, l'IA répondrait par-dessus l'opérateur et le contact
    // recevrait deux réponses potentiellement contradictoires.
    expect($conversation->fresh())
        ->ai_enabled->toBeFalse()
        ->handover_at->not->toBeNull();
});
