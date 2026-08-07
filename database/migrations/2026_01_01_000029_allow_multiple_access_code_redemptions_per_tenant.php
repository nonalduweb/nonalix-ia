<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autorise une entreprise à consommer plusieurs codes d'accès au fil du temps.
 *
 * La contrainte d'origine (« une entreprise ne naît que d'une seule
 * consommation ») décrivait le seul usage alors possible : l'inscription.
 * Depuis l'ouverture de la page Facturation, un client renouvelle ou surclasse
 * son abonnement en saisissant un nouveau code — un second passage, légitime,
 * que l'unicité sur `tenant_id` interdisait.
 *
 * L'unicité devient composite : un même code ne peut pas être consommé deux
 * fois par la même entreprise (ce qui resterait un abus), mais des codes
 * différents le peuvent. La trace de chaque consommation est ainsi conservée,
 * ce qui était tout l'objet de la table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_code_redemptions', function (Blueprint $table) {
            $table->dropUnique('access_code_redemptions_tenant_id_unique');
            $table->unique(['access_code_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        // Retour à « une consommation par entreprise » : on ne garde que la
        // plus ancienne, celle de l'inscription.
        DB::statement(<<<'SQL'
            DELETE FROM access_code_redemptions a
            USING access_code_redemptions b
            WHERE a.tenant_id = b.tenant_id
              AND (a.created_at > b.created_at
                   OR (a.created_at = b.created_at AND a.id > b.id))
        SQL);

        Schema::table('access_code_redemptions', function (Blueprint $table) {
            $table->dropUnique(['access_code_id', 'tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->unique('tenant_id');
        });
    }
};
