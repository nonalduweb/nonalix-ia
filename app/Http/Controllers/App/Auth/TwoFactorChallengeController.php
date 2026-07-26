<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Défi 2FA, présenté une fois par session.
 */
class TwoFactorChallengeController
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
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
            : $this->google2fa->verifyKey($user->two_factor_secret, (string) $validated['code'], 1);

        if (! $verified) {
            $this->audit->log('auth.two_factor_failed', $user);

            throw ValidationException::withMessages(['code' => 'Code invalide.']);
        }

        // Marqueur de session : le middleware `2fa` s'appuie dessus, la
        // validation n'est donc pas redemandée à chaque requête.
        $request->session()->put('auth.two_factor_verified', true);
        $request->session()->regenerate();

        $this->audit->log('auth.two_factor_verified', $user);

        return redirect()->intended(route('dashboard'));
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
}
