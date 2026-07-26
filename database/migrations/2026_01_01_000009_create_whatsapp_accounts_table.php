<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('waba_id', 40)->nullable();

            // Unicité GLOBALE, et non par tenant : c'est la seule exception à
            // la règle d'unicité composite du schéma, et elle est délibérée.
            // Un webhook Meta ne transporte que le phone_number_id ; s'il
            // pouvait correspondre à deux clients, la résolution serait
            // ambiguë et les messages d'une entreprise pourraient atterrir
            // chez une autre.
            $table->string('phone_number_id', 40)->unique();

            $table->string('display_phone_number', 32)->nullable();
            $table->string('verified_name', 160)->nullable();
            $table->string('business_id', 40)->nullable();

            // Secrets Meta : chiffrés applicativement (cast `encrypted`).
            // Un dump SQL volé ne permet pas d'usurper le numéro du client.
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('webhook_verify_token')->nullable();

            $table->string('quality_rating', 20)->nullable();
            $table->string('messaging_limit', 30)->nullable();

            $table->string('status', 20)->default('pending');
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('last_verified_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
