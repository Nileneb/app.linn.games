<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Remove webhook_id FK and langdock_execution_id from chat_messages
        Schema::table('chat_messages', function ($table) {
            if (Schema::hasColumn('chat_messages', 'webhook_id')) {
                $table->dropForeign(['webhook_id']);
                $table->dropColumn('webhook_id');
            }
            if (Schema::hasColumn('chat_messages', 'langdock_execution_id')) {
                $table->dropIndex(['langdock_execution_id']);
                $table->dropColumn('langdock_execution_id');
            }
        });

        Schema::dropIfExists('webhooks');

        DB::statement('DROP TYPE IF EXISTS webhook_frontend_object CASCADE');
    }

    public function down(): void
    {
        // Irreversible — webhook architecture removed
    }
};
