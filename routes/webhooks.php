<?php

use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use App\Http\Controllers\Webhook\EmailWebhookController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Webhooks entrants — api.nonalixia.com
|------------------------------------------------------------------------------
| Ces routes ne portent NI session, NI CSRF, NI Sanctum : l'appelant est Meta,
| pas un navigateur. L'authenticité est établie par la signature HMAC-SHA256
| calculée avec l'app_secret du tenant (voir WebhookSignatureVerifier).
|
| L'URL contient l'identifiant du tenant car chaque entreprise déclare sa propre
| application Meta, donc son propre secret : sans ce segment, on ne saurait pas
| avec quelle clé vérifier la signature.
*/

// Le secret est dans le chemin : c'est le seul dénominateur commun aux
// fournisseurs de courrier entrant, qui signent tous différemment mais
// acceptent tous une URL arbitraire. Voir VerifyEmailWebhookSecret.
Route::post('webhooks/email/{secret}', [EmailWebhookController::class, 'handle'])
    ->middleware('email-webhook')
    ->name('webhooks.email');

Route::prefix('webhooks/whatsapp')
    ->as('webhooks.whatsapp.')
    ->group(function () {

        // Handshake de vérification Meta (à la déclaration de l'URL de callback).
        Route::get('{tenant}', [WhatsAppWebhookController::class, 'verify'])
            ->name('verify');

        // Réception des événements : messages entrants et statuts de livraison.
        Route::post('{tenant}', [WhatsAppWebhookController::class, 'handle'])
            ->name('handle');
    });
