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

        foreach (['p5_prisma_zahlen', 'p5_screening_kriterien', 'p5_treffer', 'p5_screening_entscheidungen', 'p5_tool_entscheidung'] as $t) {
            if (! Schema::hasTable($t)) {
                DB::statement("DROP TYPE IF EXISTS \"{$t}\" CASCADE");
            }
        }

        if (! Schema::hasTable('p5_prisma_zahlen')) {
            Schema::create('p5_prisma_zahlen', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->integer('identifiziert_gesamt')->nullable();
                $table->integer('davon_datenbank_treffer')->nullable();
                $table->integer('davon_graue_literatur')->nullable();
                $table->integer('nach_deduplizierung')->nullable();
                $table->integer('ausgeschlossen_l1')->nullable();
                $table->integer('volltext_geprueft')->nullable();
                $table->integer('ausgeschlossen_l2')->nullable();
                $table->integer('eingeschlossen_final')->nullable();
                $table->timestampTz('aktualisiert_am')->default(DB::raw('now()'));
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        // p5_screening_kriterien – uses screening_level and kriterium_typ ENUMs
        if (! Schema::hasTable('p5_screening_kriterien')) {
            Schema::create('p5_screening_kriterien', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('beschreibung');
                $table->text('beispiel')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });

            DB::statement("ALTER TABLE p5_screening_kriterien ADD COLUMN level screening_level NOT NULL, ADD COLUMN kriterium_typ kriterium_typ NOT NULL");
        }

        // p5_treffer – central hit table
        if (! Schema::hasTable('p5_treffer')) {
            Schema::create('p5_treffer', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('record_id');
                $table->text('titel')->nullable();
                $table->text('autoren')->nullable();
                $table->smallInteger('jahr')->nullable();
                $table->text('journal')->nullable();
                $table->text('doi')->nullable();
                $table->text('abstract')->nullable();
                $table->text('datenbank_quelle')->nullable();
                $table->boolean('ist_duplikat')->default(false);
                $table->uuid('duplikat_von')->nullable();
                $table->boolean('retrieval_downloaded')->nullable();
                $table->text('retrieval_source_url')->nullable();
                $table->text('retrieval_storage_path')->nullable();
                $table->text('retrieval_status')->nullable();
                $table->text('retrieval_last_response')->nullable();
                $table->timestampTz('retrieval_checked_at')->nullable();
                $table->timestampTz('erstellt_am')->default(DB::raw('now()'));
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
                $table->unique(['projekt_id', 'record_id']);
            });

            // Self-referencing FK must be added after table creation
            Schema::table('p5_treffer', function (Blueprint $table) {
                $table->foreign('duplikat_von')->references('id')->on('p5_treffer');
            });
        }

        // p5_screening_entscheidungen – uses screening_level and screening_entscheidung ENUMs
        if (! Schema::hasTable('p5_screening_entscheidungen')) {
            Schema::create('p5_screening_entscheidungen', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('treffer_id');
                $table->text('ausschlussgrund')->nullable();
                $table->text('reviewer')->nullable();
                $table->date('datum')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('treffer_id')->references('id')->on('p5_treffer')->cascadeOnDelete();
            });

            DB::statement("ALTER TABLE p5_screening_entscheidungen ADD COLUMN level screening_level NOT NULL, ADD COLUMN entscheidung screening_entscheidung NOT NULL");
        }

        // p5_tool_entscheidung – uses tool_empfehlung ENUM
        if (! Schema::hasTable('p5_tool_entscheidung')) {
            Schema::create('p5_tool_entscheidung', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->boolean('gewaehlt')->default(false);
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });

            DB::statement("ALTER TABLE p5_tool_entscheidung ADD COLUMN tool tool_empfehlung NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p5_tool_entscheidung');
        Schema::dropIfExists('p5_screening_entscheidungen');
        Schema::dropIfExists('p5_treffer');
        Schema::dropIfExists('p5_screening_kriterien');
        Schema::dropIfExists('p5_prisma_zahlen');
    }
};
