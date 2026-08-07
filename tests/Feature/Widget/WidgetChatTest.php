<?php

declare(strict_types=1);

use App\Jobs\AI\GenerateAgentReplyJob;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;

/*
| API publique du widget de chat.
|
| Elle est appelée depuis le site du client — une origine tierce, sans session
| ni jeton CSRF. Ces tests fixent ce contrat : toute régression qui replacerait
| ces routes derrière la pile `web` les ferait échouer en 419.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $this->agent = Agent::factory()->create(['is_active' => true]);
});

it('ouvre une conversation web pour une entreprise sans compte WhatsApp', function () {
    Queue::fake();

    // Le cas nominal d'un client « widget seul » : aucun compte WhatsApp n'a
    // jamais été connecté. La colonne whatsapp_account_id doit l'accepter.
    expect($this->tenant->whatsappAccounts()->count())->toBe(0);

    $this->postJson("http://localhost/widget/chat/{$this->tenant->id}", [
        'session_id' => 'abc123',
        'body'       => 'Bonjour, vous êtes ouverts ce soir ?',
    ])->assertOk()->assertJson(['status' => 'queued']);

    $this->actingForTenant($this->tenant);

    $conversation = Conversation::query()->where('channel', 'web')->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->whatsapp_account_id)->toBeNull()
        ->and($conversation->tenant_id)->toBe($this->tenant->id);

    Queue::assertPushed(GenerateAgentReplyJob::class);
});

it('accepte le message sans jeton CSRF', function () {
    Queue::fake();

    // `post` et non `postJson` : la requête passe par la vérification CSRF si
    // la route est restée dans la pile `web`. Un 419 signalerait la régression.
    $this->post("http://localhost/widget/chat/{$this->tenant->id}", [
        'session_id' => 'sans-jeton',
        'body'       => 'Bonjour',
    ])->assertOk();
});

it('enregistre le message entrant du visiteur', function () {
    Queue::fake();

    $this->postJson("http://localhost/widget/chat/{$this->tenant->id}", [
        'session_id' => 'abc123',
        'body'       => 'Je cherche un devis',
    ])->assertOk();

    $this->actingForTenant($this->tenant);

    $contact = Contact::query()->where('wa_id', 'web_abc123')->first();

    expect($contact)->not->toBeNull();
    expect(Message::query()->where('body', 'Je cherche un devis')->exists())->toBeTrue();
});

it('réutilise la conversation web en cours pour une même session', function () {
    Queue::fake();

    foreach (['Premier', 'Second'] as $body) {
        $this->postJson("http://localhost/widget/chat/{$this->tenant->id}", [
            'session_id' => 'session-stable',
            'body'       => $body,
        ])->assertOk();
    }

    $this->actingForTenant($this->tenant);

    expect(Conversation::query()->where('channel', 'web')->count())->toBe(1);
});

it('expose la configuration de l\'agent actif', function () {
    $this->agent->forceFill([
        'name'             => 'Léon',
        'greeting_message' => 'Bonjour !',
        'settings'         => ['theme_color' => '#123456'],
    ])->save();

    $this->getJson("http://localhost/widget/config/{$this->tenant->id}")
        ->assertOk()
        ->assertJson([
            'agent_name'       => 'Léon',
            'greeting_message' => 'Bonjour !',
            'theme_color'      => '#123456',
        ]);
});

it('ne renvoie l\'historique qu\'à qui présente l\'identifiant de session', function () {
    Queue::fake();

    $this->postJson("http://localhost/widget/chat/{$this->tenant->id}", [
        'session_id' => 'secret-visiteur',
        'body'       => 'Message confidentiel',
    ])->assertOk();

    // Sans session_id : aucune fuite de l'historique.
    $this->getJson("http://localhost/widget/config/{$this->tenant->id}")
        ->assertOk()
        ->assertJsonPath('messages', []);

    // Avec le bon identifiant : l'historique est restitué.
    $this->getJson("http://localhost/widget/config/{$this->tenant->id}?session_id=secret-visiteur")
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Message confidentiel');
});

it('refuse une entreprise inconnue', function () {
    $this->getJson('http://localhost/widget/config/'.\Illuminate\Support\Str::uuid()->toString())
        ->assertStatus(404);
});

it('valide le corps du message', function () {
    $this->postJson("http://localhost/widget/chat/{$this->tenant->id}", [
        'session_id' => 'abc',
        'body'       => '',
    ])->assertStatus(422);
});
