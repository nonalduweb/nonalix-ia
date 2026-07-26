<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions de la plateforme et rôle `super-admin`.
 *
 * Les permissions sont GLOBALES (elles décrivent des capacités), les rôles
 * sont cloisonnés par tenant. Les rôles clients (`owner`, `admin`, `agent`,
 * `viewer`) sont créés à la volée dans le périmètre de chaque entreprise.
 */
class PermissionSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const PERMISSIONS = [
        'conversations' => ['view', 'reply', 'assign', 'toggle-ai', 'close'],
        'contacts'      => ['view', 'create', 'update', 'delete'],
        'leads'         => ['view', 'create', 'update', 'delete'],
        'knowledge'     => ['view', 'create', 'update', 'delete'],
        'agent'         => ['view', 'update'],
        'whatsapp'      => ['view', 'update'],
        'settings'      => ['view', 'update'],
        'users'         => ['view', 'create', 'update', 'delete'],
        'stats'         => ['view'],
        'api'           => ['access'],
    ];

    /** Capacités réservées à l'équipe NONALIX. Préfixe `platform.` — voir Gate::before. */
    private const PLATFORM_PERMISSIONS = [
        'platform.tenants', 'platform.plans', 'platform.incidents',
        'platform.audit', 'platform.usage', 'platform.impersonate',
    ];

    public function run(): void
    {
        // Les rôles plateforme n'appartiennent à aucun tenant.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (self::PERMISSIONS as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}", 'web');
            }
        }

        foreach (self::PLATFORM_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Permissions attribuées à chaque rôle client.
     *
     * Exposé publiquement : le seeder de démonstration et la création d'un
     * tenant depuis l'administration s'appuient sur la même définition, pour
     * qu'un rôle signifie partout la même chose.
     *
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array
    {
        $all = [];

        foreach (self::PERMISSIONS as $resource => $actions) {
            foreach ($actions as $action) {
                $all[] = "{$resource}.{$action}";
            }
        }

        return [
            // Contrôle total sur son entreprise.
            'owner' => $all,

            // Tout sauf la suppression d'utilisateurs (réservée à l'owner).
            'admin' => array_values(array_diff($all, ['users.delete'])),

            // Opérationnel : la messagerie et les prospects, pas la configuration.
            'agent' => [
                'conversations.view', 'conversations.reply', 'conversations.assign',
                'conversations.toggle-ai', 'conversations.close',
                'contacts.view', 'contacts.update',
                'leads.view', 'leads.create', 'leads.update',
                'knowledge.view', 'stats.view',
            ],

            // Lecture seule.
            'viewer' => [
                'conversations.view', 'contacts.view', 'leads.view',
                'knowledge.view', 'stats.view',
            ],
        ];
    }
}
