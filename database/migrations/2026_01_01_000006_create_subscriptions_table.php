<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des abonnements.
 *
 * Aucune intégration de paiement en Phase 1 : la table porte l'état
 * contractuel, `external_reference` accueillera l'identifiant Stripe en
 * Phase 5 sans migration destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();

            $table->string('status', 20)->default('trialing');

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();

            $table->string('external_reference')->nullable();
            $table->jsonb('meta')->default('{}');

            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
