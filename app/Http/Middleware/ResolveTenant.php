<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use App\Support\Domain;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Établit le tenant courant à partir de l'utilisateur authentifié.
 *
 * Le tenant n'est JAMAIS lu depuis la requête (paramètre, en-tête, corps) :
 * c'est une propriété de l'identité, pas une donnée d'entrée. Un utilisateur
 * ne peut donc pas changer de tenant en modifiant sa requête.
 *
 * S'exécute obligatoirement après `auth`.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        // Un super-admin n'a pas de tenant : il n'a rien à faire sur les
        // routes clientes. Le rediriger vers son espace évite un 500 obscur.
        //
        // Inertia::location() : un 302 vers un autre domaine est suivi en
        // silence par le XHR d'Inertia, qui reçoit du HTML étranger et
        // n'agit pas. Le 409 + X-Inertia-Location est le seul contrat qui
        // provoque une navigation réelle.
        if ($user->isSuperAdmin()) {
            return Inertia::location(Domain::admin());
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            abort(403, 'Aucune entreprise n\'est associée à ce compte.');
        }

        // Un compte suspendu conserve ses données mais perd l'accès.
        if (! $tenant->isOperational()) {
            abort(403, sprintf(
                'L\'accès à cet espace est suspendu. Contactez %s.',
                config('nonalix.support_email'),
            ));
        }

        // Sauvegarde/restauration plutôt qu'effacement inconditionnel. En
        // production le contexte précédent est toujours nul, le comportement
        // est donc identique : le tenant d'une requête ne fuit pas vers la
        // suivante sous Octane ou worker persistant. Mais un appelant qui
        // avait délibérément posé un contexte — une commande, un test — le
        // retrouve intact après la requête.
        $previous = $this->context->current();

        $this->context->set($tenant);

        try {
            return $next($request);
        } finally {
            $previous === null
                ? $this->context->forget()
                : $this->context->set($previous);
        }
    }
}
