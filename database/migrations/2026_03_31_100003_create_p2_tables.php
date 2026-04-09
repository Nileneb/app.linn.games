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

        foreach (['p2_review_typ_entscheidung', 'p2_mapping_suchstring_komponenten', 'p2_trefferlisten', 'p2_cluster'] as $t) {
            if (! Schema::hasTable($t)) {
                DB::statement("DROP TYPE IF EXISTS \"{$t}\" CASCADE");
            }
        }

        if (! Schema::hasTable('p2_review_typ_entscheidung')) {
            Schema::create('p2_review_typ_entscheidung', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->boolean('passt')->nullable();
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement('ALTER TABLE p2_review_typ_entscheidung ADD COLUMN review_typ review_typ NOT NULL');
        }

        if (! Schema::hasTable('p2_mapping_suchstring_komponenten')) {
            Schema::create('p2_mapping_suchstring_komponenten', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('komponente_label');
                $table->text('sprache')->nullable();
                $table->boolean('trunkierung_genutzt')->default(false);
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement('ALTER TABLE p2_mapping_suchstring_komponenten ADD COLUMN suchbegriffe jsonb');
        }

        if (! Schema::hasTable('p2_trefferlisten')) {
            Schema::create('p2_trefferlisten', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('datenbank');
                $table->text('suchstring')->nullable();
                $table->integer('treffer_gesamt')->nullable();
                $table->text('einschaetzung')->nullable();
                $table->boolean('anpassung_notwendig')->default(false);
                $table->date('suchdatum')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p2_cluster')) {
            Schema::create('p2_cluster', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('cluster_id');
                $table->text('cluster_label');
                $table->text('beschreibung')->nullable();
                $table->integer('treffer_schaetzung')->nullable();
                $table->text('relevanz')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE p2_cluster ADD CONSTRAINT p2_cluster_relevanz_check CHECK (relevanz IN ('hoch', 'mittel', 'gering'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p2_cluster');
        Schema::dropIfExists('p2_trefferlisten');
        Schema::dropIfExists('p2_mapping_suchstring_komponenten');
        Schema::dropIfExists('p2_review_typ_entscheidung');
    }
};
