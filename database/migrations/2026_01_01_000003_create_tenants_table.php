<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name', 160);
            $table->string('slug', 80)->unique();

            $table->string('status', 20)->default('trial');
            $table->timestampTz('trial_ends_at')->nullable();

            // RESTRICT : on ne supprime pas un plan encore utilisé par un client.
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();

            // Dépassements de quotas accordés au cas par cas par NONALIX.
            // Surcharge `plans.quotas` métrique par métrique.
            $table->jsonb('quota_overrides')->default('{}');

            $table->timestampTz('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->foreignUuid('suspended_by')->nullable();

            $table->jsonb('settings')->default('{}');

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
