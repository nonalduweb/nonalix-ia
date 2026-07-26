<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Billing\QuotaService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque une action dont le quota est déjà atteint.
 *
 * Vérifie AVANT l'action, sans incrémenter : la consommation est comptabilisée
 * au moment où l'action réussit réellement (message effectivement envoyé,
 * réponse IA effectivement produite). Compter à l'entrée facturerait des
 * requêtes qui échouent.
 *
 * Usage : ->middleware('quota:messages_sent')
 */
class EnforceTenantQuota
{
    public function __construct(
        private readonly QuotaService $quotas,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next, string $metric): Response
    {
        if (! config('nonalix.quotas.enforce', true) || ! $this->context->has()) {
            return $next($request);
        }

        // Lève une QuotaExceededException, transformée en 429 par le handler.
        $this->quotas->assertWithinQuota($this->context->currentOrFail(), $metric);

        return $next($request);
    }
}
