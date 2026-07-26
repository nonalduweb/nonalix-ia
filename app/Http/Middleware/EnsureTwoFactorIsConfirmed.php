<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impose la 2FA aux rôles à privilèges.
 *
 * Deux situations distinctes :
 *   - 2FA non configurée → redirection vers l'écran de configuration ;
 *   - 2FA configurée mais défi non validé pour cette session → redirection
 *     vers le défi.
 *
 * Les rôles sans privilèges ne sont pas bloqués : imposer la 2FA à un simple
 * opérateur ferait chuter l'adoption sans gain de sécurité proportionné.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $request->expectsJson()
                ? response()->json([
                    'message' => 'L\'authentification à deux facteurs doit être activée.',
                    'code'    => 'two_factor_setup_required',
                ], 403)
                : redirect()->route('two-factor.setup');
        }

        // Le défi est validé une fois par session, pas à chaque requête.
        if ($request->session()->get('auth.two_factor_verified') !== true) {
            return $request->expectsJson()
                ? response()->json([
                    'message' => 'Validation à deux facteurs requise.',
                    'code'    => 'two_factor_challenge_required',
                ], 403)
                : redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
