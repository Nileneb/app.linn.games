<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunk_codierungen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->foreignUuid('paper_embedding_id')->constrained('paper_embeddings')->cascadeOnDelete();
            $table->text('paraphrase')->nullable();
            $table->text('generalisierung')->nullable();
            $table->text('reduktion')->nullable();
            $table->text('kategorie')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['projekt_id', 'status'], 'idx_chunk_codierungen_projekt_status');
            $table->unique(['paper_embedding_id'], 'uq_chunk_codierungen_embedding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunk_codierungen');
    }
};
