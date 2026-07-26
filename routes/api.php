<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| API publique v1 — api.nonalixia.com/v1
|------------------------------------------------------------------------------
| Authentification par jeton Sanctum. Le jeton appartient à un utilisateur,
| donc à un tenant : `ResolveTenant` en déduit le contexte et le scope global
| fait le reste. Aucune route ne prend de `tenant_id` en paramètre — il ne peut
| donc pas être falsifié.
*/

Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {

    Route::get('me', MeController::class)->name('me');

    // --- Contacts -------------------------------------------------------------
    Route::apiResource('contacts', ContactController::class)
        ->only(['index', 'show', 'store', 'update']);

    Route::post('contacts/{contact}/opt-out', [ContactController::class, 'optOut'])
        ->name('contacts.opt-out');

    // --- Conversations --------------------------------------------------------
    Route::apiResource('conversations', ConversationController::class)
        ->only(['index', 'show', 'update']);

    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])
        ->name('conversations.messages.index');

    // L'envoi consomme le quota du tenant : middleware dédié.
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('quota:messages_sent')
        ->name('conversations.messages.store');

    // --- Prospects ------------------------------------------------------------
    Route::apiResource('leads', LeadController::class)
        ->only(['index', 'show', 'update']);
});
