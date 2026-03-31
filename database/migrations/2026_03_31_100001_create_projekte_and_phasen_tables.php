<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projekte', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('titel');
            $table->text('forschungsfrage')->nullable();
            $table->text('verantwortlich')->nullable();
            $table->date('startdatum')->nullable();
            $table->text('notizen')->nullable();
            $table->timestampTz('letztes_update')->default(DB::raw('now()'));
            $table->timestampTz('erstellt_am')->default(DB::raw('now()'));
        });

        DB::statement("ALTER TABLE projekte ADD COLUMN review_typ review_typ");

        Schema::create('phasen', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('projekt_id');
            $table->smallInteger('phase_nr');
            $table->text('titel');
            $table->text('notizen')->nullable();
            $table->timestampTz('abgeschlossen_am')->nullable();

            $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            $table->unique(['projekt_id', 'phase_nr']);
        });

        DB::statement("ALTER TABLE phasen ADD COLUMN status phase_status DEFAULT 'offen'");
    }

    public function down(): void
    {
        Schema::dropIfExists('phasen');
        Schema::dropIfExists('projekte');
    }
};
