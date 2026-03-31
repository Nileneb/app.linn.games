<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p8_suchprotokoll', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('suchstring_id')->nullable();
            $table->text('datenbank');
            $table->date('suchdatum')->nullable();
            $table->text('db_version')->nullable();
            $table->text('suchstring_final');
            $table->jsonb('gesetzte_filter')->nullable();
            $table->integer('treffer_gesamt')->nullable();
            $table->integer('treffer_eindeutig')->nullable();
            $table->foreign('suchstring_id')->references('id')->on('p4_suchstrings')->nullOnDelete();
        });

        Schema::create('p8_limitationen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('projekt_id');
            $table->text('limitationstyp');
            $table->text('beschreibung')->nullable();
            $table->text('auswirkung_auf_vollstaendigkeit')->nullable();
            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
        });

        Schema::create('p8_reproduzierbarkeitspruefung', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('projekt_id');
            $table->text('pruefpunkt');
            $table->boolean('erfuellt')->nullable();
            $table->text('anmerkung')->nullable();
            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
        });

        Schema::create('p8_update_plan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('projekt_id');
            $table->text('update_typ')->nullable();
            $table->text('intervall')->nullable();
            $table->text('verantwortlich')->nullable();
            $table->text('tool')->nullable();
            $table->date('naechstes_update')->nullable();
            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE p8_update_plan ADD CONSTRAINT chk_update_typ CHECK (update_typ IN ('living_review', 'periodisch'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('p8_update_plan');
        Schema::dropIfExists('p8_reproduzierbarkeitspruefung');
        Schema::dropIfExists('p8_limitationen');
        Schema::dropIfExists('p8_suchprotokoll');
    }
};
