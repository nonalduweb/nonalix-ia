<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rend le compte WhatsApp facultatif sur une conversation.
 *
 * La colonne datait du temps où WhatsApp était le seul canal. Depuis
 * l'ouverture du widget de chat, une conversation `web` n'a aucun compte
 * WhatsApp à rattacher — et le cas nominal d'un client qui n'utilise QUE le
 * widget n'en possède même aucun.
 *
 * Sans ce changement, l'insertion échouait sur la contrainte NOT NULL, ce que
 * le contrôleur contournait en empruntant le compte d'un autre tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('whatsapp_account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Les conversations web n'ont pas de compte WhatsApp : les conserver
        // rendrait le retour arrière impossible. On les supprime, elles n'ont
        // aucun sens dans un schéma antérieur au canal web.
        Schema::disableForeignKeyConstraints();

        DB::table('conversations')
            ->whereNull('whatsapp_account_id')
            ->delete();

        Schema::enableForeignKeyConstraints();

        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('whatsapp_account_id')->nullable(false)->change();
        });
    }
};
