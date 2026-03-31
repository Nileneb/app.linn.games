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

        if (! Schema::hasTable('p3_disziplinen')) {
            Schema::create('p3_disziplinen', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('disziplin');
                $table->text('art')->nullable();
                $table->text('relevanz')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE p3_disziplinen ADD CONSTRAINT p3_disziplinen_art_check CHECK (art IN ('kerndisziplin', 'angrenzend'))");
        }

        if (! Schema::hasTable('p3_datenbankmatrix')) {
            Schema::create('p3_datenbankmatrix', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('datenbank');
                $table->text('disziplin')->nullable();
                $table->text('abdeckung')->nullable();
                $table->text('besonderheit')->nullable();
                $table->text('zugang')->nullable();
                $table->boolean('empfohlen')->nullable();
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE p3_datenbankmatrix ADD CONSTRAINT p3_datenbankmatrix_zugang_check CHECK (zugang IN ('frei', 'kostenpflichtig', 'institutionell'))");
        }

        if (! Schema::hasTable('p3_geografische_filter')) {
            Schema::create('p3_geografische_filter', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('region_land');
                $table->boolean('validierter_filter_vorhanden')->default(false);
                $table->text('filtername_quelle')->nullable();
                $table->decimal('sensitivitaet_prozent', 5, 2)->nullable();
                $table->text('hilfsstrategie')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p3_graue_literatur')) {
            Schema::create('p3_graue_literatur', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('projekt_id');
                $table->text('quelle');
                $table->text('typ')->nullable();
                $table->text('url')->nullable();
                $table->text('suchpfad')->nullable();
                $table->text('relevanz')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('p3_graue_literatur');
        Schema::dropIfExists('p3_geografische_filter');
        Schema::dropIfExists('p3_datenbankmatrix');
        Schema::dropIfExists('p3_disziplinen');
    }
};
