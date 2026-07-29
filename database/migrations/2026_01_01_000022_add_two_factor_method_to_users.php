<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Méthode de second facteur choisie par l'utilisateur.
 *
 * La 2FA n'existait qu'en TOTP : elle imposait d'installer une application
 * d'authentification, ce qui écarte une partie des clients. Le code par
 * e-mail offre la même barrière — prouver l'accès à un canal distinct — sans
 * cette exigence.
 *
 * `totp` reste le défaut : plus sûr, puisqu'il ne dépend pas d'une boîte mail
 * qui pourrait elle-même être compromise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // totp | email
            $table->string('two_factor_method', 10)->default('totp')->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_method');
        });
    }
};
