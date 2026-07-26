<?php

declare(strict_types=1);

namespace App\Services\AI\Concerns;

use App\Data\AI\TokenUsage;

/**
 * Estimation du coût d'un appel, en micro-centimes d'euro.
 *
 * Les tarifs de config/ai.php sont exprimés par million de tokens. Le calcul
 * reste entier de bout en bout : arrondir en flottant sur des millions
 * d'appels produit une dérive comptable.
 */
trait EstimatesCost
{
    protected function estimateCost(TokenUsage $usage, string $model): int
    {
        $pricing = config("ai.pricing.{$model}");

        // Modèle absent de la grille : on ne devine pas un prix, on renvoie 0.
        // Un coût fantaisiste serait pire qu'un coût manquant, qui se repère
        // immédiatement dans les statistiques.
        if (! is_array($pricing)) {
            return 0;
        }

        $input  = (int) ($pricing['input'] ?? 0);
        $output = (int) ($pricing['output'] ?? 0);

        return intdiv($usage->inputTokens * $input, 1_000_000)
             + intdiv($usage->outputTokens * $output, 1_000_000);
    }
}
