<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Identifiant WhatsApp : numéro E.164 sans le « + ».
            $table->string('wa_id', 32);
            $table->string('phone_number', 32)->nullable();

            $table->string('name', 160)->nullable();
            // Nom public du profil WhatsApp, fourni par Meta, non modifiable.
            $table->string('profile_name', 160)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('locale', 10)->nullable();

            $table->string('opt_in_status', 20)->default('unknown');
            $table->timestampTz('opt_in_at')->nullable();
            $table->timestampTz('opt_out_at')->nullable();
            $table->string('opt_in_source', 30)->nullable();

            $table->jsonb('attributes')->default('{}');

            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('blocked_at')->nullable();

            $table->timestampsTz();

            // Deux entreprises différentes peuvent légitimement avoir le même
            // contact : l'unicité est donc composite, jamais globale.
            $table->unique(['tenant_id', 'wa_id']);
            $table->index(['tenant_id', 'opt_in_status']);
            $table->index(['tenant_id', 'last_message_at']);
        });

        // Recherche floue sur le nom, cloisonnée par tenant en amont.
        DB::statement('CREATE INDEX contacts_name_trgm_idx ON contacts USING gin (name gin_trgm_ops)');

        Schema::create('consent_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();

            $table->string('action', 20);          // opt_in | opt_out
            $table->string('channel', 20)->default('whatsapp');
            $table->string('source', 30);          // keyword | dashboard | import | api
            $table->text('raw_message')->nullable();
            $table->ipAddress('ip_address')->nullable();

            // Insert-only : la preuve de consentement ne se modifie pas.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
        Schema::dropIfExists('contacts');
    }
};
