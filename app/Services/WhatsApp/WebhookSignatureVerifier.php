<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;

/**
 * Vérification de la signature HMAC des webhooks Meta.
 *
 * Trois points sur lesquels il ne faut pas se tromper :
 *
 *  1. Le HMAC porte sur le corps BRUT de la requête, pas sur un JSON réencodé.
 *     Un simple `json_encode(json_decode($body))` change l'ordre des clés,
 *     l'échappement des caractères Unicode et les espaces : la signature ne
 *     correspond plus.
 *
 *  2. La comparaison se fait en temps constant (`hash_equals`). Un `===` sur
 *     des chaînes s'arrête au premier octet différent et laisse fuir, par la
 *     durée de la comparaison, de quoi reconstruire la signature attendue.
 *
 *  3. Le secret est celui de l'application Meta du TENANT. Chaque entreprise
 *     déclare la sienne : il n'y a pas de secret global à la plateforme.
 */
class WebhookSignatureVerifier
{
    public function verify(Request $request, ?string $appSecret): bool
    {
        // Échappatoire strictement réservée au développement local. En
        // production, cette valeur à `true` équivaut à un webhook ouvert.
        if (config('whatsapp.webhooks.allow_unsigned') && ! app()->isProduction()) {
            return true;
        }

        if ($appSecret === null || $appSecret === '') {
            return false;
        }

        $header = $request->header(config('whatsapp.webhooks.signature_header', 'X-Hub-Signature-256'));

        if (! is_string($header) || $header === '') {
            return false;
        }

        $prefix = (string) config('whatsapp.webhooks.signature_prefix', 'sha256=');

        if (! str_starts_with($header, $prefix)) {
            return false;
        }

        $provided = substr($header, strlen($prefix));

        // getContent() renvoie le corps tel qu'il a été reçu, sans parsing.
        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $provided);
    }

    /**
     * Handshake de vérification, à la déclaration de l'URL dans la console Meta.
     *
     * Comparaison en temps constant également : ce jeton protège l'association
     * entre une URL et un tenant.
     */
    public function verifyChallenge(Request $request, ?string $verifyToken): ?string
    {
        if ($verifyToken === null || $verifyToken === '') {
            return null;
        }

        $mode      = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token     = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode !== 'subscribe' || ! hash_equals($verifyToken, $token)) {
            return null;
        }

        return is_string($challenge) ? $challenge : null;
    }
}
