<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use App\Support\Redaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Écriture du journal d'audit.
 *
 * Principe : l'audit ne doit JAMAIS faire échouer l'action qu'il observe.
 * Si l'écriture du journal échoue, on le signale dans les logs applicatifs et
 * on laisse passer — perdre une ligne d'audit est regrettable, bloquer l'envoi
 * d'un message client ne l'est pas moins et se voit tout de suite.
 */
class AuditLogger
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>|null  $changes
     * @param  array<string, mixed>       $context
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $changes = null,
        array $context = [],
    ): void {
        try {
            $request = request();
            $user    = Auth::user();

            AuditLog::create([
                'tenant_id'      => $this->context->id() ?? $user?->tenant_id,
                'user_id'        => $user?->id,
                'action'         => $action,
                'auditable_type' => $auditable === null ? null : $auditable::class,
                'auditable_id'   => $auditable?->getKey(),
                'changes'        => $changes === null ? null : Redaction::scrub($changes),
                'ip_address'     => $request?->ip(),
                'user_agent'     => mb_substr((string) $request?->userAgent(), 0, 500) ?: null,
                'context'        => Redaction::scrub($context),
                'created_at'     => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Échec d\'écriture du journal d\'audit.', [
                'action'    => $action,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Journalise la modification d'un modèle en ne conservant que les
     * attributs réellement modifiés (avant/après).
     */
    public function logUpdate(string $action, Model $model): void
    {
        $changes  = $model->getChanges();
        $original = array_intersect_key($model->getOriginal(), $changes);

        if ($changes === []) {
            return;
        }

        $this->log($action, $model, [
            'before' => $original,
            'after'  => $changes,
        ]);
    }

    /**
     * Trace une opération volontairement exécutée hors cloisonnement.
     *
     * Toute utilisation de TenantContext::runWithout() dans un flux
     * d'administration doit passer par ici : c'est le seul moyen de vérifier
     * a posteriori qui a consulté quoi.
     */
    public function logCrossTenantAccess(string $reason, array $context = []): void
    {
        $this->log('platform.cross_tenant_access', context: array_merge($context, [
            'reason' => $reason,
        ]));
    }
}
