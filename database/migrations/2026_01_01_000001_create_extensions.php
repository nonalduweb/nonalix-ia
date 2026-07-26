<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extensions PostgreSQL requises.
 *
 * Le conteneur les crée déjà à l'initialisation du volume, mais une base
 * existante (production, base de test recréée) ne repasse jamais par ce script.
 * Cette migration rend l'installation reproductible dans tous les cas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // gen_random_uuid() et les primitives de hachage.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        // Type `vector` et index HNSW : indispensable au RAG.
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Recherche floue sur les noms de contacts (similarité trigramme).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        // Volontairement vide : supprimer `vector` détruirait toutes les
        // colonnes d'embeddings. Un rollback ne doit jamais faire ça.
    }
};
