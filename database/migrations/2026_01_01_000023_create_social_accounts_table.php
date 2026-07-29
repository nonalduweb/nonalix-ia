<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes externes rattachés à un utilisateur.
 *
 * Table séparée plutôt que deux colonnes sur `users` : un même utilisateur
 * pourra rattacher plusieurs fournisseurs, et en retirer un sans toucher au
 * reste de son compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('provider', 20);          // google
            $table->string('provider_id', 191);      // identifiant stable chez le fournisseur
            $table->string('email', 190)->nullable();
            $table->string('avatar_url', 500)->nullable();

            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            // Un compte Google ne peut être rattaché qu'à un seul utilisateur.
            // Sans cette contrainte, deux comptes Nonalix pourraient se
            // partager la même identité externe et se voler mutuellement
            // l'accès.
            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
