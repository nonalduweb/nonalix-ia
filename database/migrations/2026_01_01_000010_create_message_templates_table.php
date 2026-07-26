<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de messages WhatsApp, synchronisés depuis Meta.
 *
 * Seul un template approuvé permet de contacter un utilisateur hors de la
 * fenêtre de 24 h. Le statut est donc une donnée opérationnelle, pas cosmétique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('whatsapp_account_id')
                ->constrained('whatsapp_accounts')->cascadeOnDelete();

            $table->string('meta_template_id', 40)->nullable();
            $table->string('name', 160);
            $table->string('language', 10);
            $table->string('category', 20)->default('utility');

            $table->string('status', 20)->default('pending');
            $table->text('rejected_reason')->nullable();

            // Structure Meta brute (header, body, footer, buttons) : conservée
            // telle quelle pour pouvoir la renvoyer sans transformation.
            $table->jsonb('components')->default('[]');

            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();

            // Meta autorise le même nom dans plusieurs langues.
            $table->unique(['tenant_id', 'name', 'language']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
