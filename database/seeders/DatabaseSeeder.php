<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // L'ordre compte : les permissions doivent exister avant qu'un rôle
        // ne les référence, et les plans avant qu'un tenant ne les utilise.
        $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoTenantSeeder::class);
        }
    }
}
