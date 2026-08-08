<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert l'audio d'un message, et lui seul.
 *
 * Les notes vocales vivent sur un disque PRIVÉ : elles peuvent contenir un
 * nom, un numéro, une adresse. Aucune URL publique ne doit y mener. Chaque
 * lecture repasse donc par l'autorisation de la conversation, et le chemin
 * n'est jamais construit à partir d'une saisie — il est lu sur le message.
 */
class MessageMediaController
{
    public function __invoke(Request $request, Message $message): StreamedResponse
    {
        // La policy porte sur la conversation : qui peut la lire peut
        // l'écouter, et personne d'autre.
        abort_unless($request->user()->can('view', $message->conversation), 403);

        $path = $message->media['storage_path'] ?? null;

        abort_if(! is_string($path) || $path === '', 404);

        $disk = Storage::disk('media');

        abort_unless($disk->exists($path), 404);

        return $disk->response(
            $path,
            null,
            [
                'Content-Type'  => $message->media['mime_type'] ?? 'audio/ogg',
                // Jamais de cache partagé : le contenu est privé et le lien
                // n'est valable que pour qui vient d'être autorisé.
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
