<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            if (! Schema::hasColumn('projekte', 'workspace_id')) {
                $table->uuid('workspace_id')->nullable()->after('user_id');
            }
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'workspace_id')) {
                $table->uuid('workspace_id')->nullable()->after('user_id');
            }
        });

        $users = DB::table('users')->select('id', 'name', 'email')->get();

        foreach ($users as $user) {
            $workspaceId = (string) Str::uuid();
            $workspaceName = trim((string) ($user->name ?: 'Workspace')) . ' Workspace';

            DB::table('workspaces')->insert([
                'id' => $workspaceId,
                'owner_id' => $user->id,
                'name' => $workspaceName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('workspace_users')->insert([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('projekte')
                ->where('user_id', $user->id)
                ->update(['workspace_id' => $workspaceId]);

            DB::table('chat_messages')
                ->where('user_id', $user->id)
                ->update(['workspace_id' => $workspaceId]);
        }

        if (DB::table('projekte')->whereNull('workspace_id')->exists()) {
            throw new RuntimeException('workspace_id backfill for projekte failed.');
        }

        if (DB::table('chat_messages')->whereNull('workspace_id')->exists()) {
            throw new RuntimeException('workspace_id backfill for chat_messages failed.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projekte ALTER COLUMN workspace_id SET NOT NULL');
            DB::statement('ALTER TABLE chat_messages ALTER COLUMN workspace_id SET NOT NULL');
        } else {
            Schema::table('projekte', function (Blueprint $table) {
                $table->uuid('workspace_id')->nullable(false)->change();
            });
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->uuid('workspace_id')->nullable(false)->change();
            });
        }

        Schema::table('projekte', function (Blueprint $table) {
            $table->foreign('workspace_id', 'projekte_workspace_fk')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreign('workspace_id', 'chat_messages_workspace_fk')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'workspace_id')) {
                $table->dropForeign('chat_messages_workspace_fk');
                $table->dropColumn('workspace_id');
            }
        });

        Schema::table('projekte', function (Blueprint $table) {
            if (Schema::hasColumn('projekte', 'workspace_id')) {
                $table->dropForeign('projekte_workspace_fk');
                $table->dropColumn('workspace_id');
            }
        });
    }
};
