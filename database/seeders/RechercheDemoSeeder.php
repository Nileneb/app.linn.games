<?php

namespace Database\Seeders;

use App\Models\Recherche\P1Komponente;
use App\Models\Recherche\P1Kriterium;
use App\Models\Recherche\P1Strukturmodellwahl;
use App\Models\Recherche\P2Trefferliste;
use App\Models\Recherche\P4Suchstring;
use App\Models\Recherche\P5PrismaZahlen;
use App\Models\Recherche\P5ScreeningEntscheidung;
use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\P6Qualitaetsbewertung;
use App\Models\Recherche\P7Datenextraktion;
use App\Models\Recherche\Phase;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RechercheDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->seedWebhooks($user);

        $projekt = $this->seedProjekt($user);
        $this->seedPhasen($projekt);
        $this->seedP1($projekt);
        $this->seedP2($projekt);
        $this->seedP4($projekt);
        $treffer = $this->seedP5($projekt);
        $this->seedP6($treffer);
        $this->seedP7($treffer);
    }

    private function seedWebhooks(User $user): void
    {
        \App\Models\Webhook::firstOrCreate(
            ['user_id' => $user->id, 'frontend_object' => 'dashboard_chat'],
            [
                'name'       => 'Dashboard Chat',
                'slug'       => 'dashboard-chat-' . substr(md5($user->id . 'dashboard_chat'), 0, 8),
                'url'        => 'https://app.langdock.com/api/hooks/workflows/30781e00-a6df-4c6f-a2ad-fa8cf9b9826b',
                'secret'     => 'Test123',
                'created_at' => now(),
            ]
        );
        // Aufruf: curl -X POST "https://app.langdock.com/api/hooks/workflows/30781e00-a6df-4c6f-a2ad-fa8cf9b9826b?secret=Test123" \
        //   -H 'Content-Type: application/json' \
        //   -d '{"prompt": "YOUR PROMPT HERE"}'}

        \App\Models\Webhook::firstOrCreate(
            ['user_id' => $user->id, 'frontend_object' => 'recherche_start'],
            [
                'name'       => 'Recherche starten',
                'slug'       => 'recherche-start-' . substr(md5($user->id . 'recherche_start'), 0, 8),
                'url'        => 'https://app.langdock.com/api/hooks/workflows/demo-placeholder',
                'created_at' => now(),
            ]
        );
    }

    private function seedProjekt(User $user): Projekt
    {
        return Projekt::create([
            'user_id'          => $user->id,
            'titel'            => 'Wirksamkeit von KI-gestützter Bildanalyse in der Radiologie',
            'forschungsfrage'  => 'Welche Evidenz besteht für den Einsatz von KI-Systemen zur Unterstützung radiologischer Bilddiagnosen im klinischen Alltag hinsichtlich Genauigkeit, Effizienz und Patientensicherheit im Vergleich zu rein menschlicher Beurteilung?',
            'review_typ'       => 'systematic_review',
            'verantwortlich'   => 'Test User',
            'startdatum'       => '2026-01-15',
            'notizen'          => 'Demo-Datensatz für Entwicklungsumgebung. Basiert auf realistischen Szenarien aus der Literatur.',
        ]);
    }

    private function seedPhasen(Projekt $projekt): void
    {
        $phasen = [
            [1, 'Fragestrukturierung & Einschlusskriterien', 'abgeschlossen'],
            [2, 'Konzeptualisierung der Suchstrategie',     'abgeschlossen'],
            [3, 'Datenbankauswahl & Filter',                'abgeschlossen'],
            [4, 'Suchstring-Entwicklung',                   'abgeschlossen'],
            [5, 'Screening & Trefferauswertung',            'in_bearbeitung'],
            [6, 'Qualitätsbewertung',                       'in_bearbeitung'],
            [7, 'Datenextraktion',                          'offen'],
            [8, 'Dokumentation & Reproduzierbarkeit',       'offen'],
        ];

        foreach ($phasen as [$nr, $titel, $status]) {
            DB::statement(
                "INSERT INTO phasen (id, projekt_id, phase_nr, titel, status)
                 VALUES (uuid_generate_v4(), ?, ?, ?, ?::phase_status)",
                [$projekt->id, $nr, $titel, $status]
            );
        }
    }

    private function seedP1(Projekt $projekt): void
    {
        // Strukturmodellwahl
        foreach (['PICO', 'SPIDER', 'PICOS'] as $modell) {
            P1Strukturmodellwahl::create([
                'projekt_id'  => $projekt->id,
                'modell'      => $modell,
                'gewaehlt'    => $modell === 'PICO',
                'begruendung' => $modell === 'PICO'
                    ? 'PICO ist das Standardmodell für klinische Interventionsstudien und eignet sich optimal für den Vergleich KI vs. menschliche Diagnostik.'
                    : null,
            ]);
        }

        // PICO-Komponenten
        $komponenten = [
            ['P', 'Population',      'Erwachsene Patienten',    ['Patienten', 'Kranke', 'Erwachsene'],        'Patienten', 'patients',     'Patients [MeSH]',           'Patient'],
            ['I', 'Intervention',    'KI-Bildanalyse',          ['Künstliche Intelligenz', 'Deep Learning'],  'KI-Diagnose', 'AI diagnosis', 'Artificial Intelligence [MeSH]', 'Artificial intelligence'],
            ['C', 'Comparison',      'Menschliche Radiologie',  ['Radiologe', 'Facharzt'],                    'Radiologische Beurteilung', 'radiology', 'Radiology [MeSH]', 'Radioloog'],
            ['O', 'Outcome',         'Diagnosegenauigkeit',     ['Sensitivität', 'Spezifität', 'AUC'],         'Diagnosegüte', 'diagnostic accuracy', 'Sensitivity and Specificity [MeSH]', 'Diagnostische Genauigkeit'],
        ];

        foreach ($komponenten as [$kuerzel, $label, $inhalt, $synonyme, $de, $en, $mesh, $thesaurus]) {
            P1Komponente::create([
                'projekt_id'              => $projekt->id,
                'modell'                  => 'PICO',
                'komponente_kuerzel'      => $kuerzel,
                'komponente_label'        => $label,
                'synonyme'                => $synonyme,
                'inhaltlicher_begriff_de' => $inhalt,
                'englische_entsprechung'  => $en,
                'mesh_term'               => $mesh,
                'thesaurus_term'          => $thesaurus,
                'anmerkungen'             => null,
            ]);
        }

        // Einschluss-/Ausschlusskriterien
        $kriterien = [
            ['einschluss', 'Peer-reviewed Studien mit klinischen Validierungsdaten', 'Nur Evidenz aus kontrollierten Settings'],
            ['einschluss', 'Studien mit quantitativen Gütekriterien (Sensitivität, Spezifität, AUC)', 'Vergleichbarkeit der Ergebnisse'],
            ['einschluss', 'Publikationen 2018–2026 (KI-Reife in der Radiologie)', 'Technologisch relevanter Zeitraum'],
            ['ausschluss', 'Konferenz-Abstracts ohne Volltext', 'Unzureichende Methodentransparenz'],
            ['ausschluss', 'Tiermedizinische Studien', 'Nicht auf humane Klinik übertragbar'],
            ['ausschluss', 'Entwicklungsstudien ohne klinische Validierung', 'Fehlende externe Validität'],
        ];

        foreach ($kriterien as [$typ, $beschreibung, $begruendung]) {
            P1Kriterium::create([
                'projekt_id'   => $projekt->id,
                'kriterium_typ' => $typ,
                'beschreibung' => $beschreibung,
                'begruendung'  => $begruendung,
            ]);
        }
    }

    private function seedP2(Projekt $projekt): void
    {
        $trefferlisten = [
            ['PubMed',     '(("Artificial Intelligence"[MeSH] OR "Deep Learning"[MeSH]) AND ("Radiology"[MeSH] OR "Diagnostic Imaging"[MeSH]) AND ("diagnosis"[MeSH]))', 1842, 'gut', false],
            ['Embase',     '(artificial intelligence OR deep learning) AND (radiology OR medical imaging) AND (diagnosis OR diagnostic accuracy)', 2104, 'gut', true],
            ['Cochrane',   '"artificial intelligence" AND "radiology" AND "diagnosis"', 87, 'mittel', false],
            ['IEEE Xplore', '("AI" OR "deep learning") AND ("medical imaging" OR "radiology") AND "diagnosis"', 563, 'bedingt', true],
            ['Web of Science', 'TS=(("artificial intelligence" OR "machine learning") AND ("radiolog*" OR "medical imaging") AND "diagnos*")', 1290, 'gut', false],
        ];

        foreach ($trefferlisten as [$db, $string, $treffer, $einschaetzung, $anpassung]) {
            P2Trefferliste::create([
                'projekt_id'          => $projekt->id,
                'datenbank'           => $db,
                'suchstring'          => $string,
                'treffer_gesamt'      => $treffer,
                'einschaetzung'       => $einschaetzung,
                'anpassung_notwendig' => $anpassung,
                'suchdatum'           => '2026-02-10',
            ]);
        }
    }

    private function seedP4(Projekt $projekt): void
    {
        $suchstrings = [
            [
                'datenbank'         => 'PubMed',
                'suchstring'        => '(("Artificial Intelligence"[MeSH] OR "Deep Learning"[MeSH] OR "Machine Learning"[MeSH]) AND ("Radiology"[MeSH] OR "Diagnostic Imaging"[MeSH] OR "Radiography"[MeSH]) AND ("Sensitivity and Specificity"[MeSH] OR "ROC Curve"[MeSH] OR "diagnostic accuracy"))',
                'feldeinschraenkung' => 'Title/Abstract + MeSH Terms',
                'gesetzte_filter'   => ['Sprache: Englisch, Deutsch', 'Publikationstyp: Clinical Trial, Systematic Review', 'Erscheinungsjahr: 2018–2026'],
                'treffer_anzahl'    => 1842,
                'einschaetzung'     => 'Suchstrategie liefert gut handhabbare Treffermenge mit hoher Spezifität durch MeSH-Terme.',
                'version'           => 3,
                'suchdatum'         => '2026-02-15',
            ],
            [
                'datenbank'         => 'Embase',
                'suchstring'        => '(\'artificial intelligence\'/exp OR \'deep learning\'/exp OR \'machine learning\'/exp) AND (\'radiology\'/exp OR \'medical image\'/exp) AND (\'sensitivity and specificity\'/exp OR \'diagnostic accuracy\') AND [2018-2026]/py',
                'feldeinschraenkung' => 'Emtree + Freitext',
                'gesetzte_filter'   => ['Sprache: Englisch', 'Publikationstyp: Article, Review'],
                'treffer_anzahl'    => 2104,
                'einschaetzung'     => 'Hohe Ausbeute, Überlappung mit PubMed erwartet (~40%); Deduplizierung notwendig.',
                'version'           => 2,
                'suchdatum'         => '2026-02-15',
            ],
        ];

        foreach ($suchstrings as $data) {
            P4Suchstring::create(array_merge($data, ['projekt_id' => $projekt->id]));
        }
    }

    private function seedP5(Projekt $projekt): array
    {
        $papers = [
            // Eingeschlossen - L1 + L2
            ['Deep learning for chest radiograph diagnosis',        'Rajpurkar P, Irvin J, Ball RL et al.',            2018, 'PLOS Medicine',              '10.1371/journal.pmed.1002686',  'eingeschlossen', 'eingeschlossen', false],
            ['AI versus radiologists in breast cancer screening',   'McKinney SM, Sieniek M, Godbole V et al.',        2020, 'Nature',                      '10.1038/s41586-019-1799-6',     'eingeschlossen', 'eingeschlossen', false],
            ['Clinically applicable AI for prostate cancer',        'Bulten W, Pinckaers H, van Boven H et al.',       2022, 'Nature Medicine',             '10.1038/s41591-021-01573-y',    'eingeschlossen', 'eingeschlossen', false],
            ['Deep learning for pulmonary embolism detection',      'Yan Q, Wang B, Gong D et al.',                    2021, 'Radiology',                   '10.1148/radiol.2021203511',     'eingeschlossen', 'eingeschlossen', false],
            ['Automated detection of diabetic retinopathy',         'Gulshan V, Peng L, Coram M et al.',               2019, 'JAMA',                        '10.1001/jama.2016.17216',       'eingeschlossen', 'eingeschlossen', false],
            ['Performance of AI in dermatology diagnosis',          'Esteva A, Kuprel B, Novoa RA et al.',             2020, 'Nature',                      '10.1038/nature21056',           'eingeschlossen', 'eingeschlossen', false],
            ['Machine learning for ECG classification',             'Hannun AY, Rajpurkar P, Haghpanahi M et al.',     2019, 'Nature Medicine',             '10.1038/s41591-018-0268-3',     'eingeschlossen', 'eingeschlossen', false],
            ['AI-assisted colonoscopy polyp detection',             'Wang P, Berzin TM, Glissen Brown JR et al.',      2021, 'Gut',                         '10.1136/gutjnl-2019-319004',    'eingeschlossen', 'eingeschlossen', false],

            // L2 ausgeschlossen
            ['Deep learning in radiology: an overview',             'Shen D, Wu G, Suk HI',                            2019, 'Annual Review of Biomedical Engineering', '10.1146/annurev-bioeng-060418',   'eingeschlossen', 'ausgeschlossen', false],
            ['AI in medical imaging narrative review',              'Obermeyer Z, Emanuel EJ',                         2020, 'New England Journal of Medicine', '10.1056/NEJMp1702513',          'eingeschlossen', 'ausgeschlossen', false],

            // L1 ausgeschlossen
            ['Machine learning in veterinary radiology',            'Lee K, Park J',                                   2020, 'Veterinary Radiology',          '10.1111/vru.12834',             'ausgeschlossen', null, false],
            ['AI model development without clinical test',          'Zhang X, Chen R',                                 2021, 'arXiv Preprint',                null,                            'ausgeschlossen', null, false],
            ['Deep learning for satellite imaging classification',  'Torres M, Santos F, Lima A',                      2022, 'Remote Sensing',                '10.3390/rs14010123',            'ausgeschlossen', null, false],

            // Duplikat
            ['Deep learning for chest X-ray diagnosis (duplicate)', 'Rajpurkar P et al.',                             2018, 'PLOS Medicine',                 '10.1371/journal.pmed.1002686',  'ausgeschlossen', null, true],

            // Unklar L1
            ['Artificial intelligence in emergency radiology',      'Jalal S, Lloyd ME, Delorme S et al.',             2021, 'Canadian Association of Radiology Journal', '10.1177/0846537121992651', 'unklar', 'eingeschlossen', false],
        ];

        $treffer = [];
        $duplikatOriginalId = null;

        foreach ($papers as $i => [$titel, $autoren, $jahr, $journal, $doi, $l1, $l2, $isDuplikat]) {
            $t = P5Treffer::create([
                'projekt_id'       => $projekt->id,
                'record_id'        => sprintf('REC-%04d', $i + 1),
                'titel'            => $titel,
                'autoren'          => $autoren,
                'jahr'             => $jahr,
                'journal'          => $journal,
                'doi'              => $doi,
                'datenbank_quelle' => ['PubMed', 'Embase', 'Cochrane'][array_rand(['PubMed', 'Embase', 'Cochrane'])],
                'ist_duplikat'     => $isDuplikat,
                'duplikat_von'     => $isDuplikat ? $duplikatOriginalId : null,
            ]);

            // Merke das erste Paper als potentielles Duplikat-Original
            if ($i === 0) {
                $duplikatOriginalId = $t->id;
            }

            // L1 Screening
            P5ScreeningEntscheidung::create([
                'treffer_id'      => $t->id,
                'level'           => 'L1_titel_abstract',
                'entscheidung'    => $l1,
                'ausschlussgrund' => $l1 === 'ausgeschlossen' ? $this->ausschlussgrund($titel) : null,
                'reviewer'        => 'Test User',
                'datum'           => '2026-02-25',
            ]);

            // L2 Screening (nur wenn L1 eingeschlossen oder unklar)
            if ($l2 !== null) {
                P5ScreeningEntscheidung::create([
                    'treffer_id'      => $t->id,
                    'level'           => 'L2_volltext',
                    'entscheidung'    => $l2,
                    'ausschlussgrund' => $l2 === 'ausgeschlossen' ? 'Kein kontrollierter Vergleich mit menschlicher Beurteilung vorhanden (Narrative Review)' : null,
                    'reviewer'        => 'Test User',
                    'datum'           => '2026-03-05',
                ]);
            }

            $treffer[] = ['model' => $t, 'l2' => $l2];
        }

        // PRISMA-Zahlen
        P5PrismaZahlen::create([
            'projekt_id'              => $projekt->id,
            'identifiziert_gesamt'    => 5886,
            'davon_datenbank_treffer' => 5800,
            'davon_graue_literatur'   => 86,
            'nach_deduplizierung'     => 3204,
            'ausgeschlossen_l1'       => 3190,
            'volltext_geprueft'       => 14,
            'ausgeschlossen_l2'       => 4,
            'eingeschlossen_final'    => 9,
        ]);

        return $treffer;
    }

    private function seedP6(array $treffer): void
    {
        $robData = [
            ['RoB2',     'RCT',               'niedrig',  null, true],
            ['RoB2',     'RCT',               'niedrig',  null, true],
            ['ROBINS-I', 'nicht_randomisiert', 'moderat',  'Selektion der Trainingsdaten möglicherweise nicht repräsentativ', true],
            ['RoB2',     'RCT',               'niedrig',  null, true],
            ['RoB2',     'RCT',               'niedrig',  null, true],
            ['ROBINS-I', 'qualitativ',         'moderat',  'Eingeschränkte externe Validität durch Monozentrischen Ansatz', true],
            ['RoB2',     'RCT',               'niedrig',  null, true],
            ['RoB2',     'RCT',               'niedrig',  null, true],
            // L2 ausgeschlossene
            ['narrativ', 'systematic_review',  'nicht_bewertet', 'Narrative Review — kein primäres Studienmaterial', false],
            ['narrativ', 'systematic_review',  'nicht_bewertet', 'Keine quantitativen Originaldaten', false],
        ];

        $idx = 0;
        foreach ($treffer as $item) {
            if ($item['l2'] !== null && isset($robData[$idx])) {
                [$tool, $typ, $urteil, $problem, $behalten] = $robData[$idx];
                P6Qualitaetsbewertung::create([
                    'treffer_id'       => $item['model']->id,
                    'studientyp'       => $typ,
                    'rob_tool'         => $tool,
                    'gesamturteil'     => $urteil,
                    'hauptproblem'     => $problem,
                    'im_review_behalten' => $behalten,
                    'bewertet_von'     => 'Test User',
                    'bewertet_am'      => '2026-03-12',
                ]);
                $idx++;
            }
        }
    }

    private function seedP7(array $treffer): void
    {
        $extraktionen = [
            ['USA',         'N=420 Patienten, Level-1-Trauma-Zentrum',        'KI-Chest-AI v2.3',   'AUC 0.94, Sensitivität 87%, Spezifität 93%',  'KI-System signifikant überlegen bei Pneumonie-Detektion (p<0.001)'],
            ['UK',          'N=28.971 Mammographien, nationales Screening',   'DeepMind Mammography','AUC 0.889 (KI) vs. 0.814 (Radiologe)',        'KI reduziert False-Negative-Rate um 9,4%; False-Positive minimal erhöht'],
            ['NL',          'N=1.243 Prostatektomie-Schnitte',                'Paige Prostate AI',   'AUC 0.99 Gleason-Grading',                    'Pathologie-AI dem Facharzt ebenbürtig, signifikant besser als Assistenzarzt'],
            ['CN',          'N=8.266 CT-Thorax, Notaufnahme',                 'PE-Net',              'Sensitivität 92,7%, Spezifität 95,5%',        'Turnaroundzeit von 60 auf 18 Minuten reduziert'],
            ['USA/IN',      'N=128.175 Fundusbilder, Diabetiker-Screening',   'IDx-DR',             'Sensitivität 90,3%, Spezifität 98,5%',        'FDA-zugelassenes System; untersucherunabhängige Diagnose möglich'],
            ['USA',         'N=2.032 Hautläsionen, Dermatologen vs. CNN',     'Google Inception v4', 'AUC 0.96 (KI) vs. 0.91 (Dermatologen)',      'KI-System auf dem Niveau zertifizierter Dermatologen'],
            ['USA',         'N=91.232 EKG-Aufzeichnungen',                    'Stanford ECG-AI',     '12 Arrhythmietypen; F1 0.837',                'Übertrifft Kardiologen bei 10/12 Arrhythmietypen; 24/7-Einsatz möglich'],
            ['CN',          'N=1.058 Koloskopien, RCT',                       'CADe Kolonoskopie',   'Adenomdetektionsrate 29,1% vs. 20,3% (Kontrolle)','Signifikante Verbesserung der Adenomdetektionsrate (p<0.001)'],
        ];

        $idx = 0;
        foreach ($treffer as $item) {
            if ($item['l2'] === 'eingeschlossen' && isset($extraktionen[$idx])) {
                [$land, $stichprobe, $intervention, $outcome, $befund] = $extraktionen[$idx];
                P7Datenextraktion::create([
                    'treffer_id'             => $item['model']->id,
                    'land'                   => $land,
                    'stichprobe_kontext'     => $stichprobe,
                    'phaenomen_intervention' => $intervention,
                    'outcome_ergebnis'       => $outcome,
                    'hauptbefund'            => $befund,
                    'qualitaetsurteil'       => 'hoch',
                ]);
                $idx++;
            }
        }
    }

    private function ausschlussgrund(string $titel): string
    {
        if (str_contains($titel, 'veterinary') || str_contains($titel, 'Veterinary')) {
            return 'Nicht-humane Studie (Tiermedizin)';
        }
        if (str_contains($titel, 'satellite') || str_contains($titel, 'Remote Sensing')) {
            return 'Kein medizinischer Kontext';
        }
        if (str_contains($titel, 'without clinical')) {
            return 'Fehlende klinische Validierung';
        }
        return 'Außerhalb des definierten Themenbereichs';
    }
}
