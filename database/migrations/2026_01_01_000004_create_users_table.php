<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // NULL = membre de l'équipe NONALIX (super-admin).
            // Un client supprimé emporte ses utilisateurs.
            $table->foreignUuid('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');

            // Chiffrés applicativement (cast `encrypted`) : une fuite de dump
            // SQL ne suffit pas à contourner la 2FA.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();

            $table->boolean('is_super_admin')->default(false);
            $table->string('status', 20)->default('active');

            $table->string('locale', 5)->default('fr');
            $table->string('avatar_path')->nullable();

            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();

            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'status']);
            $table->index('is_super_admin');
        });

        // Invariant structurel : un super-admin n'appartient à aucun client.
        // Posé en base plutôt qu'en PHP — c'est la garantie qu'aucun chemin de
        // code, présent ou futur, ne pourra le violer.
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_super_admin_has_no_tenant
            CHECK (is_super_admin = false OR tenant_id IS NULL)
        SQL);

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 190)->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
