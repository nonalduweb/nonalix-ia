<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Queue;

/*
| Droit d'ecrire de l'operateur, canal par canal.
|
| La fenetre de 24 h est une regle de Meta. L'appliquer partout empechait de
| repondre a un visiteur de son propre site : `window_expires_at` n'est posee
| que par le webhook WhatsApp, donc web et e-mail l'avaient toujours a null.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);
    Agent::factory()->create(['is_active' => true]);
});

it('laisse ecrire sur une conversation web, sans fenetre de service', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'channel'           => 'web',
        'window_expires_at' => null,
    ]);

    expect($conversation->isWritable())->toBeTrue();

    $this->actingAsTenantUser($this->user)
        ->post("/conversations/{$conversation->id}/messages", ['body' => 'Bonjour, je prends la suite.'])
        ->assertSessionHasNoErrors();

    $this->actingForTenant($this->tenant);
    expect(Message::query()->where('body', 'Bonjour, je prends la suite.')->exists())->toBeTrue();
});

it('laisse ecrire sur une conversation e-mail, sans fenetre de service', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'channel'           => 'email',
        'window_expires_at' => null,
    ]);

    expect($conversation->isWritable())->toBeTrue();
});

it('conserve la regle des 24 h sur WhatsApp', function () {
    $account = WhatsAppAccount::factory()->create();

    $ouverte = Conversation::factory()->for($account, 'whatsappAccount')->create();
    $fermee  = Conversation::factory()->for($account, 'whatsappAccount')->windowClosed()->create();

    // Meta n'accepte qu'un template hors fenetre : la regle reste entiere.
    expect($ouverte->isWritable())->toBeTrue()
        ->and($fermee->isWritable())->toBeFalse();
});
