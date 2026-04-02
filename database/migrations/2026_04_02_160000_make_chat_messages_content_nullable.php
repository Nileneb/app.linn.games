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
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
            $table->dropColumn('langdock_execution_id');
        });
    }
};
