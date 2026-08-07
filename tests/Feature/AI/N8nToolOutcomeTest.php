<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\WhatsAppAccount;
use App\Services\AI\Tools\BookAppointmentTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
| Issue réelle d'un outil délégué à n8n.
|
| Le tableau de bord commercial compte les marqueurs `appointment_booked` et
| `quote_sent`. Les inscrire sur un échec rendrait la métrique mensongère — ce
| que faisait la détection par recherche de mots dans le texte destiné au LLM.
*/

beforeEach(function () {
    [$this->tenant, $this->user] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $this->agent = Agent::factory()->create([
        'is_active' => true,
        'settings'  => ['n8n_webhook_url' => 'https://n8n.test/webhook/nonalix'],
    ]);

    $account = WhatsAppAccount::factory()->create();
    $this->conversation = Conversation::factory()->for($account, 'whatsappAccount')->create();

    $this->lead = Lead::factory()->create([
        'contact_id' => $this->conversation->contact_id,
    ]);
});

$arguments = ['date' => '2026-09-01', 'time' => '14:00', 'reason' => 'Visite'];

it('inscrit le rendez-vous lorsque n8n confirme', function () use ($arguments) {
    Http::fake(['n8n.test/*' => Http::response(['message' => 'Rendez-vous enregistré.'], 200)]);

    $message = app(BookAppointmentTool::class)->execute($arguments, $this->conversation);

    expect($message)->toBe('Rendez-vous enregistré.');
    expect($this->lead->fresh()->qualification['appointment_booked'] ?? null)->toBeTrue();
});

it('n\'inscrit rien lorsque n8n renvoie une erreur', function () use ($arguments) {
    Http::fake(['n8n.test/*' => Http::response('boom', 500)]);

    app(BookAppointmentTool::class)->execute($arguments, $this->conversation);

    expect($this->lead->fresh()->qualification['appointment_booked'] ?? null)->toBeNull();
});

it('n\'inscrit rien lorsque le webhook est injoignable', function () use ($arguments) {
    Http::fake(fn () => throw new ConnectionException('injoignable'));

    app(BookAppointmentTool::class)->execute($arguments, $this->conversation);

    expect($this->lead->fresh()->qualification['appointment_booked'] ?? null)->toBeNull();
});

it('n\'inscrit rien lorsque l\'URL du webhook n\'est pas configurée', function () use ($arguments) {
    // Cas le plus insidieux : aucun appel réseau n'a lieu, et l'ancien code
    // classait ce refus parmi les succès.
    $this->agent->forceFill(['settings' => []])->save();

    Http::fake();

    $message = app(BookAppointmentTool::class)->execute($arguments, $this->conversation);

    expect($message)->toContain('webhook');
    expect($this->lead->fresh()->qualification['appointment_booked'] ?? null)->toBeNull();
});

it('utilise le webhook de l\'agent affecté à la conversation', function () use ($arguments) {
    // L'agent par défaut de l'entreprise n'est pas forcément celui qui mène
    // cette conversation : c'est sa configuration à lui qui doit servir.
    $dedie = Agent::factory()->create([
        'is_active' => false,
        'settings'  => ['n8n_webhook_url' => 'https://dedie.test/webhook'],
    ]);

    $this->conversation->forceFill(['agent_id' => $dedie->id])->save();

    Http::fake(['dedie.test/*' => Http::response(['message' => 'OK'], 200)]);

    app(BookAppointmentTool::class)->execute($arguments, $this->conversation->fresh());

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://dedie.test/'));
});
