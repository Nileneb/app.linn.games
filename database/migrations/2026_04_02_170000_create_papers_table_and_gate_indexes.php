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

        // papers-Tabelle für Paper-Metadaten (separate von paper_embeddings)
        Schema::create('papers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('projekt_id')->nullable();
            $table->string('source', 50);
            $table->string('paper_id', 255);
            $table->text('title');
            $table->text('abstract')->nullable();
            $table->jsonb('authors')->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('url', 2048)->nullable();
            $table->integer('year')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->foreign('projekt_id')
                ->references('id')->on('projekte')
                ->nullOnDelete();

            $table->index('projekt_id');
            $table->index(['source', 'paper_id']);
        });

        DB::statement('ALTER TABLE papers ADD COLUMN erstellt_am timestamptz DEFAULT now()');

        // Gate-Query-Indizes (fehlen in Original-Migrationen)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_p4_suchstrings_projekt ON p4_suchstrings(projekt_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_p5_treffer_projekt_duplikat ON p5_treffer(projekt_id, ist_duplikat)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_p5_screening_entscheidung ON p5_screening_entscheidungen(entscheidung)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_p5_screening_entscheidung');
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_projekt_duplikat');
        DB::statement('DROP INDEX IF EXISTS idx_p4_suchstrings_projekt');

        Schema::dropIfExists('papers');
    }
};
