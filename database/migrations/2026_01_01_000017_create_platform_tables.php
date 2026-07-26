<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables d'exploitation : audit, incidents, consommation.
 *
 * Toutes portent un `tenant_id` nullable : certains événements sont
 * plateforme (action d'un super-admin, panne globale) et n'appartiennent
 * à aucun client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')->nullable()
                ->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('action', 80);           // ex. whatsapp_account.updated
            $table->string('auditable_type', 160)->nullable();
            $table->uuid('auditable_id')->nullable();

            // Avant/après. Les secrets y sont REMPLACÉS par un marqueur avant
            // écriture (voir Support\Redaction) : un journal d'audit ne doit
            // jamais devenir une source de fuite.
            $table->jsonb('changes')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('context')->nullable();

            // Insert-only : ni update ni delete n'est exposé.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();

            $table->string('level', 10)->default('error');
            $table->string('source', 20);           // whatsapp | ai | quota | webhook | system
            $table->string('code', 60);
            $table->string('title', 250);
            $table->jsonb('context')->nullable();

            // Les occurrences identiques sont AGRÉGÉES plutôt que dupliquées :
            // 10 000 échecs du même type doivent produire une ligne, pas
            // 10 000 — sinon la table d'incidents devient illisible.
            $table->integer('occurrences')->default(1);
            $table->timestampTz('first_seen_at')->useCurrent();
            $table->timestampTz('last_seen_at')->useCurrent();

            $table->timestampTz('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'source', 'code']);
            $table->index(['level', 'resolved_at']);
            $table->index('last_seen_at');
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('metric', 40);
            $table->string('period', 7);            // AAAA-MM
            $table->bigInteger('value')->default(0);

            $table->timestampTz('recorded_at')->useCurrent();
            $table->timestampsTz();

            // Cible de l'upsert de réconciliation depuis Redis.
            $table->unique(['tenant_id', 'metric', 'period']);
            $table->index(['period', 'metric']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()
                ->constrained('conversations')->nullOnDelete();
            $table->foreignUuid('agent_id')->nullable()
                ->constrained('agents')->nullOnDelete();

            $table->string('provider', 20);
            $table->string('model', 80);
            $table->string('operation', 20)->default('chat');   // chat | embedding

            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);

            // Micro-centimes d'euro, entier : jamais de flottant pour un coût.
            $table->bigInteger('cost_micros')->default(0);

            $table->integer('latency_ms')->nullable();
            $table->string('status', 20)->default('success');
            $table->string('error_code', 60)->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'provider', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('audit_logs');
    }
};
