<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Second facteur transmis par e-mail.
 *
 * Alternative au TOTP pour les clients qui n'installeront pas d'application
 * d'authentification. La barrière reste la même — prouver l'accès à un canal
 * distinct du mot de passe — sans l'exigence d'un outil supplémentaire.
 *
 * Le code est stocké HACHÉ : un accès en lecture au cache ne doit pas suffire
 * à franchir le second facteur, faute de quoi il ne protégerait de rien.
 */
class EmailOtp
{
    /** Durée de validité. Assez court pour qu'un code intercepté périme vite. */
    private const TTL_SECONDS = 600;

    public function send(User $user): void
    {
        // Six chiffres, tirés d'une source cryptographique : un code
        // prévisible se devine sans jamais accéder à la boîte mail.
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($user), Hash::make($code), self::TTL_SECONDS);

        $user->notify(new TwoFactorCodeNotification($code, (int) (self::TTL_SECONDS / 60)));
    }

    public function verify(User $user, string $code): bool
    {
        $hashed = Cache::get($this->key($user));

        if (! is_string($hashed) || ! Hash::check(trim($code), $hashed)) {
            return false;
        }

        // Usage unique : un code rejouable annulerait l'intérêt de sa
        // durée de vie limitée.
        Cache::forget($this->key($user));

        return true;
    }

    public function hasPending(User $user): bool
    {
        return Cache::has($this->key($user));
    }

    private function key(User $user): string
    {
        return "two_factor_email_code:{$user->getKey()}";
    }
}
