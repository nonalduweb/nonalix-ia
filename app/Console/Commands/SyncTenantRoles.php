<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\RoleProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Réaligne les rôles clients sur le catalogue de permissions.
 *
 * Deux usages :
 *
 *   - réparer les entreprises créées avant que la création de tenant ne
 *     provisionne les permissions — leurs rôles existent mais sont vides, et
 *     leurs utilisateurs se heurtent à des 403 sur tout leur espace ;
 *   - propager l'ajout d'une permission au catalogue, après avoir rejoué
 *     `db:seed --class=PermissionSeeder`.
 */
class SyncTenantRoles extends Command
{
    protected $signature = 'nonalix:sync-roles {tenant? : slug ou UUID ; toutes les entreprises si omis}
                                               {--dry-run : liste les entreprises concernées sans rien modifier}';

    protected $description = 'Recrée les rôles clients et leurs permissions pour une ou toutes les entreprises.';

    public function handle(RoleProvisioner $roles): int
    {
        $query = Tenant::query();

        if ($needle = $this->argument('tenant')) {
            // `id` est une colonne uuid : lui comparer un slug ferait échouer
            // PostgreSQL (invalid input syntax for type uuid) au lieu de ne
            // simplement rien trouver.
            $query->where(function ($q) use ($needle) {
                $q->where('slug', $needle);

                if (Str::isUuid($needle)) {
                    $q->orWhere('id', $needle);
                }
            });
        }

        $tenants = $query->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Aucune entreprise ne correspond.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            // Compté avant l'opération : c'est le nombre de rôles vides qui
            // révèle une entreprise cassée, et il vaut 0 après coup.
            // Requête directe sur Role : le cloisonnement passe par la colonne
            // tenant_id (clé d'équipe spatie), sans relation sur Tenant.
            $empty = Role::query()
                ->where('tenant_id', $tenant->id)
                ->doesntHave('permissions')
                ->count();

            if ($this->option('dry-run')) {
                $this->line(sprintf('  %-30s %d rôle(s) sans permission', $tenant->slug, $empty));

                continue;
            }

            $roles->provisionAll((string) $tenant->id);
            $this->info(sprintf('  %-30s rôles réalignés (%d étaient vides)', $tenant->slug, $empty));
        }

        return self::SUCCESS;
    }
}
