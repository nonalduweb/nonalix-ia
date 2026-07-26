<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal brut des webhooks entrants et garantie d'idempotence.
 *
 * Meta rejoue les webhooks tant qu'il n'a pas reçu de 200, et peut aussi
 * livrer plusieurs fois le même événement. `idempotency_key` transforme ce
 * comportement en non-problème : une clé déjà connue est acquittée sans
 * retraitement, donc pas de message en double dans la conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // NULL possible : un webhook dont la signature est invalide est
            // tracé avant même qu'on ait pu faire confiance au tenant d'URL.
            $table->foreignUuid('tenant_id')->nullable()
                ->constrained('tenants')->nullOnDelete();

            $table->string('provider', 20)->default('whatsapp');
            $table->string('event_type', 40)->nullable();

            // sha256(tenant + wamid + type + statut) — unicité globale.
            $table->string('idempotency_key', 64)->unique();

            $table->boolean('signature_valid')->default(false);
            $table->jsonb('payload');

            $table->string('status', 20)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();

            $table->index(['tenant_id', 'status']);
            // Sert la commande de purge (rétention 30 jours par défaut).
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
