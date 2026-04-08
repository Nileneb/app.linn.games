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

        Schema::create('agent_result_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('user_id');
            $table->uuid('projekt_id');
            $table->text('chunk_text');
            $table->string('source_file', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index('workspace_id');
            $table->index('user_id');
            $table->index('projekt_id');
            $table->index(['workspace_id', 'user_id', 'projekt_id']);
        });

        DB::statement('ALTER TABLE agent_result_embeddings ADD COLUMN embedding vector(768) NOT NULL');
        DB::statement('CREATE INDEX agent_result_embeddings_embedding_idx ON agent_result_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('agent_result_embeddings');
    }
};
