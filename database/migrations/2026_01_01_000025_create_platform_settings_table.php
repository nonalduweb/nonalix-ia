<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de la plateforme, modifiables depuis la super-administration.
 *
 * Les clés IA ne vivaient que dans le `.env` du serveur. Un super-admin qui
 * n'a pas d'accès SSH ne pouvait donc pas en renseigner une — et l'absence de
 * clé plateforme ne se voyait qu'au moment où un client échouait à indexer un
 * document.
 *
 * Table centrale, non cloisonnée : ces valeurs valent pour toute la
 * plateforme et n'appartiennent à aucune entreprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ex. ai.openai.api_key
            $table->string('key', 120)->unique();

            // Chiffré au repos par le modèle : ces valeurs sont des secrets,
            // un dump SQL ne doit pas les livrer.
            $table->text('value')->nullable();

            $table->foreignUuid('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
