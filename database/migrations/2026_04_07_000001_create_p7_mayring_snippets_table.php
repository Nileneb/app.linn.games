<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('p7_mayring_snippets')) {
            Schema::create('p7_mayring_snippets', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->uuid('paper_id');
                $table->text('snippet_text');
                $table->string('source_reference');
                $table->integer('chunk_index')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('category')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('projekt_id')->references('id')->on('projekte')->onDelete('cascade');
                $table->foreign('paper_id')->references('id')->on('p5_treffer')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index('projekt_id');
                $table->index('paper_id');
                $table->index('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p7_mayring_snippets');
    }
};
