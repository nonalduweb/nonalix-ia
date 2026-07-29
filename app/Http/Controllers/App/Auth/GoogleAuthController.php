<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Connexion et inscription via Google.
 *
 * Google remplace le mot de passe, PAS la validation commerciale : un
 * visiteur inconnu est renvoyé vers l'écran de finalisation, qui réclame un
 * code d'accès comme l'inscription classique. Sans cela, la connexion Google
 * serait une porte ouverte contournant les codes et le formulaire de demande.
 *
 * La 2FA reste exigée ensuite pour les rôles qui l'imposent : rien ne nous
 * garantit que le compte Google du client est lui-même protégé.
 */
class GoogleAuthController
{
    /** Le temps laissé pour finaliser une inscription entamée via Google. */
    private const PENDING_TTL_MINUTES = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    public function redirect(): HttpResponse|RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): HttpResponse|RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            // État invalide : session expirée pendant l'aller-retour, ou
            // tentative de rejeu. On repart proprement du début.
            return redirect()->route('login')
                ->withErrors(['email' => 'La connexion Google a expiré. Réessayez.']);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['email' => 'La connexion Google a échoué. Réessayez ou utilisez votre mot de passe.']);
        }

        $email = $googleUser->getEmail();

        if (! is_string($email) || $email === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Votre compte Google ne communique pas d\'adresse e-mail.']);
        }

        // --- Compte déjà rattaché ---------------------------------------------
        $social = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->with('user')
            ->first();

        if ($social?->user !== null) {
            return $this->login($request, $social->user, $social);
        }

        // --- Rattachement à un compte existant ---------------------------------
        // Uniquement si Google atteste que l'adresse est vérifiée. Sinon, il
        // suffirait de créer un compte Google portant l'adresse d'autrui pour
        // s'emparer de son espace client.
        $emailVerified = (bool) ($googleUser->user['email_verified'] ?? false);

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if (! $emailVerified) {
                $this->audit->log('auth.google_link_refused', $existing, [
                    'after' => ['reason' => 'email_not_verified_by_google'],
                ]);

                return redirect()->route('login')->withErrors([
                    'email' => 'Google n\'a pas vérifié cette adresse. Connectez-vous avec votre mot de passe.',
                ]);
            }

            $link = $this->linkAccount($existing, $googleUser);
            $this->audit->log('auth.google_linked', $existing);

            return $this->login($request, $existing, $link);
        }

        // --- Inconnu : finalisation exigeant un code d'accès --------------------
        $request->session()->put('google_pending', [
            'id'         => $googleUser->getId(),
            'email'      => $email,
            'name'       => $googleUser->getName() ?: $email,
            'avatar'     => $googleUser->getAvatar(),
            'verified'   => $emailVerified,
            'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->route('register.social');
    }

    private function linkAccount(User $user, $googleUser): SocialAccount
    {
        return SocialAccount::updateOrCreate(
            ['provider' => 'google', 'provider_id' => $googleUser->getId()],
            [
                'user_id'      => $user->id,
                'email'        => $googleUser->getEmail(),
                'avatar_url'   => $googleUser->getAvatar(),
                'last_used_at' => now(),
            ],
        );
    }

    private function login(Request $request, User $user, SocialAccount $social): HttpResponse|RedirectResponse
    {
        if (! $user->canAuthenticate()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte est désactivé. Contactez l\'administrateur de votre entreprise.',
            ]);
        }

        $social->forceFill(['last_used_at' => now()])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // La 2FA n'est PAS considérée comme acquise : le middleware la
        // réclamera. Une identité Google ne dit rien du second facteur.
        $request->session()->forget('auth.two_factor_verified');

        // Google atteste l'adresse : exiger notre propre lien de confirmation
        // par-dessus serait redondant et bloquerait l'accès sans rien prouver
        // de plus.
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->log('auth.login_succeeded', context: ['provider' => 'google']);

        // Inertia::location : redirection inter-domaines pour un super-admin.
        return $user->isSuperAdmin()
            ? Domain::redirectTo(Domain::admin())
            : redirect()->intended(route('dashboard'));
    }
}
