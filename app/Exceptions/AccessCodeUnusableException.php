<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Code d'accès inutilisable au moment de la consommation.
 *
 * Volontairement SANS détail, contrairement aux autres exceptions du projet :
 * l'inscription est publique, et distinguer « code inconnu » de « code épuisé »
 * permettrait de sonder l'existence de codes valides par essais successifs.
 * Le motif précis n'est visible que depuis l'administration.
 */
class AccessCodeUnusableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce code d\'accès n\'est pas valide.');
    }
}
