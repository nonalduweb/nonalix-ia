<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration métier de l'entreprise.
 *
 * Ces quatre tables alimentent directement le prompt de l'agent IA : c'est ici
 * que le client décrit ce qu'il vend, quand il est ouvert et à quel prix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Un profil et un seul par entreprise.
            $table->foreignUuid('tenant_id')->unique()
                ->constrained('tenants')->cascadeOnDelete();

            $table->string('legal_name', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('industry', 80)->nullable();
            $table->string('website')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('country', 2)->nullable();

            // Toutes les dates sont stockées en UTC ; ce fuseau sert à
            // l'affichage et au calcul des horaires d'ouverture.
            $table->string('timezone', 64)->default('Europe/Paris');
            $table->char('currency', 3)->default('EUR');
            $table->jsonb('languages')->default('["fr"]');

            $table->timestampsTz();
        });

        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // 0 = dimanche … 6 = samedi (convention ISO du front).
            $table->smallInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);

            $table->timestampsTz();

            // Plusieurs plages par jour : une entreprise peut fermer le midi.
            $table->index(['tenant_id', 'day_of_week']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 160);
            $table->text('description')->nullable();

            $table->integer('price_cents')->nullable();
            // fixed | from | hourly | quote — « à partir de » et « sur devis »
            // évitent que l'agent annonce un prix ferme à tort.
            $table->string('price_type', 10)->default('fixed');
            $table->char('currency', 3)->default('EUR');
            $table->integer('duration_minutes')->nullable();

            $table->string('category', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('position')->default(0);

            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active', 'position']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('question', 500);
            $table->text('answer');
            $table->string('category', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('position')->default(0);

            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('services');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('business_profiles');
    }
};
