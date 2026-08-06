<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abaisse le seuil de pertinence de la recherche documentaire.
 *
 * Le défaut était 0,750. Or avec text-embedding-3-small, la similarité
 * cosinus d'un extrait réellement pertinent tourne autour de 0,40 à 0,60 :
 * ce seuil ne laissait jamais rien passer et désactivait la base de
 * connaissances en silence. Constaté en production — un agent répondait
 * « je n'ai pas accès à la liste des services » alors que ses documents la
 * contenaient, le meilleur extrait plafonnant à 0,496.
 *
 * Seuls les agents restés sur l'ancienne valeur exacte sont réalignés : un
 * client ayant délibérément choisi 0,75 garde son réglage, et rien ne permet
 * de le distinguer d'un défaut jamais modifié — sauf que le laisser
 * reviendrait à laisser sa base inexploitable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE agents ALTER COLUMN rag_min_score SET DEFAULT 0.350');

        DB::table('agents')->where('rag_min_score', 0.750)->update(['rag_min_score' => 0.350]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE agents ALTER COLUMN rag_min_score SET DEFAULT 0.750');
    }
};
