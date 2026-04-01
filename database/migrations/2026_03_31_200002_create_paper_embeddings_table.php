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

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('paper_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('projekt_id')->nullable();
            $table->string('source', 50);
            $table->string('paper_id', 500);
            $table->text('title');
            $table->integer('chunk_index');
            $table->text('text_chunk');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('erstellt_am')->useCurrent();

            $table->foreign('projekt_id')
                ->references('id')
                ->on('projekte')
                ->nullOnDelete();

            $table->index('projekt_id');
            $table->index(['source', 'paper_id']);
        });

        DB::statement('ALTER TABLE paper_embeddings ADD COLUMN embedding vector(768)');
        DB::statement('CREATE INDEX paper_embeddings_embedding_idx ON paper_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('paper_embeddings');
    }
};
