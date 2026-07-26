<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 120)->default('Assistant');

            // Le fournisseur est une donnée, pas du code : en changer ne
            // demande aucun déploiement (voir Contracts\AI\ChatProvider).
            $table->string('provider', 20)->default('openai');
            $table->string('model', 80);

            // Clé API propre au tenant (chiffrée). NULL = clés de la plateforme.
            $table->text('api_key')->nullable();

            $table->decimal('temperature', 3, 2)->default(0.30);
            $table->integer('max_tokens')->default(1024);

            $table->text('system_prompt')->nullable();
            $table->string('persona', 120)->nullable();
            $table->string('tone', 40)->default('professionnel');
            $table->string('language', 10)->default('fr');

            $table->text('greeting_message')->nullable();
            $table->text('fallback_message')->nullable();

            // Nombre de messages réinjectés en contexte à chaque tour.
            $table->smallInteger('memory_window')->default(12);

            $table->boolean('rag_enabled')->default(true);
            $table->smallInteger('rag_top_k')->default(5);
            $table->decimal('rag_min_score', 4, 3)->default(0.750);

            // Détectés AVANT tout appel au LLM : un client qui demande un
            // humain ne doit pas attendre une génération pour être transféré.
            $table->jsonb('handover_keywords')->default('[]');

            // Liste blanche : un agent ne peut invoquer que ces outils.
            $table->jsonb('enabled_tools')->default('[]');

            $table->boolean('active_hours_only')->default(false);
            $table->boolean('is_active')->default(true);

            $table->jsonb('settings')->default('{}');

            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
