<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\TenantStatus;
use App\Exceptions\AccessCodeRedemptionFailedException;
use App\Models\AccessCode;
use App\Models\AccessCodeRedemption;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\QuotaService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BillingController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QuotaService $quotas,
        private readonly AuditLogger $audit,
    ) {}

    /** Affiche la page de facturation de l'entreprise. */
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.view'), 403);

        $tenant = $this->context->currentOrFail();
        $plans = Plan::query()->where('is_public', true)->orderBy('position')->get();
        $subscription = $tenant->subscriptions()->orderByDesc('starts_at')->first();

        // Calculer l'état de consommation des quotas
        $usage = [];
        foreach (['messages_sent', 'ai_requests', 'documents_stored'] as $metric) {
            $usage[$metric] = [
                'used'  => $this->quotas->current($tenant, $metric),
                'limit' => $tenant->quotaFor($metric),
            ];
        }

        return Inertia::render('Settings/Billing', [
            'plans'        => $plans,
            'subscription' => $subscription ? [
                'id'                 => $subscription->id,
                'plan_name'          => $subscription->plan?->name ?? 'Essai',
                'status'             => $subscription->status,
                'starts_at'          => $subscription->starts_at?->toIso8601String(),
                'ends_at'            => $subscription->ends_at?->toIso8601String(),
                'external_reference' => $subscription->external_reference,
            ] : null,
            'usage'        => $usage,
            'tenantStatus' => $tenant->status->value,
            'trialEndsAt'  => $tenant->trial_ends_at?->toIso8601String(),
        ]);
    }

    /**
     * Valide et applique un code d'accès pour renouveler ou surclasser l'abonnement.
     *
     * Même discipline que TenantRegistrar : le code est verrouillé puis
     * RE-vérifié à l'intérieur de la transaction. Sans cela, deux saisies
     * simultanées d'un code à usage unique liraient le même `used_count` et le
     * consommeraient chacune.
     */
    public function redeem(Request $request): RedirectResponse
    {
        // Changer de plan engage l'entreprise : réservé à qui administre sa
        // configuration, pas à un opérateur ni à un lecteur.
        abort_unless($request->user()->can('settings.update'), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $tenant = $this->context->currentOrFail();
        $user   = $request->user();

        try {
            $planName = DB::transaction(function () use ($validated, $tenant, $user, $request) {
                $accessCode = AccessCode::query()
                    ->where('code', AccessCode::normalize($validated['code']))
                    ->lockForUpdate()
                    ->first();

                if ($accessCode === null) {
                    throw new AccessCodeRedemptionFailedException('Code d\'accès invalide ou inconnu.');
                }

                if (! $accessCode->isUsable()) {
                    throw new AccessCodeRedemptionFailedException(match ($accessCode->unusableReason()) {
                        'révoqué' => 'Ce code d\'accès a été révoqué.',
                        'expiré'  => 'Ce code d\'accès a expiré.',
                        default   => 'Ce code d\'accès a déjà atteint sa limite d\'utilisations.',
                    });
                }

                // Un même code ne peut pas servir deux fois à la même
                // entreprise : la contrainte d'unicité le refuserait plus bas,
                // autant le dire clairement au client.
                $alreadyUsed = AccessCodeRedemption::query()
                    ->where('access_code_id', $accessCode->id)
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                if ($alreadyUsed) {
                    throw new AccessCodeRedemptionFailedException('Vous avez déjà utilisé ce code d\'accès.');
                }

                $plan = $accessCode->plan;

                if ($plan === null) {
                    throw new AccessCodeRedemptionFailedException('Ce code n\'est rattaché à aucune offre. Contactez le support.');
                }

                $tenant->forceFill([
                    'plan_id'       => $plan->id,
                    // Sortie définitive de la période d'essai.
                    'trial_ends_at' => null,
                    'status'        => TenantStatus::Active,
                ])->save();

                // `tenant_id` n'est pas assignable en masse : BelongsToTenant
                // le renseigne depuis le contexte, qui est celui du client
                // connecté.
                Subscription::create([
                    'plan_id'            => $plan->id,
                    'status'             => 'active',
                    'starts_at'          => now(),
                    // La durée du pack suit les `trial_days` du code.
                    'ends_at'            => now()->addDays($accessCode->trial_days > 0 ? $accessCode->trial_days : 30),
                    'external_reference' => 'code_'.$accessCode->code,
                ]);

                // Trace de la consommation : c'est elle qui rattache le client
                // à l'opération commerciale, et qui interdit le rejeu.
                AccessCodeRedemption::create([
                    'access_code_id' => $accessCode->id,
                    'tenant_id'      => $tenant->id,
                    'user_id'        => $user->id,
                    'ip_address'     => $request->ip(),
                ]);

                $accessCode->increment('used_count');

                $this->audit->log('tenant.plan_upgraded_via_code', $tenant, [
                    'after' => ['plan' => $plan->slug, 'code' => $accessCode->code],
                ]);

                return $plan->name;
            });
        } catch (AccessCodeRedemptionFailedException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            // Le message d'exception peut porter des détails de schéma : on ne
            // le renvoie pas au navigateur.
            return back()->withErrors(['code' => 'Une erreur est survenue lors de l\'activation du code. Réessayez ou contactez le support.']);
        }

        return back()->with('success', sprintf(
            'Félicitations ! Votre abonnement au pack « %s » a été activé avec succès.',
            $planName,
        ));
    }
}
