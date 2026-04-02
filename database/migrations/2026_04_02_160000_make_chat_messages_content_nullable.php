<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
            $table->string('langdock_execution_id', 100)->nullable()->after('role');
            $table->timestamp('updated_at')->nullable();

            $table->index('langdock_execution_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['langdock_execution_id']);
            $table->dropColumn(['langdock_execution_id', 'updated_at']);
            $table->text('content')->nullable(false)->change();
        });
    }
};
