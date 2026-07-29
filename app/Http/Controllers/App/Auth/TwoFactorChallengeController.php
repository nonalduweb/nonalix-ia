<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Services\Audit\AuditLogger;
use App\Services\Auth\EmailOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Défi 2FA, présenté une fois par session.
 *
 * Trois preuves acceptées : le code d'une application d'authentification, un
 * code reçu par e-mail, ou un code de récupération. Les deux premières sont
 * équivalentes en usage ; la troisième est un secours à usage unique.
 */
class TwoFactorChallengeController
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $audit,
        private readonly EmailOtp $emailOtp,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        // Avec la méthode par e-mail, le code part sans que l'utilisateur ait
        // à le demander : lui présenter un champ vide et un bouton « envoyer »
        // ajouterait une étape sans rien protéger de plus.
        if ($user->usesEmailTwoFactor() && ! $this->emailOtp->hasPending($user)) {
            $this->emailOtp->send($user);
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'method'       => $user->two_factor_method,
            'maskedEmail'  => $this->maskEmail($user->email),
            'status'       => $request->session()->get('status'),
        ]);
    }

    /** Renvoie un code par e-mail, y compris à un utilisateur en TOTP. */
    public function sendEmailCode(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || ! $user->hasTwoFactorEnabled(), 403);

        $this->emailOtp->send($user);
        $this->audit->log('auth.two_factor_email_sent', $user);

        return back()->with('status', 'Un code vient d\'être envoyé à votre adresse.');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || ! $user->hasTwoFactorEnabled(), 403);

        $validated = $request->validate([
            'code'          => ['required_without:recovery_code', 'nullable', 'string', 'size:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:20'],
        ]);

        $verified = isset($validated['recovery_code'])
            ? $this->consumeRecoveryCode($user, $validated['recovery_code'])
            : $this->verifyCode($user, (string) $validated['code']);

        if (! $verified) {
            $this->audit->log('auth.two_factor_failed', $user);

            throw ValidationException::withMessages(['code' => 'Code invalide ou expiré.']);
        }

        // Marqueur de session : le middleware `2fa` s'appuie dessus, la
        // validation n'est donc pas redemandée à chaque requête.
        $request->session()->put('auth.two_factor_verified', true);
        $request->session()->regenerate();

        $this->audit->log('auth.two_factor_verified', $user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Un code par e-mail est accepté même pour un compte en TOTP : c'est le
     * recours de celui qui a perdu son téléphone mais garde sa boîte mail.
     * L'inverse n'a pas de sens — un compte en e-mail n'a pas de secret TOTP.
     */
    private function verifyCode($user, string $code): bool
    {
        if ($this->emailOtp->verify($user, $code)) {
            return true;
        }

        return $user->two_factor_secret !== null
            && $this->google2fa->verifyKey($user->two_factor_secret, $code, 1);
    }

    /**
     * Valide et CONSOMME un code de récupération.
     *
     * Un code à usage unique qui resterait valide après emploi n'offrirait
     * aucune protection : il est retiré de la liste dans le même mouvement.
     */
    private function consumeRecoveryCode($user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(strtoupper(trim($code)), array_map('strtoupper', $codes), true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /** j****.k****@exemple.ci — confirme la bonne adresse sans la divulguer. */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $masked = mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 1));

        return $masked.'@'.$domain;
    }
}
