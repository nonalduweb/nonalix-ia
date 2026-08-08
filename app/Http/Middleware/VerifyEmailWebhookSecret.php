<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secret partagé du webhook de courrier entrant.
 *
 * Les webhooks WhatsApp prouvent leur authenticité par une signature HMAC
 * calculée avec l'app_secret du tenant. Aucun équivalent universel n'existe
 * côté courrier : Mailgun, Postmark et SendGrid signent chacun autrement, et
 * certains relais ne signent pas du tout. Tous, en revanche, laissent
 * configurer une URL arbitraire.
 *
 * Le secret voyage donc dans le chemin. C'est moins fort qu'une signature —
 * il ne prouve pas l'intégrité du corps — mais il ferme la porte, ce qui est
 * l'essentiel : sans lui, n'importe qui pouvait faire naître des conversations
 * chez n'importe quel client et déclencher des générations facturées.
 */
class VerifyEmailWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('nonalix.email.webhook_secret');

        // Non configuré : le canal est fermé, pas ouvert. Un secret vide ne
        // doit jamais valoir « laisse passer tout le monde ».
        if ($expected === '') {
            abort(404);
        }

        $provided = (string) $request->route('secret');

        // hash_equals : la comparaison ne doit pas fuir la longueur du préfixe
        // commun, qui permettrait de deviner le secret caractère par caractère.
        abort_unless(hash_equals($expected, $provided), 404);

        return $next($request);
    }
}
