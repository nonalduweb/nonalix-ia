<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adresse d'entrée du canal e-mail, frappée par la plateforme.
 *
 * Le webhook résolvait l'entreprise en comparant le destinataire au champ
 * `business_profiles.email`, une saisie libre sans contrainte d'unicité : deux
 * entreprises pouvaient déclarer la même adresse, et rien n'empêchait d'y
 * inscrire celle d'un concurrent pour recevoir son courrier.
 *
 * L'adresse d'entrée est désormais frappée par nous et jamais déclarée. Le
 * jeton est distinct de `tenants.id`, qui est PUBLIC — il figure dans le
 * snippet du widget que chaque client colle sur son site. Le déduire ne doit
 * pas suffire à écrire dans la boîte d'un client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('inbound_email_token', 32)->nullable()->unique();

            // Redirection constatée : positionnée par l'arrivée d'un message
            // sonde, jamais par une déclaration du client.
            $table->timestampTz('email_forward_verified_at')->nullable();

            $table->string('email_probe_code', 16)->nullable();
            $table->timestampTz('email_probe_sent_at')->nullable();
        });

        // Les entreprises déjà inscrites en reçoivent une immédiatement :
        // sans jeton, leur canal e-mail serait inutilisable.
        DB::table('tenants')->whereNull('inbound_email_token')->orderBy('id')
            ->each(function ($tenant) {
                DB::table('tenants')->where('id', $tenant->id)->update([
                    'inbound_email_token' => Str::lower(Str::random(24)),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'inbound_email_token',
                'email_forward_verified_at',
                'email_probe_code',
                'email_probe_sent_at',
            ]);
        });
    }
};
