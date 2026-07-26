<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Réinitialisation du mot de passe.
 *
 * Sans ce parcours, tout compte dont le mot de passe est perdu l'est
 * définitivement : la plateforme n'a aucun autre moyen de rendre la main à un
 * propriétaire.
 */
class PasswordResetController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function requestForm(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);

        $status = Password::sendResetLink($request->only('email'));

        $this->audit->log('auth.password_reset_requested', context: [
            'email'  => $request->input('email'),
            'status' => $status,
        ]);

        // Réponse IDENTIQUE que l'adresse existe ou non : la distinguer
        // permettrait d'énumérer les comptes de la plateforme. Le throttling
        // du broker (60 s) est conservé et se traduit par le même message.
        return back()->with(
            'status',
            'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé.',
        );
    }

    public function resetForm(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email', 'max:190'],
            'password' => [
                'required', 'confirmed',
                PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    // Invalide les sessions « rester connecté » ouvertes
                    // ailleurs : un mot de passe réinitialisé doit chasser un
                    // éventuel intrus, pas cohabiter avec lui.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->audit->log('auth.password_reset_failed', context: [
                'email'  => $request->input('email'),
                'status' => $status,
            ]);

            throw ValidationException::withMessages([
                'email' => 'Ce lien de réinitialisation n\'est plus valide. Demandez-en un nouveau.',
            ]);
        }

        return redirect()->route('login')
            ->with('status', 'Mot de passe modifié. Vous pouvez vous connecter.');
    }
}
