<?php

declare(strict_types=1);

use App\Exceptions\TenantMismatchException;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Message;
use App\Services\Tenancy\TenantContext;

/*
|------------------------------------------------------------------------------
| Isolation inter-tenant — tests BLOQUANTS
|------------------------------------------------------------------------------
| Un échec ici n'est pas un bug ordinaire : c'est une fuite de données entre
| clients, et donc la fin de la crédibilité commerciale du produit. Ces tests
| ne doivent jamais être ignorés ni contournés.
*/

it('ne retourne jamais les données d\'un autre tenant en lecture', function () {
    [$tenantA] = $this->createTenantWithUser();
    [$tenantB] = $this->createTenantWithUser();

    $this->actingForTenant($tenantA);
    $contactA = Contact::factory()->create();

    $this->actingForTenant($tenantB);
    $contactB = Contact::factory()->create();

    // Depuis B, on ne voit que B.
    $visible = Contact::query()->get();

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->id)->toBe($contactB->id)
        ->and($visible)->toBeIsolatedFrom($contactA->id);

    // L'identifiant d'un contact de A est simplement introuvable depuis B —
    // un 404, pas un 403 : on ne révèle pas l'existence de la ressource.
    expect(Contact::query()->find($contactA->id))->toBeNull();
});

it('renseigne automatiquement le tenant_id à la création', function () {
    [$tenant] = $this->createTenantWithUser();

    $this->actingForTenant($tenant);

    expect(Contact::factory()->create())->toBelongToTenant($tenant->id);
});

it('refuse de modifier une ressource appartenant à un autre tenant', function () {
    [$tenantA] = $this->createTenantWithUser();
    [$tenantB] = $this->createTenantWithUser();

    $this->actingForTenant($tenantA);
    $contactA = Contact::factory()->create();

    // On force la récupération hors scope, comme le ferait un bug ou une
    // désactivation involontaire du scope plus haut dans la pile.
    $this->actingForTenant($tenantB);
    $leaked = Contact::withoutTenantScope()->find($contactA->id);

    expect($leaked)->not->toBeNull();

    // Deuxième barrière : l'écriture est refusée même si la lecture a fuité.
    expect(fn () => $leaked->update(['name' => 'Détourné']))
        ->toThrow(TenantMismatchException::class);
});

it('lève une exception si une requête est exécutée sans tenant en contexte', function () {
    app(TenantContext::class)->forget();

    // Un scope silencieusement inopérant est la cause classique des fuites :
    // on préfère une erreur bruyante à une requête non filtrée.
    expect(fn () => Contact::query()->get())
        ->toThrow(RuntimeException::class, 'hors de tout contexte de tenant');
});

it('isole les conversations, messages, prospects et documents', function () {
    [$tenantA] = $this->createTenantWithUser();
    [$tenantB] = $this->createTenantWithUser();

    $this->actingForTenant($tenantA);
    $conversationA = Conversation::factory()->create();
    Message::factory()->for($conversationA)->create();
    Lead::factory()->create(['contact_id' => $conversationA->contact_id]);
    Document::factory()->create();

    $this->actingForTenant($tenantB);

    expect(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(0)
        ->and(Document::query()->count())->toBe(0);
});

it('restaure le contexte précédent après un runAs, même en cas d\'exception', function () {
    [$tenantA] = $this->createTenantWithUser();
    [$tenantB] = $this->createTenantWithUser();

    $context = app(TenantContext::class);
    $context->set($tenantA);

    try {
        $context->runAs($tenantB, function () {
            throw new RuntimeException('Échec simulé.');
        });
    } catch (RuntimeException) {
        // attendu
    }

    // Sans restauration en `finally`, un worker traiterait les jobs suivants
    // sous l'identité du mauvais tenant.
    expect($context->id())->toBe($tenantA->id);
});

it('ne permet pas à un utilisateur d\'accéder à une conversation d\'un autre tenant', function () {
    [$tenantA] = $this->createTenantWithUser();
    [$tenantB, $userB] = $this->createTenantWithUser();

    $this->actingForTenant($tenantA);
    $conversationA = Conversation::factory()->create();

    $this->actingAsTenantUser($userB);

    // Le route model binding passe par le scope global : la ressource
    // n'existe tout simplement pas pour cet utilisateur.
    $this->get("/conversations/{$conversationA->id}")->assertNotFound();
});
