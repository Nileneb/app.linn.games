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

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->default('trial')->after('email');
            });
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

        // ─── P1 tables ─────────────────────────────────────
        if (! Schema::hasTable('p1_strukturmodell_wahl')) {
            Schema::create('p1_strukturmodell_wahl', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('modell');
                $table->boolean('gewaehlt')->default(false);
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p1_komponenten')) {
            Schema::create('p1_komponenten', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('modell');
                $table->text('komponente_kuerzel');
                $table->text('komponente_label');
                $table->text('synonyme')->nullable();
                $table->text('inhaltlicher_begriff_de')->nullable();
                $table->text('englische_entsprechung')->nullable();
                $table->text('mesh_term')->nullable();
                $table->text('thesaurus_term')->nullable();
                $table->text('anmerkungen')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p1_kriterien')) {
            Schema::create('p1_kriterien', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('kriterium_typ');
                $table->text('beschreibung');
                $table->text('begruendung')->nullable();
                $table->text('quellbezug')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p1_warnsignale')) {
            Schema::create('p1_warnsignale', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->smallInteger('lfd_nr');
                $table->text('warnsignal');
                $table->text('moegliche_auswirkung')->nullable();
                $table->text('handlungsempfehlung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        // ─── P2 tables ─────────────────────────────────────
        if (! Schema::hasTable('p2_review_typ_entscheidung')) {
            Schema::create('p2_review_typ_entscheidung', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('review_typ');
                $table->boolean('passt')->nullable();
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p2_cluster')) {
            Schema::create('p2_cluster', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('cluster_id');
                $table->text('cluster_label');
                $table->text('beschreibung')->nullable();
                $table->integer('treffer_schaetzung')->nullable();
                $table->text('relevanz')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p2_mapping_suchstring_komponenten')) {
            Schema::create('p2_mapping_suchstring_komponenten', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('komponente_label');
                $table->text('suchbegriffe')->nullable();
                $table->text('sprache')->nullable();
                $table->boolean('trunkierung_genutzt')->default(false);
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p2_trefferlisten')) {
            Schema::create('p2_trefferlisten', function (Blueprint $table) {
                $table->uuid('id')->primary();
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

        // ─── P3 tables ─────────────────────────────────────
        if (! Schema::hasTable('p3_disziplinen')) {
            Schema::create('p3_disziplinen', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('disziplin');
                $table->text('art')->nullable();
                $table->text('relevanz')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p3_datenbankmatrix')) {
            Schema::create('p3_datenbankmatrix', function (Blueprint $table) {
                $table->uuid('id')->primary();
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
        }

        if (! Schema::hasTable('p3_geografische_filter')) {
            Schema::create('p3_geografische_filter', function (Blueprint $table) {
                $table->uuid('id')->primary();
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
                $table->uuid('id')->primary();
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

        // ─── P4 tables ─────────────────────────────────────
        if (! Schema::hasTable('p4_suchstrings')) {
            Schema::create('p4_suchstrings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('datenbank');
                $table->text('suchstring');
                $table->text('feldeinschraenkung')->nullable();
                $table->text('gesetzte_filter')->nullable();
                $table->integer('treffer_anzahl')->nullable();
                $table->text('einschaetzung')->nullable();
                $table->text('anpassung')->nullable();
                $table->text('version')->default('v1.0');
                $table->date('suchdatum')->nullable();
                $table->timestamp('erstellt_am')->useCurrent();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p4_thesaurus_mapping')) {
            Schema::create('p4_thesaurus_mapping', function (Blueprint $table) {
                $table->uuid('id')->primary();
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
                $table->uuid('id')->primary();
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

        // ─── P5 tables (additional) ────────────────────────
        if (! Schema::hasTable('p5_prisma_zahlen')) {
            Schema::create('p5_prisma_zahlen', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->integer('identifiziert_gesamt')->nullable();
                $table->integer('davon_datenbank_treffer')->nullable();
                $table->integer('davon_graue_literatur')->nullable();
                $table->integer('nach_deduplizierung')->nullable();
                $table->integer('ausgeschlossen_l1')->nullable();
                $table->integer('volltext_geprueft')->nullable();
                $table->integer('ausgeschlossen_l2')->nullable();
                $table->integer('eingeschlossen_final')->nullable();
                $table->timestamp('aktualisiert_am')->useCurrent();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p5_screening_kriterien')) {
            Schema::create('p5_screening_kriterien', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('level');
                $table->string('kriterium_typ');
                $table->text('beschreibung');
                $table->text('beispiel')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p5_screening_entscheidungen')) {
            Schema::create('p5_screening_entscheidungen', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('treffer_id');
                $table->string('level');
                $table->string('entscheidung');
                $table->text('ausschlussgrund')->nullable();
                $table->text('reviewer')->nullable();
                $table->date('datum')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('treffer_id')->references('id')->on('p5_treffer')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p5_tool_entscheidung')) {
            Schema::create('p5_tool_entscheidung', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('tool');
                $table->boolean('gewaehlt')->default(false);
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        // ─── P6 tables ─────────────────────────────────────
        if (! Schema::hasTable('p6_qualitaetsbewertung')) {
            Schema::create('p6_qualitaetsbewertung', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('treffer_id');
                $table->string('studientyp');
                $table->string('rob_tool');
                $table->string('gesamturteil');
                $table->text('hauptproblem')->nullable();
                $table->boolean('im_review_behalten')->default(true);
                $table->text('anmerkung')->nullable();
                $table->text('bewertet_von')->nullable();
                $table->date('bewertet_am')->nullable();
                $table->foreign('treffer_id')->references('id')->on('p5_treffer')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p6_luckenanalyse')) {
            Schema::create('p6_luckenanalyse', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('fehlender_aspekt');
                $table->text('fehlender_studientyp')->nullable();
                $table->text('moegliche_konsequenz')->nullable();
                $table->text('empfehlung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        // ─── P7 tables ─────────────────────────────────────
        if (! Schema::hasTable('p7_synthese_methode')) {
            Schema::create('p7_synthese_methode', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->string('methode');
                $table->boolean('gewaehlt')->default(false);
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p7_datenextraktion')) {
            Schema::create('p7_datenextraktion', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('treffer_id');
                $table->text('land')->nullable();
                $table->text('stichprobe_kontext')->nullable();
                $table->text('phaenomen_intervention')->nullable();
                $table->text('outcome_ergebnis')->nullable();
                $table->text('hauptbefund')->nullable();
                $table->string('qualitaetsurteil')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('treffer_id')->references('id')->on('p5_treffer')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p7_muster_konsistenz')) {
            Schema::create('p7_muster_konsistenz', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('muster_befund');
                $table->text('unterstuetzende_quellen')->nullable();
                $table->text('widersprechende_quellen')->nullable();
                $table->text('moegliche_erklaerung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p7_grade_einschaetzung')) {
            Schema::create('p7_grade_einschaetzung', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('outcome');
                $table->integer('studienanzahl')->nullable();
                $table->string('rob_gesamt')->nullable();
                $table->text('inkonsistenz')->nullable();
                $table->text('indirektheit')->nullable();
                $table->text('impraezision')->nullable();
                $table->string('grade_urteil');
                $table->text('begruendung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        // ─── P8 tables ─────────────────────────────────────
        if (! Schema::hasTable('p8_suchprotokoll')) {
            Schema::create('p8_suchprotokoll', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('suchstring_id')->nullable();
                $table->text('datenbank');
                $table->date('suchdatum')->nullable();
                $table->text('db_version')->nullable();
                $table->text('suchstring_final');
                $table->text('gesetzte_filter')->nullable();
                $table->integer('treffer_gesamt')->nullable();
                $table->integer('treffer_eindeutig')->nullable();
                $table->foreign('suchstring_id')->references('id')->on('p4_suchstrings')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('p8_limitationen')) {
            Schema::create('p8_limitationen', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('limitationstyp');
                $table->text('beschreibung')->nullable();
                $table->text('auswirkung_auf_vollstaendigkeit')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p8_reproduzierbarkeitspruefung')) {
            Schema::create('p8_reproduzierbarkeitspruefung', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('pruefpunkt');
                $table->boolean('erfuellt')->nullable();
                $table->text('anmerkung')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('p8_update_plan')) {
            Schema::create('p8_update_plan', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('projekt_id');
                $table->text('update_typ')->nullable();
                $table->text('intervall')->nullable();
                $table->text('verantwortlich')->nullable();
                $table->text('tool')->nullable();
                $table->date('naechstes_update')->nullable();
                $table->foreign('projekt_id')->references('id')->on('projekte')->cascadeOnDelete();
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
                $table->text('secret')->nullable();
                $table->string('frontend_object')->nullable();
                $table->unique(['user_id', 'frontend_object']);
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
        Schema::dropIfExists('p8_update_plan');
        Schema::dropIfExists('p8_reproduzierbarkeitspruefung');
        Schema::dropIfExists('p8_limitationen');
        Schema::dropIfExists('p8_suchprotokoll');
        Schema::dropIfExists('p7_grade_einschaetzung');
        Schema::dropIfExists('p7_muster_konsistenz');
        Schema::dropIfExists('p7_datenextraktion');
        Schema::dropIfExists('p7_synthese_methode');
        Schema::dropIfExists('p6_luckenanalyse');
        Schema::dropIfExists('p6_qualitaetsbewertung');
        Schema::dropIfExists('p5_tool_entscheidung');
        Schema::dropIfExists('p5_screening_entscheidungen');
        Schema::dropIfExists('p5_screening_kriterien');
        Schema::dropIfExists('p5_prisma_zahlen');
        Schema::dropIfExists('p4_anpassungsprotokoll');
        Schema::dropIfExists('p4_thesaurus_mapping');
        Schema::dropIfExists('p4_suchstrings');
        Schema::dropIfExists('p3_graue_literatur');
        Schema::dropIfExists('p3_geografische_filter');
        Schema::dropIfExists('p3_datenbankmatrix');
        Schema::dropIfExists('p3_disziplinen');
        Schema::dropIfExists('p2_trefferlisten');
        Schema::dropIfExists('p2_mapping_suchstring_komponenten');
        Schema::dropIfExists('p2_cluster');
        Schema::dropIfExists('p2_review_typ_entscheidung');
        Schema::dropIfExists('p1_warnsignale');
        Schema::dropIfExists('p1_kriterien');
        Schema::dropIfExists('p1_komponenten');
        Schema::dropIfExists('p1_strukturmodell_wahl');
        Schema::dropIfExists('p5_treffer');
        Schema::dropIfExists('phasen');
        Schema::dropIfExists('projekte');
    }
};
