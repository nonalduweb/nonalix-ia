<?php

declare(strict_types=1);

namespace App\Support;

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
