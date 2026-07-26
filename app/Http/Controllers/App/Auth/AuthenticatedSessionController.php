<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    // Type de retour élargi : Inertia::location() renvoie une réponse 409
    // porteuse de X-Inertia-Location, pas un RedirectResponse.
    public function store(Request $request): RedirectResponse|HttpResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            (bool) ($credentials['remember'] ?? false),
        )) {
            $this->audit->log('auth.login_failed', context: ['email' => $credentials['email']]);

            // Message unique quelle que soit la cause : distinguer « e-mail
            // inconnu » de « mot de passe incorrect » permettrait d'énumérer
            // les comptes de la plateforme.
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Compte désactivé : on déconnecte immédiatement plutôt que de laisser
        // une session s'établir.
        if (! $user->canAuthenticate()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez l\'administrateur de votre entreprise.',
            ]);
        }

        $request->session()->regenerate();

        // Le défi 2FA n'est pas encore passé : le middleware `2fa` s'en
        // chargera à la première route protégée.
        $request->session()->forget('auth.two_factor_verified');

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->log('auth.login_succeeded');

        // Un super-admin est renvoyé vers son propre domaine : l'espace client
        // n'a aucun sens pour lui (il n'a pas de tenant).
        //
        // Inertia::location() et NON redirect()->away() : le formulaire est
        // envoyé en XHR, et un 302 vers un autre domaine est suivi en silence
        // par le navigateur. Inertia reçoit alors du HTML qu'il ne sait pas
        // interpréter et ne fait RIEN — la page reste figée, sans erreur.
        // Inertia::location() répond 409 + X-Inertia-Location, seul contrat
        // qui déclenche une vraie navigation. Hors contexte Inertia, la
        // méthode retombe d'elle-même sur une redirection classique.
        if ($user->isSuperAdmin()) {
            return Inertia::location(Domain::admin());
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('auth.logout');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
