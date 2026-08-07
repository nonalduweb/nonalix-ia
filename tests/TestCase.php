<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Le contexte est un singleton : sans ce nettoyage, un test qui
        // positionne un tenant contaminerait le suivant et masquerait
        // précisément les fuites que la suite cherche à détecter.
        if ($this->app !== null) {
            $this->app->make(TenantContext::class)->forget();
        }

        parent::tearDown();
    }

    /**
     * Crée un tenant complet avec son utilisateur, ses rôles et son contexte.
     *
     * @param  array<int, string>  $permissions
     * @return array{0: Tenant, 1: User}
     */
    protected function createTenantWithUser(
        string $role = 'owner',
        array $permissions = [],
        array $tenantAttributes = [],
    ): array {
        $tenant = Tenant::factory()->create($tenantAttributes);

        // 2FA confirmée par défaut : elle est OBLIGATOIRE pour les rôles
        // `owner` et `admin`, et sans elle le middleware redirige vers l'écran
        // de configuration — la route testée ne serait jamais atteinte. Un
        // test portant sur le parcours 2FA lui-même crée son propre
        // utilisateur sans cet état.
        $user = User::factory()->for($tenant)->withTwoFactor()->create();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $roleModel = \Spatie\Permission\Models\Role::findOrCreate($role, 'web');

        foreach ($permissions ?: $this->defaultPermissions() as $permission) {
            $roleModel->givePermissionTo(
                \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web')
            );
        }

        $user->assignRole($roleModel);

        return [$tenant, $user];
    }

    /** Place le contexte applicatif sur un tenant donné. */
    protected function actingForTenant(Tenant $tenant): static
    {
        app(TenantContext::class)->set($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return $this;
    }

    /** Authentifie l'utilisateur ET positionne le contexte de son tenant. */
    protected function actingAsTenantUser(User $user): static
    {
        $this->actingAs($user);

        if ($user->tenant !== null) {
            $this->actingForTenant($user->tenant);
        }

        // Le middleware `2fa` s'appuie sur ce marqueur de session.
        $this->withSession(['auth.two_factor_verified' => true]);

        return $this;
    }

    /** @return array<int, string> */
    private function defaultPermissions(): array
    {
        return [
            'conversations.view', 'conversations.reply', 'conversations.assign',
            'conversations.toggle-ai', 'conversations.close',
            'contacts.view', 'contacts.update',
            'leads.view', 'leads.update',
            'knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.delete',
            'agent.view', 'agent.create', 'agent.update', 'agent.delete',
            'whatsapp.view', 'whatsapp.update',
            'settings.view', 'settings.update',
            'users.view', 'users.create', 'users.update',
        ];
    }
}
