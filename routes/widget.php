<?php

declare(strict_types=1);

use App\Http\Controllers\App\WidgetChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API du widget de chat (publique)
|--------------------------------------------------------------------------
|
| Ces routes sont appelées par `public/widget.js`, chargé sur le site du
| client. Elles sont donc anonymes et inter-origines : ni session, ni jeton
| CSRF, ni authentification. Voir la pile `widget` dans bootstrap/app.php.
|
| L'identifiant du tenant apparaît dans l'URL, mais il n'ouvre rien : il
| désigne l'entreprise à qui parler, exactement comme un numéro de téléphone
| public. L'historique n'est lisible qu'en présentant l'identifiant de session
| opaque conservé dans le navigateur du visiteur.
|
*/

Route::prefix('widget')->as('widget.')->group(function () {
    Route::get('config/{tenant}', [WidgetChatController::class, 'config'])->name('config');
    Route::post('chat/{tenant}', [WidgetChatController::class, 'chat'])->name('chat');

    // Un message vocal coûte bien plus cher qu'un message texte : transcription,
    // génération, puis synthèse. Le plafond est donc plus bas.
    Route::post('voice/{tenant}', [WidgetChatController::class, 'voice'])
        ->middleware('throttle:12,1')
        ->name('voice');

    // Écoute d'un audio, réservée au visiteur qui en est l'auteur : le contrôle
    // se fait sur l'identifiant de session, pas sur la seule connaissance de
    // l'identifiant du message.
    Route::get('audio/{tenant}/{message}', [WidgetChatController::class, 'audio'])->name('audio');
});
