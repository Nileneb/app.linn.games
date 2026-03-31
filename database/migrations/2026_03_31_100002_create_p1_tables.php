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

        if (! Schema::hasTable('p1_strukturmodell_wahl')) {
            Schema::create('p1_strukturmodell_wahl', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->boolean('gewaehlt')->default(false);
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE p1_strukturmodell_wahl ADD COLUMN modell strukturmodell NOT NULL");
        }

        if (! Schema::hasTable('p1_komponenten')) {
            Schema::create('p1_komponenten', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('komponente_kuerzel');
                $table->text('komponente_label');
                $table->text('inhaltlicher_begriff_de')->nullable();
                $table->text('englische_entsprechung')->nullable();
                $table->text('mesh_term')->nullable();
                $table->text('thesaurus_term')->nullable();
                $table->text('anmerkungen')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE p1_komponenten ADD COLUMN modell strukturmodell NOT NULL");
            DB::statement("ALTER TABLE p1_komponenten ADD COLUMN synonyme TEXT[]");
        }

        if (! Schema::hasTable('p1_kriterien')) {
            Schema::create('p1_kriterien', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('beschreibung');
                $table->text('begruendung')->nullable();
                $table->text('quellbezug')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE p1_kriterien ADD COLUMN kriterium_typ kriterium_typ NOT NULL");
        }

        if (! Schema::hasTable('p1_warnsignale')) {
            Schema::create('p1_warnsignale', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->smallInteger('lfd_nr');
                $table->text('warnsignal');
                $table->text('moegliche_auswirkung')->nullable();
                $table->text('handlungsempfehlung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p1_warnsignale');
        Schema::dropIfExists('p1_kriterien');
        Schema::dropIfExists('p1_komponenten');
        Schema::dropIfExists('p1_strukturmodell_wahl');
    }
};
