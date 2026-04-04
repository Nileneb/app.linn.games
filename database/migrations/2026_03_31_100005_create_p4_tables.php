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

        foreach (['p4_suchstrings', 'p4_thesaurus_mapping', 'p4_anpassungsprotokoll'] as $t) {
            if (! Schema::hasTable($t)) {
                DB::statement("DROP TYPE IF EXISTS \"{$t}\" CASCADE");
            }
        }

        if (! Schema::hasTable('p4_suchstrings')) {
            Schema::create('p4_suchstrings', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('datenbank');
                $table->text('suchstring');
                $table->text('feldeinschraenkung')->nullable();
                $table->jsonb('gesetzte_filter')->nullable();
                $table->integer('treffer_anzahl')->nullable();
                $table->text('einschaetzung')->nullable();
                $table->text('anpassung')->nullable();
                $table->text('version')->default('v1.0');
                $table->date('suchdatum')->nullable();
                $table->timestampTz('erstellt_am')->default(DB::raw('now()'));
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p4_thesaurus_mapping')) {
            Schema::create('p4_thesaurus_mapping', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('freitext_de')->nullable();
                $table->text('freitext_en')->nullable();
                $table->text('mesh_term')->nullable();
                $table->text('emtree_term')->nullable();
                $table->text('psycinfo_term')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p4_anpassungsprotokoll')) {
            Schema::create('p4_anpassungsprotokoll', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('suchstring_id');
                $table->text('version');
                $table->date('datum')->nullable();
                $table->text('aenderung')->nullable();
                $table->text('grund')->nullable();
                $table->integer('treffer_vorher')->nullable();
                $table->integer('treffer_nachher')->nullable();
                $table->text('entscheidung')->nullable();
                $table->foreign('suchstring_id')->references('id')->on('p4_suchstrings')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p4_anpassungsprotokoll');
        Schema::dropIfExists('p4_thesaurus_mapping');
        Schema::dropIfExists('p4_suchstrings');
    }
};
