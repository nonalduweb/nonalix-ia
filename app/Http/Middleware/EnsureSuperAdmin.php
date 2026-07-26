<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use App\Support\Domain;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint l'accès à admin.nonalixia.com aux membres de l'équipe NONALIX.
 *
 * Toute tentative refusée est auditée : c'est le signal le plus direct qu'un
 * compte client a été compromis ou qu'une escalade est tentée.
 */
class EnsureSuperAdmin
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $this->audit->log('admin.access_denied', context: [
            'path'      => $request->path(),
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        // Les clients d'API reçoivent un refus net, sans redirection.
        if ($request->expectsJson()) {
            abort(403);
        }

        // Un utilisateur client atterrit ici sans intention malveillante dans
        // l'immense majorité des cas : la session est partagée entre app.* et
        // admin.* (nécessaire à l'impersonation), il suffit d'avoir tapé la
        // mauvaise adresse. Un 403 nu le laisserait sur une page blanche sans
        // aucune issue — on le renvoie donc vers SON espace, avec une
        // explication. L'accès reste refusé et tracé : la sécurité est
        // identique, seule l'issue change.
        if ($user->tenant_id !== null) {
            // Le message de session est posé séparément : Inertia::location()
            // ne renvoie pas un RedirectResponse et n'expose donc pas ->with().
            // Un 302 inter-domaines, lui, serait avalé en silence par le XHR
            // d'Inertia et laisserait l'utilisateur sur une page figée.
            session()->flash('error', "Cet espace est réservé à l'équipe Nonalix. Vous avez été redirigé vers votre tableau de bord.");

            return Inertia::location(Domain::app());
        }

        // Compte sans tenant et sans privilège plateforme : état incohérent,
        // aucune redirection n'aurait de sens.
        abort(403);
    }
}
