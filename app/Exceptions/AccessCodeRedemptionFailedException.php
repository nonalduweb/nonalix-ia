<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Refus motivé d'une consommation de code depuis l'espace client.
 *
 * Contrairement à AccessCodeUnusableException, le motif est ici explicite : la
 * page Facturation est authentifiée et réservée à qui administre l'entreprise,
 * et l'utilisateur a besoin de savoir si son code est expiré, épuisé ou déjà
 * utilisé pour savoir quoi faire ensuite. La route porte `throttle:access-code`
 * pour que cette précision n'ouvre pas un canal d'exploration par essais.
 *
 * Sert de signal de rollback : levée à l'intérieur de la transaction, elle
 * annule l'ensemble de la consommation.
 */
class AccessCodeRedemptionFailedException extends RuntimeException {}
