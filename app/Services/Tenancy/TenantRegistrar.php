<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Exceptions\AccessCodeUnusableException;
use App\Models\AccessCode;
use App\Models\AccessCodeRedemption;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Création d'une entreprise depuis l'inscription publique.
 *
 * Le code d'accès est consommé sous verrou de ligne, dans la même transaction
 * que la création de l'entreprise : sans cela, deux inscriptions simultanées
 * liraient toutes deux `used_count = 0` sur un code à usage unique et le
 * consommeraient chacune. Le verrou sérialise, la transaction garantit qu'un
 * échec ultérieur ne laisse pas un code décompté sans entreprise en face.
 */
class TenantRegistrar
{
    public function __construct(
        private readonly RoleProvisioner $roles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{company:string, name:string, email:string, password:string}  $data
     * @return array{tenant:Tenant, owner:User}
     *
     * @throws AccessCodeUnusableException
     */
    public function register(string $rawCode, array $data, ?string $ip = null): array
    {
        return DB::transaction(function () use ($rawCode, $data, $ip) {
            $code = AccessCode::query()
                ->where('code', AccessCode::normalize($rawCode))
                ->lockForUpdate()
                ->first();

            // Le contrôle est REFAIT ici, après le verrou. Celui de la
            // validation du formulaire a eu lieu hors transaction : entre les
            // deux, le code a pu être révoqué ou épuisé.
            if ($code === null || ! $code->isUsable()) {
                throw new AccessCodeUnusableException();
            }

            $tenant = Tenant::create([
                'name'          => $data['company'],
                'slug'          => $this->uniqueSlug($data['company']),
                'plan_id'       => $code->plan_id,
                'status'        => TenantStatus::Trial,
                'trial_ends_at' => now()->addDays($code->trial_days),
            ]);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                // Actif, mais l'accès reste fermé tant que l'adresse n'est pas
                // vérifiée puis la 2FA confirmée : deux barrières distinctes.
                'status'    => UserStatus::Active,
            ]);

            $this->roles->provisionAll((string) $tenant->id);
            $owner->assignRole(Role::findOrCreate('owner', 'web'));

            AccessCodeRedemption::create([
                'access_code_id' => $code->id,
                'tenant_id'      => $tenant->id,
                'user_id'        => $owner->id,
                'ip_address'     => $ip,
            ]);

            $code->increment('used_count');

            $this->audit->log('platform.tenant_registered', $tenant, [
                'after' => [
                    'name'  => $tenant->name,
                    'owner' => $owner->email,
                    'code'  => $code->code,
                    'plan'  => $code->plan?->slug,
                ],
            ]);

            return ['tenant' => $tenant, 'owner' => $owner];
        });
    }

    /**
     * Slug dérivé du nom, suffixé si nécessaire.
     *
     * Deux entreprises peuvent légitimement s'appeler pareil ; le slug porte
     * une contrainte d'unicité et ferait échouer la seconde inscription.
     */
    private function uniqueSlug(string $company): string
    {
        $base = Str::slug($company) ?: 'entreprise';
        $base = Str::limit($base, 70, '');
        $slug = $base;

        // withTrashed : `tenants` est en suppression douce, et la contrainte
        // d'unicité porte aussi sur les lignes supprimées. Sans cela, le slug
        // d'une entreprise effacée ferait échouer l'inscription suivante.
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
