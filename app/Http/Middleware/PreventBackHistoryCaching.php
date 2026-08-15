<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interdit au navigateur de garder une page authentifiée en mémoire.
 *
 * Sans cet en-tête, un retour arrière après déconnexion restitue la page telle
 * qu'elle était — l'écran de code de vérification, le tableau de bord, une
 * conversation — sans jamais interroger le serveur. La session est bien fermée,
 * mais l'utilisateur voit des restes, et l'on peut légitimement croire que la
 * déconnexion a échoué.
 *
 * `no-store` est le seul niveau qui l'empêche vraiment : `no-cache` autorise
 * encore la restitution depuis l'historique.
 *
 * Ne concerne que les réponses produites par PHP. Les fichiers construits sont
 * servis par Nginx et gardent leur cache long, qui repose sur l'empreinte du
 * nom de fichier.
 */
class PreventBackHistoryCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Un téléchargement en flux n'a pas d'en-têtes à réécrire ici, et
        // certains portent volontairement leur propre politique de cache.
        if ($response->headers->has('Cache-Control') && $response->headers->get('Cache-Control') === 'private, no-store') {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
