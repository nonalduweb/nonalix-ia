<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ouvre la création et la suppression d'agents IA.
 *
 * Le catalogue ne connaissait que `agent.view` et `agent.update`, hérités du
 * temps où chaque entreprise n'avait qu'un seul agent créé à la volée. Depuis
 * le passage au multi-agents, AgentPolicy vérifie `create` et `delete` : sans
 * ces deux permissions, la création et la suppression répondaient 403 à tout
 * le monde, y compris au propriétaire de l'entreprise.
 *
 * Les permissions sont globales ; seuls les rôles sont cloisonnés par tenant.
 * On les rattache donc à chaque rôle `owner` / `admin` déjà provisionné, sans
 * toucher au reste de leur configuration (une entreprise a pu l'ajuster).
 */
return new class extends Migration
{
    private const PERMISSIONS = ['agent.create', 'agent.delete'];

    /** Rôles clients qui reçoivent la configuration de l'agent. */
    private const ROLES = ['owner', 'admin'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard  = 'web';

        foreach (self::PERMISSIONS as $name) {
            DB::table($tables['permissions'])->insertOrIgnore([
                'name'       => $name,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table($tables['permissions'])
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', $guard)
            ->pluck('id');

        // `super-admin` porte l'intégralité du catalogue par définition.
        $roleIds = DB::table($tables['roles'])
            ->where('guard_name', $guard)
            ->where(fn ($q) => $q->whereIn('name', self::ROLES)->orWhere('name', 'super-admin'))
            ->pluck('id');

        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
        }

        if ($rows !== []) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore($rows);
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        $permissionIds = DB::table($tables['permissions'])
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table($tables['role_has_permissions'])->whereIn('permission_id', $permissionIds)->delete();
            DB::table($tables['permissions'])->whereIn('id', $permissionIds)->delete();
        }

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        app('cache')->store(config('permission.cache.store') !== 'default'
            ? config('permission.cache.store')
            : null)->forget(config('permission.cache.key'));
    }
};
