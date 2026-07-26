<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne les valeurs par défaut sur le marché servi.
 *
 * Les colonnes étaient créées avec Europe/Paris et EUR. Le marché principal
 * étant l'Afrique de l'Ouest, chaque entreprise devait corriger ces deux
 * champs — et un fuseau erroné ne se voit pas : il fait simplement répondre
 * l'agent « nous sommes fermés » aux mauvaises heures.
 *
 * Seule la valeur par défaut change. Les lignes existantes sont laissées
 * telles quelles : une entreprise a pu délibérément choisir Europe/Paris, et
 * rien ne permet de distinguer ce choix d'un défaut jamais modifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE business_profiles ALTER COLUMN timezone SET DEFAULT 'Africa/Abidjan'");
        DB::statement("ALTER TABLE business_profiles ALTER COLUMN currency SET DEFAULT 'XOF'");
        DB::statement("ALTER TABLE services ALTER COLUMN currency SET DEFAULT 'XOF'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE business_profiles ALTER COLUMN timezone SET DEFAULT 'Europe/Paris'");
        DB::statement("ALTER TABLE business_profiles ALTER COLUMN currency SET DEFAULT 'EUR'");
        DB::statement("ALTER TABLE services ALTER COLUMN currency SET DEFAULT 'EUR'");
    }
};
