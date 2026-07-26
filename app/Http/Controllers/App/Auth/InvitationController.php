<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Auth;

use App\Enums\UserStatus;
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
 * Acceptation d'une invitation : le destinataire choisit son mot de passe.
 *
 * Distinct de la réinitialisation, et non un simple alias : l'émetteur de
 * jetons `invitations` accorde sept jours là où une réinitialisation en
 * accorde soixante minutes. Confondre les deux ferait expirer l'invitation
 * avant que le client n'ouvre sa boîte, et son compte deviendrait
 * définitivement inaccessible.
 */
class InvitationController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request, string $token): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email', 'max:190'],
            'password' => [
                'required', 'confirmed',
                PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        $status = Password::broker('invitations')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $attributes = [
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ];

                // L'activation ne concerne QUE les comptes invités. Un compte
                // désactivé qui suivrait un vieux lien ne doit pas revenir à
                // la vie par ce biais.
                if ($user->status === UserStatus::Invited) {
                    $attributes['status'] = UserStatus::Active;

                    // Avoir ouvert le lien reçu à cette adresse prouve qu'on
                    // la contrôle : c'est exactement ce que vérifie la
                    // confirmation d'e-mail, inutile de la redemander.
                    $attributes['email_verified_at'] = $user->email_verified_at ?? now();
                }

                $user->forceFill($attributes)->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->audit->log('auth.invitation_failed', context: [
                'email'  => $request->input('email'),
                'status' => $status,
            ]);

            throw ValidationException::withMessages([
                'email' => 'Cette invitation n\'est plus valide. Demandez-en une nouvelle à votre administrateur.',
            ]);
        }

        return redirect()->route('login')
            ->with('status', 'Votre compte est activé. Vous pouvez vous connecter.');
    }
}
