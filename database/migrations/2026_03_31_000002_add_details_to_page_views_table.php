<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('path')->default('/')->after('id');
            $table->string('ip_anonymous')->nullable()->after('path');
            $table->string('user_agent')->nullable()->after('ip_anonymous');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn(['path', 'ip_anonymous', 'user_agent']);
        });
    }
};
