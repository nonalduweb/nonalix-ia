<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Services\Audit\AuditLogger;
use App\Services\Auth\EmailOtp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Configuration de l'authentification à deux facteurs (TOTP).
 *
 * Le secret n'est activé qu'après confirmation d'un code valide : sans cette
 * étape, un utilisateur qui scanne mal le QR code se retrouverait verrouillé
 * hors de son compte.
 */
class TwoFactorSetupController
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $audit,
        private readonly EmailOtp $emailOtp,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/TwoFactorSetup', [
            'enabled'   => $user->hasTwoFactorEnabled(),
            'required'  => $user->requiresTwoFactor(),
            // Le secret est affiché tant que la 2FA n'est pas confirmée.
            'qrCode'    => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled()
                ? $this->qrCodeSvg($user->email, $user->two_factor_secret)
                : null,
            'secret'    => $user->hasTwoFactorEnabled() ? null : $user->two_factor_secret,
            'method'    => $user->two_factor_method,
            'email'     => $user->email,
        ]);
    }

    /** Génère un secret et des codes de récupération, sans encore les activer. */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->withErrors(['two_factor' => 'La 2FA est déjà active.']);
        }

        $user->forceFill([
            'two_factor_method'         => 'totp',
            'two_factor_secret'         => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at'   => null,
        ])->save();

        return back();
    }

    /**
     * Bascule sur le second facteur par e-mail et envoie un premier code.
     *
     * Aucun secret à générer : la preuve est l'accès à la boîte, revérifié à
     * chaque connexion. Le compte n'est confirmé qu'après saisie du code,
     * comme pour le TOTP — activer sans vérifier enfermerait dehors un
     * utilisateur dont l'adresse est erronée.
     */
    public function enableEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->withErrors(['two_factor' => 'La 2FA est déjà active.']);
        }

        $user->forceFill([
            'two_factor_method'         => 'email',
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at'   => null,
        ])->save();

        $this->emailOtp->send($user);

        return back()->with('status', "Un code vient d'être envoyé à {$user->email}.");
    }

    /** Confirme la configuration en validant un premier code. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $code = $request->string('code')->toString();

        if ($user->usesEmailTwoFactor()) {
            $valid = $this->emailOtp->verify($user, $code);
        } elseif ($user->two_factor_secret === null) {
            return back()->withErrors(['code' => 'Choisissez d\'abord une méthode.']);
        } else {
            // `window: 1` tolère une dérive d'horloge d'environ 30 secondes de
            // part et d'autre — sans cela, un téléphone légèrement
            // désynchronisé rendrait la 2FA inutilisable.
            $valid = $this->google2fa->verifyKey($user->two_factor_secret, $code, 1);
        }

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Code invalide ou expiré.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->put('auth.two_factor_verified', true);

        $this->audit->log('auth.two_factor_enabled', $user);

        return redirect()->route('dashboard')
            ->with('success', 'Authentification à deux facteurs activée.')
            ->with('recoveryCodes', $user->two_factor_recovery_codes);
    }

    /** Désactivation, refusée si le rôle de l'utilisateur l'exige. */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['password' => ['required', 'current_password']]);

        if ($user->requiresTwoFactor()) {
            return back()->withErrors([
                'two_factor' => 'Votre rôle impose l\'authentification à deux facteurs.',
            ]);
        }

        $user->forceFill([
            'two_factor_method'         => 'totp',
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        $this->audit->log('auth.two_factor_disabled', $user);

        return back()->with('success', 'Authentification à deux facteurs désactivée.');
    }

    /** @return array<int, string> */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** QR code en SVG : aucune dépendance à GD, et rendu net à toute taille. */
    private function qrCodeSvg(string $email, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            $email,
            $secret,
        );

        $writer = new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }
}
