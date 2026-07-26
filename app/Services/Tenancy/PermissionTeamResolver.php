<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Branche le mode « teams » de spatie/laravel-permission sur notre tenant.
 *
 * Conséquence : un rôle `admin` n'existe que dans le périmètre d'une entreprise.
 * Le même utilisateur ne peut pas hériter des permissions d'un autre tenant,
 * même si un rôle homonyme y existe.
 *
 * Le constructeur doit rester SANS argument : PermissionRegistrar instancie
 * cette classe avec `new $class` sans passer par le conteneur. Le contexte est
 * donc résolu paresseusement, au premier appel.
 */
class PermissionTeamResolver implements PermissionsTeamResolver
{
    /**
     * Équipe des rôles plateforme (super-admins NONALIX).
     *
     * `tenant_id` fait partie de la clé primaire de `model_has_roles`, et
     * PostgreSQL interdit NULL dans une clé primaire : un rôle sans tenant ne
     * pourrait tout simplement pas être enregistré. On emploie donc l'UUID nul
     * comme sentinelle plutôt que NULL.
     *
     * Bénéfice secondaire : l'index unique (tenant_id, name, guard_name) de la
     * table `roles` redevient effectif pour les rôles plateforme — avec NULL,
     * PostgreSQL considère chaque ligne comme distincte et le même rôle
     * pourrait être créé en double.
     *
     * Aucune clé étrangère ne pointe vers `tenants` depuis ces colonnes :
     * cette valeur ne correspond donc à aucune entreprise réelle, et ne peut
     * pas entrer en collision avec un UUID v7 (dont les bits de version
     * diffèrent).
     */
    public const PLATFORM_TEAM_ID = '00000000-0000-0000-0000-000000000000';

    protected int|string|null $teamId = null;

    private ?TenantContext $context = null;

    public function getPermissionsTeamId(): int|string|null
    {
        // Une valeur posée explicitement (seeders, commandes, tests) prime sur
        // le contexte, afin de pouvoir attribuer des rôles hors requête HTTP.
        return $this->teamId
            ?? $this->context()->id()
            ?? self::PLATFORM_TEAM_ID;
    }

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        // `null` signifie explicitement « périmètre plateforme ».
        $this->teamId = $id === null ? self::PLATFORM_TEAM_ID : $id;
    }

    /**
     * Le contexte est un singleton : le mémoriser ici est sûr et évite un
     * appel au conteneur à chaque vérification de permission.
     */
    private function context(): TenantContext
    {
        return $this->context ??= app(TenantContext::class);
    }
}
