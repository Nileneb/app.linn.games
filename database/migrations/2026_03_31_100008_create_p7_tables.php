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

        // hasTable-Guards sind bewusst gesetzt: Ermöglicht idempotente Ausführung
        // bei migrate:fresh und verhindert Fehler bei wiederholtem Migrationslauf.

        foreach (['p7_synthese_methode', 'p7_datenextraktion', 'p7_muster_konsistenz', 'p7_grade_einschaetzung'] as $t) {
            if (! Schema::hasTable($t)) {
                DB::statement("DROP TYPE IF EXISTS \"{$t}\" CASCADE");
            }
        }

        // p7_synthese_methode – uses synthese_methode ENUM
        if (! Schema::hasTable('p7_synthese_methode')) {
            Schema::create('p7_synthese_methode', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->boolean('gewaehlt')->default(false);
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });

            DB::statement('ALTER TABLE p7_synthese_methode ADD COLUMN methode synthese_methode NOT NULL');
        }

        // p7_datenextraktion – uses rob_urteil ENUM
        if (! Schema::hasTable('p7_datenextraktion')) {
            Schema::create('p7_datenextraktion', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('treffer_id');
                $table->text('land')->nullable();
                $table->text('stichprobe_kontext')->nullable();
                $table->text('phaenomen_intervention')->nullable();
                $table->text('outcome_ergebnis')->nullable();
                $table->text('hauptbefund')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('treffer_id')->references('id')->on('p5_treffer')->cascadeOnDelete();
            });

            DB::statement('ALTER TABLE p7_datenextraktion ADD COLUMN qualitaetsurteil rob_urteil');
        }

        // p7_muster_konsistenz – uses jsonb arrays (compatible with Laravel array cast)
        if (! Schema::hasTable('p7_muster_konsistenz')) {
            Schema::create('p7_muster_konsistenz', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('muster_befund');
                $table->text('moegliche_erklaerung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });

            DB::statement('ALTER TABLE p7_muster_konsistenz ADD COLUMN unterstuetzende_quellen jsonb, ADD COLUMN widersprechende_quellen jsonb');
        }

        // p7_grade_einschaetzung – uses rob_urteil, grade_urteil ENUMs
        if (! Schema::hasTable('p7_grade_einschaetzung')) {
            Schema::create('p7_grade_einschaetzung', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('outcome');
                $table->integer('studienanzahl')->nullable();
                $table->text('inkonsistenz')->nullable();
                $table->text('indirektheit')->nullable();
                $table->text('impraezision')->nullable();
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });

            DB::statement('ALTER TABLE p7_grade_einschaetzung ADD COLUMN rob_gesamt rob_urteil, ADD COLUMN grade_urteil grade_urteil NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p7_grade_einschaetzung');
        Schema::dropIfExists('p7_muster_konsistenz');
        Schema::dropIfExists('p7_datenextraktion');
        Schema::dropIfExists('p7_synthese_methode');
    }
};
