<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Conversion et formatage monétaire.
 *
 * Toutes les sommes sont stockées dans la plus petite unité de leur devise :
 * centimes pour l'euro, francs pour le franc CFA. Diviser systématiquement
 * par cent — ce que faisait l'interface — affiche 150 FCFA là où le client
 * paie 15 000, une erreur d'un facteur cent sur une grille tarifaire.
 *
 * Le franc CFA, comme le yen ou le won, n'a pas de sous-unité.
 */
final class Money
{
    /** Devises sans sous-unité (norme ISO 4217, exposant 0). */
    private const ZERO_DECIMAL = [
        'XOF', 'XAF', 'JPY', 'KRW', 'VND', 'CLP', 'ISK',
        'KMF', 'GNF', 'RWF', 'UGX', 'DJF', 'BIF', 'MGA',
    ];

    /** Nombre de décimales de la devise : 0 ou 2. */
    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /** Combien d'unités minimales dans une unité principale. */
    public static function factor(string $currency): int
    {
        return self::decimals($currency) === 0 ? 1 : 100;
    }

    /** Unité minimale (stockage) vers unité principale (affichage). */
    public static function toMajor(int $minor, string $currency): float
    {
        return $minor / self::factor($currency);
    }

    /** Unité principale (saisie) vers unité minimale (stockage). */
    public static function toMinor(float $major, string $currency): int
    {
        return (int) round($major * self::factor($currency));
    }

    /**
     * Rendu lisible, en français.
     *
     * Volontairement sans NumberFormatter : l'extension intl n'expose pas
     * toujours le symbole attendu localement — « F CFA » est plus parlant que
     * « XOF » pour un commerçant d'Abidjan.
     */
    public static function format(int $minor, string $currency): string
    {
        $currency = strtoupper($currency);
        $amount   = self::toMajor($minor, $currency);
        $decimals = self::decimals($currency);

        $formatted = number_format($amount, $decimals, ',', ' ');

        return match ($currency) {
            'XOF', 'XAF' => $formatted.' F CFA',
            'EUR'        => $formatted.' €',
            'USD'        => $formatted.' $',
            'MAD'        => $formatted.' DH',
            default      => $formatted.' '.$currency,
        };
    }
}
