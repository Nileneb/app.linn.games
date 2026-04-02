<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->timestamps();
        });

        Schema::create('workspace_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('editor');
            $table->timestamps();

            $table->foreign('workspace_id', 'workspace_users_workspace_fk')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();

            $table->unique(['workspace_id', 'user_id'], 'workspace_users_workspace_user_unique');
            $table->index(['user_id', 'workspace_id'], 'workspace_users_user_workspace_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE workspace_users ADD CONSTRAINT workspace_users_role_check CHECK (role IN ('owner', 'editor', 'viewer'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE workspace_users DROP CONSTRAINT IF EXISTS workspace_users_role_check');
        }

        Schema::dropIfExists('workspace_users');
        Schema::dropIfExists('workspaces');
    }
};
