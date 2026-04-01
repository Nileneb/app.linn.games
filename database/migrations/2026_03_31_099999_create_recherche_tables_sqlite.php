<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite-compatible version of the Recherche core tables for testing.
 * Skipped on PostgreSQL (handled by the real migrations).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        if (! Schema::hasTable('projekte')) {
            Schema::create('projekte', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('titel');
                $table->text('forschungsfrage')->nullable();
                $table->string('review_typ')->nullable();
                $table->text('verantwortlich')->nullable();
                $table->date('startdatum')->nullable();
                $table->text('notizen')->nullable();
                $table->timestamp('letztes_update')->useCurrent();
                $table->timestamp('erstellt_am')->useCurrent();
            });
        }

        if (! Schema::hasTable('phasen')) {
            Schema::create('phasen', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->smallInteger('phase_nr');
                $table->text('titel');
                $table->string('status')->default('offen');
                $table->text('notizen')->nullable();
                $table->timestamp('abgeschlossen_am')->nullable();

                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
                $table->unique(['projekt_id', 'phase_nr']);
            });
        }

        if (! Schema::hasTable('p5_treffer')) {
            Schema::create('p5_treffer', function (Blueprint $table) {
                $table->uuid('id')->primary();
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
                $table->timestamp('erstellt_am')->useCurrent();

                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
                $table->unique(['projekt_id', 'record_id']);
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 20);
                $table->text('content');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('webhooks')) {
            Schema::create('webhooks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('name');
                $table->text('slug')->unique();
                $table->text('url');
                $table->timestamp('created_at')->useCurrent();
            });

            if (Schema::hasTable('chat_messages')) {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->uuid('webhook_id')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('p5_treffer');
        Schema::dropIfExists('phasen');
        Schema::dropIfExists('projekte');
    }
};
