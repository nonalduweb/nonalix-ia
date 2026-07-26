<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();

            // Identifiant Meta. NULL tant que le message sortant n'est pas parti.
            // C'est la clé d'idempotence côté entrant.
            $table->string('wamid', 128)->nullable();

            $table->string('direction', 3);        // in | out
            $table->string('sender_type', 10);     // contact | ai | agent | system
            $table->foreignUuid('sender_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();

            // {id, mime_type, sha256, filename, storage_path}
            $table->jsonb('media')->nullable();

            $table->foreignUuid('template_id')->nullable()
                ->constrained('message_templates')->nullOnDelete();

            // Message cité (réponse à un message précédent).
            $table->string('context_wamid', 128)->nullable();

            $table->string('status', 12)->default('queued');
            // Erreur Meta brute : c'est ce qui permet d'arbitrer un litige.
            $table->jsonb('error')->nullable();

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('failed_at')->nullable();

            // Traçabilité IA : fournisseur, modèle, tokens, fragments RAG
            // utilisés, outils appelés. Sert au débogage et à l'explicabilité.
            $table->jsonb('ai_meta')->nullable();

            $table->timestampsTz();

            // Idempotence : un rejeu de webhook ne peut pas créer de doublon.
            $table->unique(['tenant_id', 'wamid']);

            // Fil de discussion.
            $table->index(['tenant_id', 'conversation_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('conversation_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Note interne : jamais transmise au contact ni au LLM.
            $table->text('body');

            $table->timestampsTz();

            $table->index(['tenant_id', 'conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_notes');
        Schema::dropIfExists('messages');
    }
};
