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

        // p6_qualitaetsbewertung – uses studientyp, rob_tool, rob_urteil ENUMs
        Schema::create('p6_qualitaetsbewertung', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('treffer_id');
            $table->text('hauptproblem')->nullable();
            $table->boolean('im_review_behalten')->default(true);
            $table->text('anmerkung')->nullable();
            $table->text('bewertet_von')->nullable();
            $table->date('bewertet_am')->nullable();
            $table->foreign('treffer_id')->references('id')->on('p5_treffer')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE p6_qualitaetsbewertung ADD COLUMN studientyp studientyp NOT NULL");
        DB::statement("ALTER TABLE p6_qualitaetsbewertung ADD COLUMN rob_tool rob_tool NOT NULL");
        DB::statement("ALTER TABLE p6_qualitaetsbewertung ADD COLUMN gesamturteil rob_urteil NOT NULL");

        Schema::create('p6_luckenanalyse', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('projekt_id');
            $table->text('fehlender_aspekt');
            $table->text('fehlender_studientyp')->nullable();
            $table->text('moegliche_konsequenz')->nullable();
            $table->text('empfehlung')->nullable();
            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p6_luckenanalyse');
        Schema::dropIfExists('p6_qualitaetsbewertung');
    }
};
