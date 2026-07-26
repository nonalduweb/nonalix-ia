<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de connaissances : documents sources et fragments vectorisés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('title', 250);
            $table->string('source_type', 10);      // pdf | docx | txt | url | manual
            $table->text('source_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->bigInteger('size_bytes')->nullable();

            // Empreinte du contenu : empêche d'indexer deux fois le même
            // fichier, et permet de détecter qu'une URL a changé.
            $table->string('checksum', 64)->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();

            $table->integer('chunks_count')->default(0);
            $table->integer('tokens_count')->default(0);

            // Trace l'espace vectoriel employé. Changer de modèle rend les
            // embeddings existants incomparables : le réindexage s'appuie
            // sur ces deux colonnes pour savoir quoi refaire.
            $table->string('embedding_provider', 20)->nullable();
            $table->string('embedding_model', 80)->nullable();

            $table->timestampTz('processed_at')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'checksum']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();

            $table->integer('position');
            $table->text('content');
            $table->integer('tokens')->default(0);

            // Page, titre de section, URL d'origine — restitués à l'agent pour
            // qu'il puisse citer sa source.
            $table->jsonb('metadata')->default('{}');

            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'document_id', 'position']);
        });

        // La dimension est figée au niveau de la plateforme : pgvector exige
        // une taille fixe par colonne, et mélanger deux espaces vectoriels
        // produirait des scores de similarité silencieusement faux.
        $dimensions = (int) config('ai.embeddings.dimensions', 1536);

        DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");

        // HNSW : recherche approximative, bien plus rapide qu'IVFFlat sur des
        // volumes modestes et sans phase d'entraînement préalable.
        // Distance cosinus : les embeddings sont normalisés.
        DB::statement(<<<'SQL'
            CREATE INDEX document_chunks_embedding_idx
            ON document_chunks USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
