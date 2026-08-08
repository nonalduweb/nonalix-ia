<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socle du canal vocal.
 *
 * Deux ajouts seulement. Les réglages vocaux d'un agent — voix, langue, mode
 * de réponse — vivent dans `agents.settings`, le jsonb qui porte déjà les
 * réglages n8n et widget : ils n'ont pas à devenir des colonnes.
 *
 * La clé ElevenLabs, elle, en mérite une : une clé ne se range pas dans un
 * jsonb en clair. Elle suit exactement le patron de `agents.api_key`, chiffrée
 * au repos par le cast Eloquent.
 *
 * La consommation se greffe sur `ai_usage_logs` plutôt que d'ouvrir une table
 * parallèle. Deux colonnes suffisent : compter des secondes d'audio dans
 * `input_tokens` fausserait les analyses de jetons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->text('elevenlabs_api_key')->nullable()->after('api_key');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            // Secondes d'audio transcrites ou synthétisées.
            $table->decimal('audio_seconds', 10, 2)->nullable()->after('output_tokens');

            // Caractères envoyés à la synthèse : l'unité facturée par ElevenLabs.
            $table->integer('characters')->nullable()->after('audio_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('elevenlabs_api_key');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['audio_seconds', 'characters']);
        });
    }
};
