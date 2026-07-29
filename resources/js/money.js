/**
 * Formatage monétaire, pendant côté navigateur de App\Support\Money.
 *
 * Les sommes sont stockées dans la plus petite unité de leur devise :
 * centimes pour l'euro, francs pour le franc CFA. Diviser systématiquement
 * par cent affiche 150 FCFA là où le client paie 15 000 — une erreur d'un
 * facteur cent sur une grille tarifaire.
 */

// Devises sans sous-unité (ISO 4217, exposant 0).
const ZERO_DECIMAL = [
    'XOF', 'XAF', 'JPY', 'KRW', 'VND', 'CLP', 'ISK',
    'KMF', 'GNF', 'RWF', 'UGX', 'DJF', 'BIF', 'MGA',
];

export const decimals = (currency) => (ZERO_DECIMAL.includes((currency ?? '').toUpperCase()) ? 0 : 2);

export const factor = (currency) => (decimals(currency) === 0 ? 1 : 100);

/** Unité minimale (stockage) vers unité principale (affichage). */
export const toMajor = (minor, currency) => (minor ?? 0) / factor(currency);

/** Unité principale (saisie) vers unité minimale (stockage). */
export const toMinor = (major, currency) => Math.round((parseFloat(major) || 0) * factor(currency));

const SYMBOLS = {
    XOF: 'F CFA',
    XAF: 'F CFA',
    EUR: '€',
    USD: '$',
    MAD: 'DH',
};

/**
 * `F CFA` plutôt que le code `XOF` d'Intl : c'est ainsi qu'un commerçant
 * d'Abidjan lit un prix.
 */
export const formatMoney = (minor, currency) => {
    const code = (currency ?? 'XOF').toUpperCase();
    const value = toMajor(minor, code);

    const formatted = value.toLocaleString('fr-FR', {
        minimumFractionDigits: decimals(code),
        maximumFractionDigits: decimals(code),
    });

    return `${formatted} ${SYMBOLS[code] ?? code}`;
};

/** Prix d'un plan, période comprise. */
export const formatPlanPrice = (plan) =>
    plan.price_cents === 0
        ? 'Gratuit'
        : `${formatMoney(plan.price_cents, plan.currency)}${plan.interval === 'year' ? ' / an' : ' / mois'}`;
