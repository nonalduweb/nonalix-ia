<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Création des rôles clients dans le périmètre d'une entreprise.
 *
 * Les permissions sont globales, les rôles sont cloisonnés par tenant : un
 * `owner` existe donc autant de fois qu'il y a d'entreprises, et chacun doit
 * recevoir ses permissions à la création.
 *
 * Un simple `Role::findOrCreate()` produit un rôle VIDE. Attribué tel quel, il
 * n'accorde rien : toute vérification `can()` échoue et l'utilisateur se
 * heurte à des 403 sur l'intégralité de son espace, alors même qu'il porte le
 * rôle attendu. C'est ce qui se produisait pour toute entreprise créée depuis
 * l'administration.
 *
 * La définition de référence vit dans PermissionSeeder::rolePermissions(),
 * pour qu'un rôle signifie la même chose partout — seeder de démonstration,
 * création depuis l'administration, invitation d'un membre d'équipe.
 */
class RoleProvisioner
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Crée les quatre rôles clients d'une entreprise et leurs permissions.
     *
     * Idempotent : rejouable sur une entreprise existante pour réaligner ses
     * rôles après une évolution du catalogue de permissions.
     */
    public function provisionAll(string $tenantId): void
    {
        $this->registrar->setPermissionsTeamId($tenantId);

        foreach (PermissionSeeder::rolePermissions() as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Récupère un rôle du tenant courant, en le créant avec ses permissions
     * s'il n'existe pas encore.
     */
    public function role(string $name): Role
    {
        $role = Role::findOrCreate($name, 'web');

        // Un rôle déjà peuplé n'est pas retouché : une entreprise peut avoir
        // ajusté les permissions de son rôle, et les écraser ici annulerait
        // silencieusement sa configuration.
        if ($role->permissions()->count() === 0) {
            $role->syncPermissions(PermissionSeeder::rolePermissions()[$name] ?? []);
            $this->registrar->forgetCachedPermissions();
        }

        return $role;
    }
}
