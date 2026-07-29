<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Exceptions\AccessCodeUnusableException;
use App\Models\AccessCode;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
            'googleEnabled' => filled(config('services.google.client_id')),
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

    /**
     * Finalisation d'une inscription entamée via Google.
     *
     * Google a prouvé l'identité, pas le droit d'ouvrir un compte : le code
     * d'accès reste exigé, comme pour l'inscription classique.
     */
    public function createSocial(Request $request): Response|RedirectResponse
    {
        $pending = $this->pendingGoogle($request);

        if ($pending === null) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Votre session a expiré. Recommencez la connexion Google.']);
        }

        return Inertia::render('Auth/RegisterSocial', [
            'name'          => $pending['name'],
            'email'         => $pending['email'],
            'prefilledCode' => $request->query('code'),
        ]);
    }

    public function storeSocial(Request $request): RedirectResponse
    {
        $pending = $this->pendingGoogle($request);

        if ($pending === null) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Votre session a expiré. Recommencez la connexion Google.']);
        }

        $validated = $request->validate([
            'code'    => ['required', 'string', 'max:32'],
            'company' => ['required', 'string', 'min:2', 'max:160'],
            'name'    => ['required', 'string', 'min:2', 'max:120'],
            'terms'   => ['accepted'],
        ], [
            'terms.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
        ]);

        // L'adresse vient de Google, jamais du formulaire : la laisser
        // modifiable permettrait de rattacher l'identité Google d'un tiers.
        if (User::query()->where('email', $pending['email'])->exists()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Un compte existe déjà pour cette adresse.']);
        }

        try {
            ['owner' => $owner] = $this->registrar->register(
                $validated['code'],
                [
                    'company'  => $validated['company'],
                    'name'     => $validated['name'],
                    'email'    => $pending['email'],
                    // Mot de passe aléatoire jamais communiqué : le compte
                    // s'utilise via Google. Le propriétaire pourra s'en
                    // définir un par « mot de passe oublié ».
                    'password' => Str::random(48),
                ],
                $request->ip(),
            );
        } catch (AccessCodeUnusableException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        SocialAccount::create([
            'user_id'      => $owner->id,
            'provider'     => 'google',
            'provider_id'  => $pending['id'],
            'email'        => $pending['email'],
            'avatar_url'   => $pending['avatar'] ?? null,
            'last_used_at' => now(),
        ]);

        // Google atteste l'adresse : pas de second lien de confirmation.
        $owner->forceFill(['email_verified_at' => now()])->save();

        $request->session()->forget('google_pending');

        Auth::login($owner, remember: true);
        $request->session()->regenerate();
        $request->session()->forget('auth.two_factor_verified');

        return redirect()->route('dashboard');
    }

    /**
     * Identité Google mise de côté par le callback, si elle est encore valide.
     *
     * L'expiration est vérifiée ici et pas seulement à l'écriture : une
     * session oubliée ouverte sur un poste partagé ne doit pas rester une
     * inscription en attente indéfiniment.
     */
    private function pendingGoogle(Request $request): ?array
    {
        $pending = $request->session()->get('google_pending');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('google_pending');

            return null;
        }

        return $pending;
    }
}
