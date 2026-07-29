<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes d'accès déposées depuis le site commercial.
 *
 * L'inscription exige un code, mais rien ne permettait d'en demander un : un
 * prospect arrivait sur un formulaire réclamant un code qu'il n'avait aucun
 * moyen d'obtenir. Cette table est le chaînon manquant entre la visite et la
 * création de compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('company', 160);
            $table->string('contact_name', 120);
            $table->string('email', 190);
            $table->string('phone', 32)->nullable();

            // Pack souhaité : indicatif, la décision reste au super-admin.
            $table->foreignUuid('plan_id')->nullable()
                ->constrained('plans')->nullOnDelete();

            $table->text('message')->nullable();

            // pending | approved | rejected
            $table->string('status', 12)->default('pending');

            // Code émis à l'approbation : garde le lien entre la demande et
            // l'entreprise qui en naîtra.
            $table->foreignUuid('access_code_id')->nullable()
                ->constrained('access_codes')->nullOnDelete();

            $table->text('review_note')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();

            // Le formulaire est public : l'adresse IP sert à repérer un dépôt
            // massif, que le seul limiteur de débit ne suffirait pas à établir.
            $table->ipAddress('ip_address')->nullable();

            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
