<?php

declare(strict_types=1);

use App\Enums\MessageStatus;

/*
| Les webhooks de statut Meta n'arrivent pas dans l'ordre : un `delivered`
| peut parvenir après un `read`. Sans transition monotone, l'interface
| afficherait « distribué » sur un message déjà lu.
*/

it('autorise la progression normale des statuts', function () {
    expect(MessageStatus::Queued->canTransitionTo(MessageStatus::Sent))->toBeTrue()
        ->and(MessageStatus::Sent->canTransitionTo(MessageStatus::Delivered))->toBeTrue()
        ->and(MessageStatus::Delivered->canTransitionTo(MessageStatus::Read))->toBeTrue();
});

it('refuse toute régression de statut', function () {
    expect(MessageStatus::Read->canTransitionTo(MessageStatus::Delivered))->toBeFalse()
        ->and(MessageStatus::Delivered->canTransitionTo(MessageStatus::Sent))->toBeFalse()
        ->and(MessageStatus::Sent->canTransitionTo(MessageStatus::Queued))->toBeFalse();
});

it('refuse de rejouer un statut identique', function () {
    foreach (MessageStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse();
    }
});

it('traite l\'échec comme un état terminal', function () {
    // Un message en échec ne doit pas repasser « distribué » à cause d'un
    // webhook tardif : l'opérateur doit continuer à voir le problème.
    expect(MessageStatus::Failed->canTransitionTo(MessageStatus::Delivered))->toBeFalse()
        ->and(MessageStatus::Failed->canTransitionTo(MessageStatus::Read))->toBeFalse();
});

it('permet de passer en échec depuis n\'importe quel statut non terminal', function () {
    expect(MessageStatus::Queued->canTransitionTo(MessageStatus::Failed))->toBeTrue()
        ->and(MessageStatus::Sent->canTransitionTo(MessageStatus::Failed))->toBeTrue();
});
