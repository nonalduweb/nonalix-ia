<?php

declare(strict_types=1);

use App\Services\WhatsApp\WebhookSignatureVerifier;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->verifier = new WebhookSignatureVerifier;
    $this->secret   = 'secret-applicatif-meta';
});

function requestWithBody(string $body, ?string $signature): Request
{
    $request = Request::create('/webhooks/whatsapp/x', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    if ($signature !== null) {
        $request->headers->set('X-Hub-Signature-256', $signature);
    }

    return $request;
}

it('valide une signature correcte', function () {
    $body      = '{"object":"whatsapp_business_account","entry":[]}';
    $signature = 'sha256='.hash_hmac('sha256', $body, $this->secret);

    expect($this->verifier->verify(requestWithBody($body, $signature), $this->secret))->toBeTrue();
});

it('rejette une signature calculée avec un autre secret', function () {
    $body      = '{"a":1}';
    $signature = 'sha256='.hash_hmac('sha256', $body, 'autre-secret');

    expect($this->verifier->verify(requestWithBody($body, $signature), $this->secret))->toBeFalse();
});

it('rejette une requête sans en-tête de signature', function () {
    expect($this->verifier->verify(requestWithBody('{"a":1}', null), $this->secret))->toBeFalse();
});

it('rejette une signature sans le préfixe sha256=', function () {
    $body = '{"a":1}';

    expect($this->verifier->verify(
        requestWithBody($body, hash_hmac('sha256', $body, $this->secret)),
        $this->secret,
    ))->toBeFalse();
});

it('rejette la vérification si aucun secret n\'est configuré', function () {
    $body = '{"a":1}';

    expect($this->verifier->verify(
        requestWithBody($body, 'sha256='.hash_hmac('sha256', $body, $this->secret)),
        null,
    ))->toBeFalse();
});

it('calcule le HMAC sur le corps brut, pas sur un JSON réencodé', function () {
    // Ordre des clés et échappement Unicode diffèrent après un aller-retour
    // json_decode/json_encode : le HMAC ne correspondrait plus.
    $body      = '{"z":"café","a":1}';
    $signature = 'sha256='.hash_hmac('sha256', $body, $this->secret);

    expect($this->verifier->verify(requestWithBody($body, $signature), $this->secret))->toBeTrue();

    $reencoded = json_encode(json_decode($body, true), JSON_THROW_ON_ERROR);

    expect($reencoded)->not->toBe($body)
        ->and(hash_hmac('sha256', $reencoded, $this->secret))
        ->not->toBe(hash_hmac('sha256', $body, $this->secret));
});
