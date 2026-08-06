<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Masque les données sensibles avant journalisation ou audit.
 *
 * Un journal d'audit qui contient les jetons Meta en clair déplace simplement
 * le problème : il devient lui-même la cible. Toute donnée qui part vers un
 * log, un rapport d'erreur ou `audit_logs.changes` passe par ici.
 */
final class Redaction
{
    public const MASK = '[masqué]';

    /** Clés dont la valeur ne doit jamais être écrite, quelle qu'elle soit. */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'access_token', 'app_secret', 'webhook_verify_token',
        'api_key', 'secret', 'token', 'authorization',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_code',
        'remember_token', 'private_key',
    ];

    /**
     * Masque récursivement les valeurs sensibles d'un tableau.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::scrub($value);

                continue;
            }

            if (self::isSensitive((string) $key)) {
                $data[$key] = self::MASK;
            }
        }

        return $data;
    }

    /**
     * Retire des valeurs de secrets d'un texte libre.
     *
     * scrub() masque par NOM de champ ; il ne peut rien contre un secret noyé
     * dans une phrase. Or Meta renvoie le jeton d'accès dans ses messages
     * d'erreur (« Malformed access token EAAG… ») : stocké tel quel dans
     * `whatsapp_accounts.last_error`, le jeton se retrouvait en clair en base
     * et à l'écran, alors qu'il est chiffré dans sa propre colonne.
     *
     * @param  array<int, string|null>  $secrets  valeurs à faire disparaître
     */
    public static function fromText(?string $text, array $secrets): string
    {
        $text = (string) $text;

        foreach ($secrets as $secret) {
            // Les secrets courts produiraient des remplacements aberrants :
            // masquer « ok » masquerait la moitié des mots du message.
            if (is_string($secret) && mb_strlen($secret) >= 8) {
                $text = str_replace($secret, self::MASK, $text);
            }
        }

        return $text;
    }

    /**
     * Applique fromText() récursivement à toutes les chaînes d'un tableau.
     *
     * Sert à nettoyer une réponse d'API avant d'en tirer un message d'erreur :
     * le secret doit disparaître AVANT que l'exception n'existe, sinon chaque
     * consommateur en aval doit y penser — et l'un d'eux oubliera.
     *
     * @param  array<mixed>  $data
     * @param  array<int, string|null>  $secrets
     * @return array<mixed>
     */
    public static function fromArray(array $data, array $secrets): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = match (true) {
                is_array($value)  => self::fromArray($value, $secrets),
                is_string($value) => self::fromText($value, $secrets),
                default           => $value,
            };
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = mb_strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Masque partiellement un numéro de téléphone.
     *
     * On conserve l'indicatif et les deux derniers chiffres : assez pour
     * identifier une conversation en support, pas assez pour recontacter la
     * personne depuis un log.
     */
    public static function phone(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return $number;
        }

        $digits = preg_replace('/\D/', '', $number) ?? '';
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return substr($digits, 0, 3).str_repeat('•', $length - 5).substr($digits, -2);
    }

    /** Masque un jeton en n'en gardant qu'une amorce reconnaissable. */
    public static function token(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return $token;
        }

        return strlen($token) <= 8
            ? self::MASK
            : substr($token, 0, 4).'…'.substr($token, -4);
    }
}
