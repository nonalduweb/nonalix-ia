<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes d'accès : l'unique porte d'entrée vers la création d'une entreprise.
 *
 * L'inscription est fermée par défaut. Un code est émis par NONALIX, porte le
 * pack qu'il ouvre, la durée d'essai accordée et un nombre d'usages. Sans lui,
 * aucun compte ne peut être créé : la plateforme détient les jetons WhatsApp
 * de ses clients, une inscription ouverte en ferait une cible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Stocké en clair, à dessein : il doit être relu et communiqué au
            // prospect. Ce n'est pas un secret d'authentification mais un bon
            // d'entrée à usage limité, révocable et daté.
            $table->string('code', 32)->unique();

            // restrictOnDelete : un pack encore référencé par un code non
            // utilisé ne doit pas disparaître sous les pieds d'un prospect.
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();

            $table->string('label', 160)->nullable();   // ex. « Salon Pro 2026 »

            // 0 = illimité. Le compteur est incrémenté dans la transaction
            // d'inscription, sous verrou, pour qu'un code à usage unique ne
            // puisse pas être consommé deux fois en parallèle.
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);

            $table->unsignedSmallInteger('trial_days')->default(14);

            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();

            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('revoked_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            $table->index('plan_id');
            $table->index(['revoked_at', 'expires_at']);
        });

        // Trace des consommations : qui a utilisé quel code, et pour créer
        // quelle entreprise. Indispensable pour rattacher un client à une
        // opération commerciale, et pour constater un usage anormal.
        Schema::create('access_code_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('access_code_id')->constrained('access_codes')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->ipAddress('ip_address')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // Une entreprise ne naît que d'une seule consommation.
            $table->unique('tenant_id');
            $table->index('access_code_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_code_redemptions');
        Schema::dropIfExists('access_codes');
    }
};
