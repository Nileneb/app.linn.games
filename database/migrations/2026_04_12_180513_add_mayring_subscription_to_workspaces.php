<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('mayring_subscription_id')->nullable()->after('stripe_customer_id');
            $table->boolean('mayring_active')->default(false)->after('mayring_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['mayring_subscription_id', 'mayring_active']);
        });
    }
};
