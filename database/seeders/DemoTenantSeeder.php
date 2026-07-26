<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Agent;
use App\Models\BusinessHour;
use App\Models\BusinessProfile;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Entreprise de démonstration — environnement local uniquement.
 *
 * Permet de voir la plateforme fonctionner sans connexion WhatsApp réelle :
 * profil, horaires, prestations, FAQ et agent configuré.
 */
class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::query()->where('slug', 'demo')->exists()) {
            $this->command?->info('Le tenant de démonstration existe déjà.');

            return;
        }

        $tenant = Tenant::create([
            'name'          => 'Garage Martin',
            'slug'          => 'demo',
            'status'        => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
            'plan_id'       => Plan::query()->where('slug', 'starter')->firstOrFail()->id,
        ]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        // Rôles créés dans le périmètre du tenant, avec les mêmes définitions
        // que celles utilisées à la création d'un vrai client.
        foreach (PermissionSeeder::rolePermissions() as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions(
                Permission::query()->whereIn('name', $permissions)->get()
            );
        }

        $owner = User::create([
            'tenant_id'         => $tenant->id,
            'name'              => 'Sophie Martin',
            'email'             => 'demo@nonalixia.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'status'            => UserStatus::Active,
        ]);

        $owner->assignRole('owner');

        // Le contexte doit être posé : tout ce qui suit est cloisonné.
        app(TenantContext::class)->runAs($tenant, function () use ($owner) {
            BusinessProfile::create([
                'legal_name'  => 'Garage Martin SARL',
                'description' => 'Réparation et entretien automobile toutes marques.',
                'industry'    => 'Automobile',
                'phone'       => '+33450112233',
                'city'        => 'Annecy',
                'country'     => 'FR',
                'timezone'    => 'Europe/Paris',
            ]);

            // Lundi–vendredi, avec coupure du midi : cas réel qui valide la
            // gestion de plusieurs plages par jour.
            foreach (range(1, 5) as $day) {
                BusinessHour::create(['day_of_week' => $day, 'opens_at' => '08:00', 'closes_at' => '12:00']);
                BusinessHour::create(['day_of_week' => $day, 'opens_at' => '14:00', 'closes_at' => '18:30']);
            }

            BusinessHour::create(['day_of_week' => 6, 'opens_at' => '09:00', 'closes_at' => '12:00']);
            BusinessHour::create(['day_of_week' => 0, 'is_closed' => true]);

            $services = [
                ['name' => 'Vidange', 'price_cents' => 8900, 'price_type' => 'from', 'duration_minutes' => 45],
                ['name' => 'Révision complète', 'price_cents' => 24900, 'price_type' => 'from', 'duration_minutes' => 180],
                ['name' => 'Changement de plaquettes', 'price_cents' => 15900, 'price_type' => 'from', 'duration_minutes' => 90],
                ['name' => 'Diagnostic électronique', 'price_cents' => 5900, 'price_type' => 'fixed', 'duration_minutes' => 30],
                ['name' => 'Carrosserie', 'price_type' => 'quote', 'price_cents' => null],
            ];

            foreach ($services as $index => $service) {
                Service::create($service + ['position' => $index, 'currency' => 'EUR']);
            }

            $faqs = [
                ['question' => 'Faut-il prendre rendez-vous ?', 'answer' => 'Oui, pour toute intervention. Le diagnostic peut se faire sans rendez-vous le matin.'],
                ['question' => 'Proposez-vous un véhicule de prêt ?', 'answer' => 'Oui, sous réserve de disponibilité, pour les interventions de plus d\'une journée.'],
                ['question' => 'Quels moyens de paiement acceptez-vous ?', 'answer' => 'Carte bancaire, espèces et virement. Paiement en trois fois possible au-delà de 500 €.'],
            ];

            foreach ($faqs as $index => $faq) {
                Faq::create($faq + ['position' => $index]);
            }

            Agent::create([
                'name'          => 'Léa',
                'provider'      => config('ai.default'),
                'model'         => config('ai.providers.'.config('ai.default').'.default_model'),
                'persona'       => 'assistante du garage',
                'tone'          => 'chaleureux et professionnel',
                'language'      => 'fr',
                'system_prompt' => "Tu accueilles les clients du Garage Martin. Ton rôle est de "
                    ."renseigner sur les prestations, orienter vers une prise de rendez-vous et "
                    ."recueillir le besoin. Tu ne confirmes jamais un créneau toi-même.",
                'greeting_message'  => 'Bonjour ! Je suis Léa, du Garage Martin. Comment puis-je vous aider ?',
                'fallback_message'  => "Je préfère faire vérifier ce point par un collègue. Il vous recontacte rapidement.",
                'handover_keywords' => ['humain', 'conseiller', 'quelqu\'un', 'responsable'],
                'enabled_tools'     => ['request_human_handover', 'qualify_lead', 'list_services', 'get_business_hours'],
                'is_active'         => true,
            ]);
        });

        $this->command?->info('Tenant de démonstration créé : demo@nonalixia.test / password');
    }
}
