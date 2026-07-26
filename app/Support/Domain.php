<?php

declare(strict_types=1);

namespace App\Support;

use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Construction des URL inter-domaines.
 *
 * L'application sert quatre sous-domaines distincts et doit régulièrement
 * rediriger de l'un vers l'autre. Coder « https:// » en dur casse le
 * développement local, qui tourne en clair : le navigateur suivrait la
 * redirection vers un port TLS qui n'écoute pas.
 *
 * Le schéma est donc déduit de la requête en cours, avec repli sur APP_URL —
 * ce qui couvre aussi les redirections émises hors contexte HTTP.
 */
final class Domain
{
    public static function url(string $domain, string $path = '/'): string
    {
        return sprintf(
            '%s://%s/%s',
            self::scheme(),
            $domain,
            ltrim($path, '/'),
        );
    }

    public static function app(string $path = '/'): string
    {
        return self::url((string) config('nonalix.domains.app'), $path);
    }

    public static function admin(string $path = '/'): string
    {
        return self::url((string) config('nonalix.domains.admin'), $path);
    }

    public static function api(string $path = '/'): string
    {
        return self::url((string) config('nonalix.domains.api'), $path);
    }

    /**
     * Redirige vers une URL, en tenant compte d'Inertia.
     *
     * Une redirection 302 vers un AUTRE domaine est suivie en silence par le
     * XHR d'Inertia : le navigateur récupère le HTML de la cible, qu'Inertia
     * ne sait pas interpréter, et il ne se passe RIEN — aucune erreur, ni à
     * l'écran ni dans les journaux, puisque la réponse est un 302 valide.
     *
     * Inertia impose un 409 porteur de X-Inertia-Location pour ce cas.
     * L'application servant quatre domaines qui se renvoient l'un vers
     * l'autre, le piège est partout : ce point d'entrée unique évite d'y
     * retomber.
     *
     * À l'intérieur d'un même domaine, la redirection ordinaire est conservée
     * — elle évite un rechargement complet de page.
     */
    public static function redirectTo(string $url): SymfonyResponse
    {
        $target  = parse_url($url, PHP_URL_HOST);
        $current = request()?->getHost();

        return $target !== null && $current !== null && $target !== $current
            ? Inertia::location($url)
            : redirect()->to($url);
    }

    private static function scheme(): string
    {
        // Derrière un proxy TLS, `isSecure()` s'appuie sur X-Forwarded-Proto,
        // que TrustProxies rend exploitable.
        if (($request = request()) !== null && $request->server->has('REQUEST_METHOD')) {
            return $request->isSecure() ? 'https' : 'http';
        }

        return str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';
    }
}
