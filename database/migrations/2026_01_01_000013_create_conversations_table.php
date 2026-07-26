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
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('whatsapp_account_id')
                ->constrained('whatsapp_accounts')->cascadeOnDelete();

            // Point d'extension omnicanal (Phase 6) : la colonne existe déjà
            // pour éviter une migration lourde sur une grosse table plus tard.
            $table->string('channel', 20)->default('whatsapp');

            $table->string('status', 20)->default('open');

            // L'opérateur peut couper l'IA sur une conversation précise, sans
            // toucher à la configuration globale de l'agent.
            $table->boolean('ai_enabled')->default(true);

            $table->foreignUuid('assigned_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampTz('handover_at')->nullable();
            $table->string('handover_reason', 60)->nullable();

            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('last_inbound_at')->nullable();

            // Dénormalisation assumée de `last_inbound_at + 24h` : permet de
            // filtrer les conversations joignables sans calcul à la volée.
            $table->timestampTz('window_expires_at')->nullable();

            $table->integer('unread_count')->default(0);

            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            // Requête principale de la boîte de réception.
            $table->index(['tenant_id', 'status', 'last_message_at']);
            $table->index(['tenant_id', 'assigned_user_id', 'status']);
            $table->index(['tenant_id', 'contact_id']);
        });

        // Une seule conversation ouverte par contact et par canal.
        // Index partiel : les conversations closes peuvent s'accumuler sans
        // empêcher la création d'une nouvelle.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX conversations_active_unique
            ON conversations (tenant_id, contact_id, channel)
            WHERE closed_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
