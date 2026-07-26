<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Confirmation de l'adresse e-mail du propriétaire.
 *
 * Barrière distincte de la 2FA et antérieure à elle : une adresse erronée
 * rendrait le compte irrécupérable, puisque la réinitialisation de mot de
 * passe passe par cette même adresse.
 */
class EmailVerificationController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function notice(Request $request): RedirectResponse|Response
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : Inertia::render('Auth/VerifyEmail', [
                'email'  => $request->user()->email,
                'status' => $request->session()->get('status'),
            ]);
    }

    /**
     * EmailVerificationRequest valide la signature du lien et l'appartenance
     * à l'utilisateur connecté : un lien reçu par erreur ne vérifie pas
     * l'adresse d'un autre compte.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->fulfill();
        $this->audit->log('auth.email_verified');

        return redirect()->route('dashboard')
            ->with('success', 'Adresse confirmée. Configurez maintenant la double authentification.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Un nouveau lien vient d\'être envoyé.');
    }
}
