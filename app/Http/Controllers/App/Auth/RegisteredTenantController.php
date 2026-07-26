<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Exceptions\AccessCodeUnusableException;
use App\Models\AccessCode;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscription publique d'une entreprise, sur présentation d'un code d'accès.
 *
 * L'inscription est fermée par défaut : la plateforme détient les jetons
 * WhatsApp et les clés IA de ses clients, une création de compte libre en
 * ferait une cible. Le code est émis par NONALIX et porte le pack ouvert.
 */
class RegisteredTenantController
{
    public function __construct(
        private readonly TenantRegistrar $registrar,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            // Pré-remplissage depuis un lien du type /register?code=ABCD-...,
            // pour que le prospect n'ait pas à recopier le code à la main.
            'prefilledCode' => $request->query('code'),
        ]);
    }

    /**
     * Vérifie un code sans rien consommer, pour un retour immédiat au
     * remplissage du formulaire.
     */
    public function checkCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $code = AccessCode::query()
            ->with('plan:id,name,slug')
            ->where('code', AccessCode::normalize($validated['code']))
            ->first();

        if ($code === null || ! $code->isUsable()) {
            // Aucune distinction entre inconnu, expiré et épuisé : la
            // différence permettrait de sonder les codes existants.
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid'     => true,
            'plan'      => $code->plan?->name,
            'trialDays' => $code->trial_days,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'     => ['required', 'string', 'max:32'],
            'company'  => ['required', 'string', 'min:2', 'max:160'],
            'name'     => ['required', 'string', 'min:2', 'max:120'],
            'email'    => ['required', 'email:rfc,dns', 'max:190', 'unique:users,email'],
            'password' => [
                'required', 'confirmed',
                // uncompromised() interroge la liste des mots de passe ayant
                // fuité (k-anonymat : seul un préfixe de hachage est envoyé).
                Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
            'terms'    => ['accepted'],
        ], [
            'terms.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
        ]);

        try {
            ['owner' => $owner] = $this->registrar->register(
                $validated['code'],
                $validated,
                $request->ip(),
            );
        } catch (AccessCodeUnusableException $e) {
            $this->audit->log('platform.registration_refused', context: [
                'reason' => 'access_code_unusable',
                'email'  => $validated['email'],
            ]);

            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        // L'envoi part après la transaction : un relais SMTP indisponible ne
        // doit pas annuler une inscription valide. L'utilisateur pourra
        // toujours redemander le lien depuis l'écran de vérification.
        $owner->sendEmailVerificationNotification();

        Auth::login($owner);
        $request->session()->regenerate();

        // La session part sans 2FA vérifiée : le middleware `2fa` imposera sa
        // configuration dès que l'adresse aura été confirmée.
        $request->session()->forget('auth.two_factor_verified');

        return redirect()->route('verification.notice');
    }
}
