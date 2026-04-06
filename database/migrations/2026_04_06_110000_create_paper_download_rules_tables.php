<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Papers downloads don't exist natively, but we now create a config table
        // for download decision rules/algorithms
        Schema::create('paper_download_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('projekt_id');
            $table->string('name'); // e.g., "open_access_only", "all_with_dois"
            $table->text('description')->nullable();
            $table->json('criteria'); // Structured filtering logic
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100); // Lower = higher priority
            $table->uuid('erstellt_von')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            $table->index(['projekt_id', 'is_active', 'priority']);
        });

        // Track actual download attempts per paper
        Schema::create('paper_download_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('paper_id');
            $table->uuid('projekt_id');
            $table->uuid('rule_id')->nullable(); // Which rule triggered this
            $table->enum('status', ['pending', 'success', 'failed', 'skipped']);
            $table->text('source_url')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('http_status_code')->nullable();
            $table->timestamps();

            $table->foreign('paper_id')->references('id')->on('papers')->cascadeOnDelete();
            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            $table->foreign('rule_id')->references('id')->on('paper_download_rules')->nullableOnDelete();
            $table->index(['projekt_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_download_attempts');
        Schema::dropIfExists('paper_download_rules');
    }
};
