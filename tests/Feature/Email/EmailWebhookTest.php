<?php

declare(strict_types=1);

use App\Jobs\AI\GenerateAgentReplyJob;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

/*
| Reception du courrier entrant.
|
| Le point d'entree est public : il doit etre ferme a qui ne presente pas le
| secret, et ne resoudre l'entreprise que par l'adresse QUE NOUS avons frappee.
*/

beforeEach(function () {
    config(['nonalix.email.webhook_secret' => 'secret-de-test']);
    config(['nonalix.email.inbound_domain' => 'in.nonalixia.test']);

    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->tenant->forceFill(['inbound_email_token' => 'abcdefghijklmnopqrstuvwx'])->save();

    $this->actingForTenant($this->tenant);
    Agent::factory()->create(['is_active' => true]);

    $this->url = 'http://api.nonalixia.test/webhooks/email/secret-de-test';
});

function courrier(array $overrides = []): array
{
    return array_merge([
        'sender'    => 'Awa Kone <awa@exemple.test>',
        'recipient' => 'in-abcdefghijklmnopqrstuvwx@in.nonalixia.test',
        'subject'   => 'Demande de devis',
        'body'      => 'Bonjour, quels sont vos tarifs ?',
    ], $overrides);
}

it('refuse un secret absent ou faux', function () {
    Queue::fake();

    $this->postJson('http://api.nonalixia.test/webhooks/email/mauvais-secret', courrier())
        ->assertStatus(404);

    Queue::assertNothingPushed();
});

it('reste ferme si aucun secret n\'est configure', function () {
    config(['nonalix.email.webhook_secret' => null]);

    // Un secret vide ne doit jamais valoir « laisse passer tout le monde ».
    $this->postJson('http://api.nonalixia.test/webhooks/email/', courrier())
        ->assertStatus(404);
});

it('enregistre le courrier et declenche l\'agent', function () {
    Queue::fake();

    $this->postJson($this->url, courrier())
        ->assertOk()
        ->assertJson(['status' => 'queued']);

    $this->actingForTenant($this->tenant);

    $conversation = Conversation::query()->where('channel', 'email')->first();

    expect($conversation)->not->toBeNull()
        // Une conversation e-mail n'emprunte JAMAIS le compte WhatsApp d'un
        // autre tenant pour satisfaire la cle etrangere.
        ->and($conversation->whatsapp_account_id)->toBeNull();

    expect(Message::query()->where('body', 'like', '%tarifs%')->exists())->toBeTrue();

    Queue::assertPushed(GenerateAgentReplyJob::class);
});

it('ne resout l\'entreprise que par l\'adresse frappee', function () {
    Queue::fake();

    // Le champ `business_profiles.email` est une saisie libre : il ne doit
    // plus servir a router du courrier.
    $this->postJson($this->url, courrier(['recipient' => 'contact@restaurant.test']))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    Queue::assertNothingPushed();
});

it('ignore un jeton inconnu sans creer de conversation', function () {
    Queue::fake();

    $this->postJson($this->url, courrier(['recipient' => 'in-zzzzzzzzzzzzzzzzzzzzzzzz@in.nonalixia.test']))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    Queue::assertNothingPushed();
});

it('ignore son propre expediteur, pour ne pas boucler', function () {
    Queue::fake();

    // Une redirection mal reglee peut nous renvoyer nos propres reponses.
    $this->postJson($this->url, courrier(['sender' => 'bonjour@in.nonalixia.test']))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    Queue::assertNothingPushed();
});

it('valide la presence de l\'expediteur et du destinataire', function () {
    $this->postJson($this->url, courrier(['sender' => '']))->assertStatus(422);
});

it('reutilise la conversation e-mail en cours', function () {
    Queue::fake();

    $this->postJson($this->url, courrier())->assertOk();
    $this->postJson($this->url, courrier(['subject' => 'Relance']))->assertOk();

    $this->actingForTenant($this->tenant);

    expect(Conversation::query()->where('channel', 'email')->count())->toBe(1);
    expect(Contact::query()->count())->toBe(1);
});

it('marque la redirection verifiee a l\'arrivee du message sonde', function () {
    Queue::fake();

    $this->tenant->forceFill(['email_probe_code' => 'PROBE12345'])->save();

    $this->postJson($this->url, courrier([
        'subject' => 'Vérification de votre canal e-mail Nonalix IA [PROBE12345]',
    ]))->assertOk()->assertJson(['status' => 'verified']);

    $fresh = Tenant::query()->find($this->tenant->id);

    expect($fresh->email_forward_verified_at)->not->toBeNull()
        ->and($fresh->email_probe_code)->toBeNull();

    // La sonde ne doit surtout pas devenir une conversation.
    $this->actingForTenant($this->tenant);
    expect(Conversation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('cloisonne les entreprises', function () {
    Queue::fake();

    [$autre] = $this->createTenantWithUser();
    $autre->forceFill(['inbound_email_token' => 'zyxwvutsrqponmlkjihgfedc'])->save();

    $this->postJson($this->url, courrier())->assertOk();

    // Le courrier appartient au tenant dont l'adresse a ete visee, pas a l'autre.
    $this->actingForTenant($autre);
    expect(Conversation::query()->count())->toBe(0);

    $this->actingForTenant($this->tenant);
    expect(Conversation::query()->count())->toBe(1);
});
