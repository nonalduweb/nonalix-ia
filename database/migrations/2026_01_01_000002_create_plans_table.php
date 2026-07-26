<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();

            // Montants en centimes, entiers : aucun flottant pour de la monnaie.
            $table->integer('price_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('interval', 10)->default('month');   // month | year

            // Limites du plan, ex. {"messages_sent": 5000, "ai_requests": 3000}
            $table->jsonb('quotas')->default('{}');

            // Drapeaux fonctionnels, ex. {"rag": true, "api_access": false}
            $table->jsonb('features')->default('{}');

            // block : refus au-delà du quota. soft : autorisé et facturé.
            $table->string('overage_policy', 10)->default('block');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->smallInteger('position')->default(0);

            $table->timestampsTz();

            $table->index(['is_active', 'is_public', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
