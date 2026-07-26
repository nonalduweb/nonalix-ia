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
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()
                ->constrained('conversations')->nullOnDelete();

            $table->string('status', 20)->default('new');
            $table->smallInteger('score')->default(0);      // 0 – 100

            // Réponses collectées par l'agent : besoin, budget, échéance…
            // Structure libre car elle dépend du secteur du client.
            $table->jsonb('qualification')->default('{}');

            $table->string('intent', 120)->nullable();
            $table->string('source', 20)->default('whatsapp_ai');

            $table->foreignUuid('assigned_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampTz('qualified_at')->nullable();
            // ai | user — distingue une qualification automatique d'une
            // décision humaine, ce qui change la confiance qu'on lui accorde.
            $table->string('qualified_by', 10)->nullable();

            $table->string('lost_reason', 160)->nullable();
            $table->timestampTz('next_action_at')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'next_action_at']);
        });

        // Un seul prospect ouvert par contact : sans cela, chaque nouvelle
        // conversation en créerait un doublon et fausserait les statistiques.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX leads_open_unique
            ON leads (tenant_id, contact_id)
            WHERE status NOT IN ('won', 'lost', 'unqualified')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
