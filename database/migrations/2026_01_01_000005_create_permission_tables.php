<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables de spatie/laravel-permission en mode « teams ».
 *
 * `tenant_id` joue le rôle de team : un rôle `admin` n'a de sens qu'à
 * l'intérieur d'une entreprise. Les rôles plateforme (super-admin) ont un
 * `tenant_id` NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $team   = config('permission.column_names.team_foreign_key');

        Schema::create($tables['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('guard_name', 40);
            $table->string('description')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $table) use ($team) {
            $table->bigIncrements('id');
            $table->uuid($team)->nullable();
            $table->string('name', 120);
            $table->string('guard_name', 40);
            $table->string('description')->nullable();
            $table->timestampsTz();

            $table->index($team);
            // Un même nom de rôle peut exister chez plusieurs clients.
            $table->unique([$team, 'name', 'guard_name']);
        });

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($tables, $team) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid(config('permission.column_names.model_morph_key'));
            $table->uuid($team)->nullable();

            $table->index([
                config('permission.column_names.model_morph_key'), 'model_type',
            ], 'model_has_permissions_model_index');

            $table->foreign('permission_id')
                ->references('id')->on($tables['permissions'])
                ->cascadeOnDelete();

            $table->primary(
                [$team, 'permission_id', config('permission.column_names.model_morph_key'), 'model_type'],
                'model_has_permissions_primary'
            );
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($tables, $team) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid(config('permission.column_names.model_morph_key'));
            $table->uuid($team)->nullable();

            $table->index([
                config('permission.column_names.model_morph_key'), 'model_type',
            ], 'model_has_roles_model_index');

            $table->foreign('role_id')
                ->references('id')->on($tables['roles'])
                ->cascadeOnDelete();

            $table->primary(
                [$team, 'role_id', config('permission.column_names.model_morph_key'), 'model_type'],
                'model_has_roles_primary'
            );
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $table) use ($tables) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')->on($tables['permissions'])
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')->on($tables['roles'])
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });

        app('cache')->store(config('permission.cache.store') !== 'default'
            ? config('permission.cache.store')
            : null)->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }
};
