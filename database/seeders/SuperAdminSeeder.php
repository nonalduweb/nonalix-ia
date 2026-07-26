<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Premier compte NONALIX.
 *
 * Le mot de passe n'est JAMAIS écrit en dur : il vient de l'environnement, ou
 * bien un mot de passe aléatoire est généré et affiché une seule fois en
 * console. Un identifiant par défaut commité dans le dépôt serait une porte
 * d'entrée permanente sur toute la plateforme.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('NONALIX_ADMIN_EMAIL');

        if (! is_string($email) || $email === '') {
            $this->command?->warn(
                'NONALIX_ADMIN_EMAIL non défini : aucun super-admin créé. '
                .'Renseignez-la puis relancez `php artisan db:seed --class=SuperAdminSeeder`.'
            );

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Le super-admin {$email} existe déjà.");

            return;
        }

        $password  = env('NONALIX_ADMIN_PASSWORD');
        $generated = false;

        if (! is_string($password) || strlen($password) < 12) {
            $password  = Str::password(20);
            $generated = true;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $user = User::create([
            'tenant_id'         => null,
            'name'              => env('NONALIX_ADMIN_NAME', 'Administrateur NONALIX'),
            'email'             => $email,
            'password'          => Hash::make($password),
            'email_verified_at' => now(),
            'is_super_admin'    => true,
            'status'            => UserStatus::Active,
        ]);

        $user->assignRole(Role::findOrCreate('super-admin', 'web'));

        $this->command?->info("Super-admin créé : {$email}");

        if ($generated) {
            $this->command?->warn("Mot de passe généré : {$password}");
            $this->command?->warn('Notez-le maintenant : il ne sera plus affiché.');
        }

        // Rappel volontairement insistant : un super-admin sans 2FA est le
        // point de compromission le plus critique de la plateforme.
        $this->command?->warn('Activez l\'authentification à deux facteurs dès la première connexion.');
    }
}
