<?php

declare(strict_types=1);

use App\Jobs\WhatsApp\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [$this->tenant] = $this->createTenantWithUser();
    $this->actingForTenant($this->tenant);

    $this->account = WhatsAppAccount::factory()->create([
        'app_secret'           => 'secret-de-test',
        'webhook_verify_token' => 'jeton-de-verification',
    ]);

    $this->url = "http://api.nonalixia.test/webhooks/whatsapp/{$this->tenant->id}";
});

/** Payload Meta minimal contenant un message texte entrant. */
function inboundPayload(string $phoneNumberId, string $wamid = 'wamid.TEST123'): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry'  => [[
            'id'      => '123456',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata'          => ['phone_number_id' => $phoneNumberId],
                    'contacts'          => [['wa_id' => '33612345678', 'profile' => ['name' => 'Marie']]],
                    'messages'          => [[
                        'id'        => $wamid,
                        'from'      => '33612345678',
                        'timestamp' => (string) now()->timestamp,
                        'type'      => 'text',
                        'text'      => ['body' => 'Bonjour, quels sont vos tarifs ?'],
                    ]],
                ],
            ]],
        ]],
    ];
}

function sign(array $payload, string $secret): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return [
        'body'      => $body,
        'signature' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ];
}

it('valide le handshake de vérification Meta', function () {
    $this->get($this->url.'?'.http_build_query([
        'hub_mode'         => 'subscribe',
        'hub_verify_token' => 'jeton-de-verification',
        'hub_challenge'    => '9876543',
    ]))->assertOk()->assertSee('9876543');
});

it('refuse le handshake si le jeton est incorrect', function () {
    $this->get($this->url.'?'.http_build_query([
        'hub_mode'         => 'subscribe',
        'hub_verify_token' => 'mauvais-jeton',
        'hub_challenge'    => '9876543',
    ]))->assertForbidden();
});

it('rejette un webhook dont la signature est invalide', function () {
    Queue::fake();

    $payload = inboundPayload($this->account->phone_number_id);
    $signed  = sign($payload, 'mauvais-secret');

    $this->call('POST', $this->url, [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signed['signature'],
        'CONTENT_TYPE'             => 'application/json',
    ], $signed['body'])->assertUnauthorized();

    // Aucune donnée persistée, aucun job mis en file.
    expect(WebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('accepte un webhook signé et met le traitement en file', function () {
    Queue::fake();

    $payload = inboundPayload($this->account->phone_number_id);
    $signed  = sign($payload, 'secret-de-test');

    $this->call('POST', $this->url, [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signed['signature'],
        'CONTENT_TYPE'             => 'application/json',
    ], $signed['body'])->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(WebhookEvent::query()->first()->signature_valid)->toBeTrue();

    Queue::assertPushed(ProcessWebhookEventJob::class);
});

it('est idempotent : un rejeu du même webhook ne crée qu\'un seul événement', function () {
    Queue::fake();

    $payload = inboundPayload($this->account->phone_number_id);
    $signed  = sign($payload, 'secret-de-test');

    $headers = [
        'HTTP_X-Hub-Signature-256' => $signed['signature'],
        'CONTENT_TYPE'             => 'application/json',
    ];

    // Meta rejoue tant qu'il n'a pas reçu de 200, et peut livrer plusieurs
    // fois le même événement : le doublon doit être absorbé silencieusement.
    $this->call('POST', $this->url, [], [], [], $headers, $signed['body'])->assertOk();
    $this->call('POST', $this->url, [], [], [], $headers, $signed['body'])->assertOk();
    $this->call('POST', $this->url, [], [], [], $headers, $signed['body'])->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1);
});

it('acquitte en 200 un webhook destiné à un tenant inconnu', function () {
    $unknown = 'http://api.nonalixia.test/webhooks/whatsapp/'.\Illuminate\Support\Str::uuid7();

    // Un 404 pousserait Meta à rejouer indéfiniment un webhook que nous ne
    // saurons jamais traiter.
    $this->postJson($unknown, [])->assertOk()->assertJson(['status' => 'ignored']);
});
