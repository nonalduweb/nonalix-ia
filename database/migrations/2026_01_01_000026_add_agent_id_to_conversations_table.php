<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignUuid('agent_id')
                ->nullable()
                ->after('whatsapp_account_id')
                ->constrained('agents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Le tableau contient des NOMS DE COLONNES : Laravel en dérive
            // lui-même le nom de la contrainte. Y mettre le nom de la
            // contrainte produirait `conversations_conversations_agent_id_foreign_foreign`
            // et ferait échouer le retour arrière.
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};
