<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Billing\QuotaService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identité du porteur du jeton et état de ses quotas.
 *
 * Premier appel recommandé pour une intégration : il permet de vérifier que
 * le jeton fonctionne et de connaître les limites avant de consommer l'API.
 */
class MeController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QuotaService $quotas,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user   = $request->user();
        $tenant = $this->context->currentOrFail();

        return response()->json([
            'data' => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ],
                'tenant' => [
                    'id'     => $tenant->id,
                    'name'   => $tenant->name,
                    'status' => $tenant->status->value,
                    'plan'   => $tenant->plan?->slug,
                ],
                'quotas' => collect(config('nonalix.quotas.metrics'))
                    ->mapWithKeys(fn (string $metric) => [$metric => [
                        'used'      => $this->quotas->current($tenant, $metric),
                        'limit'     => $tenant->quotaFor($metric),
                        'remaining' => $this->quotas->remaining($tenant, $metric),
                    ]])->all(),
            ],
        ]);
    }
}
