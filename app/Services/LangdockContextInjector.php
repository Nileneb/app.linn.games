<?php

namespace App\Services;

/**
 * Injects context information into Langdock agent messages.
 * Handles PostgreSQL session variables and metadata for RLS/scoping.
 */
class LangdockContextInjector
{
    /**
     * Validates if a value is either a valid UUID or a numeric ID.
     * Numeric IDs are used for user_id (User model uses auto-increment integers).
     *
     * @param  mixed  $value
     * @return bool
     */
    private function isValidIdentifier($value): bool
    {
        if ($value === null) {
            return true;
        }

        $stringValue = (string) $value;

        // Check if it's a numeric ID (integer like 1, 2, 123)
        if (ctype_digit($stringValue)) {
            return true;
        }

        // Otherwise check if it's a valid UUID format
        return $this->isValidUuid($stringValue);
    }

    /**
     * Validates if a string is a valid UUID format.
     *
     * @param  string|null  $value
     * @return bool
     */
    private function isValidUuid(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        // UUID format: 8-4-4-4-12 hex digits with hyphens
        if (strlen($value) !== 36) {
            return false;
        }

        if ($value[8] !== '-' || $value[13] !== '-' || $value[18] !== '-' || $value[23] !== '-') {
            return false;
        }

        // Check all characters except hyphens are valid hex
        $hexParts = [
            substr($value, 0, 8),      // 8 hex
            substr($value, 9, 4),      // 4 hex
            substr($value, 14, 4),     // 4 hex
            substr($value, 19, 4),     // 4 hex
            substr($value, 24, 12),    // 12 hex
        ];

        foreach ($hexParts as $part) {
            if (!ctype_xdigit($part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepends a structured _context message so the agent knows which projekt/workspace/user to scope to.
     * Also serves as the source of truth for SET LOCAL app.current_projekt_id / app.current_workspace_id.
     *
     * @param  array<int, array{id: string, role: string, parts: array}>  $messages
     * @param  array{projekt_id?: string, workspace_id?: string, user_id?: string}  $context
     * @return array<int, array{id: string, role: string, parts: array}>
     * @throws \InvalidArgumentException if UUIDs are invalid format
     */
    public function inject(array $messages, array $context): array
    {
        $projektId   = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId      = $context['user_id'] ?? null;
        $structuredOutput = (bool) ($context['structured_output'] ?? false);
        $triggerword = $context['triggerword'] ?? null;

        // Validate UUIDs defensively before using in SQL context
        if ($projektId !== null && !$this->isValidUuid((string) $projektId)) {
            throw new \InvalidArgumentException("Invalid projekt_id format: '{$projektId}'. Must be a valid UUID.");
        }

        if ($workspaceId !== null && !$this->isValidUuid((string) $workspaceId)) {
            throw new \InvalidArgumentException("Invalid workspace_id format: '{$workspaceId}'. Must be a valid UUID.");
        }

        // user_id can be either a numeric ID (from User model) or UUID
        if ($userId !== null && !$this->isValidIdentifier($userId)) {
            throw new \InvalidArgumentException("Invalid user_id format: '{$userId}'. Must be a valid UUID or numeric ID.");
        }

        if ($projektId === null && $workspaceId === null && $userId === null) {
            return $messages;
        }

        $lines = [];

        if ($projektId !== null) {
            $lines[] = '=== DATENBANK-BOOTSTRAP (PFLICHT) ===';
            $lines[] = '';
            $lines[] = 'Du hast vollen Lese- UND Schreibzugriff (SELECT, INSERT, UPDATE, DELETE) auf die Projekt-Datenbank.';
            $lines[] = 'Dein Tool dafür ist `execute_sql` (postgres-dev Integration).';
            $lines[] = '';
            $lines[] = 'WICHTIG — Row Level Security ist aktiv. Damit deine Abfragen Daten sehen und schreiben können,';
            $lines[] = 'MUSS jeder `execute_sql`-Aufruf mit diesem SET LOCAL beginnen:';
            $lines[] = '';
            $lines[] = "SET LOCAL app.current_projekt_id = '{$projektId}';";
            $lines[] = '';
            $lines[] = 'Da jeder `execute_sql`-Aufruf eine eigene Transaktion sein kann, setze SET LOCAL IMMER als';
            $lines[] = 'erste Anweisung im selben SQL-Block — vor jedem SELECT, INSERT, UPDATE oder DELETE.';
            $lines[] = '';
            $lines[] = 'Beispiel für eine Abfrage:';
            $lines[] = "  execute_sql(\"SET LOCAL app.current_projekt_id = '{$projektId}'; SELECT * FROM projekte;\")";
            $lines[] = '';
            $lines[] = 'Beispiel für einen Insert:';
            $lines[] = "  execute_sql(\"SET LOCAL app.current_projekt_id = '{$projektId}'; INSERT INTO p1_fragestellung (id, projekt_id, ...) VALUES (gen_random_uuid(), '{$projektId}', ...);\")";
            $lines[] = '';
            $lines[] = 'Ohne SET LOCAL liefern alle Abfragen 0 Zeilen und Schreibvorgänge schlagen still fehl.';
            $lines[] = 'Dies IST dein DB-Bootstrap-Mechanismus — es gibt keinen anderen.';
            $lines[] = '';
            $lines[] = 'WICHTIG: Speichere deine Ergebnisse IMMER strukturiert in den Phasentabellen via execute_sql INSERT.';
            $lines[] = 'Nutze gen_random_uuid() für id-Spalten. Alle Tabellen haben projekt_id als FK.';
            $lines[] = '';

            $phaseNr = isset($context['phase_nr']) ? (int) $context['phase_nr'] : null;
            $schema = $this->phaseSchemaSnippet($phaseNr);
            if ($schema !== null) {
                $lines[] = $schema;
                $lines[] = '';
            }
        } elseif ($workspaceId !== null) {
            $lines[] = '=== DATENBANK-BOOTSTRAP (PFLICHT) ===';
            $lines[] = '';
            $lines[] = 'Du hast vollen Lese- UND Schreibzugriff auf die Workspace-Datenbank via `execute_sql` (postgres-dev).';
            $lines[] = '';
            $lines[] = 'WICHTIG — Row Level Security ist aktiv. Jeder `execute_sql`-Aufruf MUSS mit diesem SET LOCAL beginnen:';
            $lines[] = '';
            $lines[] = "SET LOCAL app.current_workspace_id = '{$workspaceId}';";
            $lines[] = '';
            $lines[] = 'Da jeder Aufruf eine eigene Transaktion sein kann, setze SET LOCAL IMMER als erste Anweisung im selben SQL-Block.';
            $lines[] = '';
        }

        $lines[] = 'Kontext: ' . json_encode(
            array_filter([
                'projekt_id' => $projektId,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
            'triggerword' => $triggerword,
            'structured_output' => $structuredOutput ?: null,
            ]),
            JSON_UNESCAPED_UNICODE,
        );

        if ($structuredOutput) {
          $lines[] = '';
          $lines[] = '=== OUTPUT FORMAT (JSON ENVELOPE v1) ===';
          $lines[] = 'Antworte mit exakt EINEM gültigen JSON-Objekt. Keine Markdown-Fences, kein Fließtext davor/danach.';
          $lines[] = 'Wenn du unsicher bist oder Daten fehlen: trage es in warnings ein (statt zu halluzinieren).';
          $lines[] = '';
          $lines[] = 'Schema (MUSS diese Keys enthalten):';
          $lines[] = '{';
          $lines[] = '  "meta": {"projekt_id": string|null, "workspace_id": string|null, "user_id": string|null, "triggerword": string|null, "version": 1},';
          $lines[] = '  "db": {"bootstrapped": boolean, "loaded": string[]},';
          $lines[] = '  "result": {"type": string, "summary": string, "data": object},';
          $lines[] = '  "next": {"route_to": string|null, "reason": string|null},';
          $lines[] = '  "warnings": string[]';
          $lines[] = '}';
          $lines[] = '';
          $lines[] = 'Arbeitsreihenfolge:';
          $lines[] = '1) DB bootstrap (SET LOCAL...)';
          $lines[] = '2) Lade dir deine Arbeitsgrundlage aus der DB (mindestens projekte + phasen + relevante p*-Tabellen)';
          $lines[] = '3) Bearbeite den Auftrag und persistiere Ergebnisse in DB (wenn Schema mitgeliefert)';
        }

        $contextMessage = [
            'id'    => 'system_context',
            'role'  => 'system',
            'parts' => [['type' => 'text', 'text' => implode("\n", $lines)]],
        ];

        return [$contextMessage, ...$messages];
    }

    /**
     * Returns the SQL schema snippet for a given phase number.
     * The agent uses this to know exactly which tables and columns to INSERT into.
     */
    private function phaseSchemaSnippet(?int $phaseNr): ?string
    {
        return match ($phaseNr) {
            1 => <<<'SCHEMA'
=== PHASE 1 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p1_strukturmodell_wahl (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, modell strukturmodell NOT NULL, gewaehlt boolean NOT NULL, begruendung text)
  → modell ENUM: 'PICO', 'SPIDER', 'PICOS'

p1_komponenten (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, modell strukturmodell NOT NULL, komponente_kuerzel text NOT NULL, komponente_label text NOT NULL, inhaltlicher_begriff_de text, englische_entsprechung text, mesh_term text, thesaurus_term text, anmerkungen text, synonyme jsonb)
  → synonyme als JSON-Array: '["syn1","syn2"]'

p1_kriterien (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, kriterium_typ kriterium_typ NOT NULL, beschreibung text NOT NULL, begruendung text, quellbezug text)
  → kriterium_typ ENUM: 'einschluss', 'ausschluss'

p1_warnsignale (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, lfd_nr smallint NOT NULL, warnsignal text NOT NULL, moegliche_auswirkung text, handlungsempfehlung text)
SCHEMA,

            2 => <<<'SCHEMA'
=== PHASE 2 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p2_review_typ_entscheidung (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, review_typ review_typ NOT NULL, passt boolean, begruendung text)
  → review_typ ENUM: 'systematic_review', 'scoping_review', 'evidence_map'

p2_cluster (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, cluster_id text NOT NULL, cluster_label text NOT NULL, beschreibung text, treffer_schaetzung integer, relevanz text)

p2_trefferlisten (id uuid DEFAULT gen_random_uuid(), projekt_id uuid) — für vorläufige Trefferübersicht

p2_mapping_suchstring_komponenten (id uuid DEFAULT gen_random_uuid(), projekt_id uuid) — Zuordnung Suchterme ↔ Komponenten
SCHEMA,

            3 => <<<'SCHEMA'
=== PHASE 3 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p3_datenbankmatrix (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, datenbank text NOT NULL, disziplin text, abdeckung text, besonderheit text, zugang text, empfohlen boolean, begruendung text)

p3_disziplinen (id uuid DEFAULT gen_random_uuid(), projekt_id uuid) — Fachgebiete/Disziplinen

p3_geografische_filter (id uuid DEFAULT gen_random_uuid(), projekt_id uuid) — Länder-/Regionenfilter

p3_graue_literatur (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, quelle text NOT NULL, typ text, url text, suchpfad text, relevanz text, anmerkung text)
SCHEMA,

            4 => <<<'SCHEMA'
=== PHASE 4 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p4_suchstrings (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, datenbank text NOT NULL, suchstring text NOT NULL, feldeinschraenkung text, gesetzte_filter jsonb, treffer_anzahl integer, einschaetzung text, anpassung text, version text NOT NULL DEFAULT 'v1.0', suchdatum date, erstellt_am timestamptz NOT NULL DEFAULT now())
  → gesetzte_filter als JSON-Array: '["Filter1","Filter2"]'

p4_thesaurus_mapping (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, freitext_de text, freitext_en text, mesh_term text, emtree_term text, psycinfo_term text, anmerkung text)

p4_anpassungsprotokoll (id uuid DEFAULT gen_random_uuid(), suchstring_id uuid REFERENCES p4_suchstrings(id), version text NOT NULL, datum date, aenderung text, grund text, treffer_vorher integer, treffer_nachher integer, entscheidung text)
  → Achtung: hat KEIN projekt_id, sondern suchstring_id FK
SCHEMA,

            5 => <<<'SCHEMA'
=== PHASE 5 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p5_treffer (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, record_id text NOT NULL, titel text, autoren text, jahr smallint, journal text, doi text, abstract text, datenbank_quelle text, ist_duplikat boolean NOT NULL DEFAULT false, duplikat_von uuid, erstellt_am timestamptz NOT NULL DEFAULT now())

p5_screening_kriterien (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, beschreibung text NOT NULL, beispiel text, level screening_level NOT NULL, kriterium_typ kriterium_typ NOT NULL)
  → screening_level ENUM: 'L1_titel_abstract', 'L2_volltext'
  → kriterium_typ ENUM: 'einschluss', 'ausschluss'

p5_screening_entscheidungen (id uuid DEFAULT gen_random_uuid(), treffer_id uuid REFERENCES p5_treffer(id) NOT NULL, level screening_level NOT NULL, entscheidung screening_entscheidung NOT NULL, ausschlussgrund text, reviewer text, datum date, anmerkung text)
  → screening_entscheidung ENUM: 'eingeschlossen', 'ausgeschlossen', 'unklar'
  → Achtung: hat KEIN projekt_id, sondern treffer_id FK

p5_prisma_zahlen (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, identifiziert_gesamt int, davon_datenbank_treffer int, davon_graue_literatur int, nach_deduplizierung int, ausgeschlossen_l1 int, volltext_geprueft int, ausgeschlossen_l2 int, eingeschlossen_final int, aktualisiert_am timestamptz NOT NULL DEFAULT now())

p5_tool_entscheidung (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, tool tool_empfehlung NOT NULL, gewaehlt boolean NOT NULL, begruendung text)
  → tool_empfehlung ENUM: 'Rayyan', 'Covidence', 'EPPI_Reviewer', 'DistillerSR', 'ASReview', 'SWIFT_ActiveScreener'
SCHEMA,

            6 => <<<'SCHEMA'
=== PHASE 6 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p6_qualitaetsbewertung (id uuid DEFAULT gen_random_uuid(), treffer_id uuid REFERENCES p5_treffer(id) NOT NULL, studientyp studientyp NOT NULL, rob_tool rob_tool NOT NULL, gesamturteil rob_urteil NOT NULL, hauptproblem text, im_review_behalten boolean NOT NULL, anmerkung text, bewertet_von text, bewertet_am date)
  → studientyp ENUM: 'RCT', 'nicht_randomisiert', 'qualitativ', 'systematic_review', 'guideline_framework', 'konzeptuell'
  → rob_tool ENUM: 'RoB2', 'ROBINS-I', 'CASP_qualitativ', 'AMSTAR2', 'ROBINS-I_erweitert', 'narrativ'
  → rob_urteil ENUM: 'niedrig', 'moderat', 'hoch', 'kritisch', 'nicht_bewertet'
  → Achtung: hat KEIN projekt_id, sondern treffer_id FK

p6_luckenanalyse (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, fehlender_aspekt text NOT NULL, fehlender_studientyp text, moegliche_konsequenz text, empfehlung text)
SCHEMA,

            7 => <<<'SCHEMA'
=== PHASE 7 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p7_synthese_methode (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, methode synthese_methode NOT NULL, gewaehlt boolean NOT NULL DEFAULT false, begruendung text)
  → synthese_methode ENUM: 'meta_analyse', 'narrative_synthese', 'thematische_synthese', 'framework_synthesis'

p7_datenextraktion (id uuid DEFAULT gen_random_uuid(), treffer_id uuid REFERENCES p5_treffer(id) NOT NULL, land text, stichprobe_kontext text, phaenomen_intervention text, outcome_ergebnis text, hauptbefund text, anmerkung text, qualitaetsurteil rob_urteil)
  → rob_urteil ENUM: 'niedrig', 'moderat', 'hoch', 'kritisch', 'nicht_bewertet'
  → Achtung: hat KEIN projekt_id, sondern treffer_id FK

p7_muster_konsistenz (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, muster_befund text NOT NULL, moegliche_erklaerung text, unterstuetzende_quellen jsonb, widersprechende_quellen jsonb)

p7_grade_einschaetzung (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, outcome text NOT NULL, studienanzahl integer, inkonsistenz text, indirektheit text, impraezision text, rob_gesamt rob_urteil, grade_urteil grade_urteil NOT NULL, begruendung text)
  → grade_urteil ENUM: 'stark', 'moderat', 'schwach', 'sehr_schwach'
SCHEMA,

            8 => <<<'SCHEMA'
=== PHASE 8 — Tabellenschemata ===
Schreibe deine Ergebnisse in diese Tabellen:

p8_suchprotokoll (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, suchstring_id uuid REFERENCES p4_suchstrings(id), datenbank text NOT NULL, suchdatum date, db_version text, suchstring_final text NOT NULL, gesetzte_filter jsonb, treffer_gesamt integer, treffer_eindeutig integer)

p8_limitationen (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, limitationstyp text NOT NULL, beschreibung text, auswirkung_auf_vollstaendigkeit text)

p8_reproduzierbarkeitspruefung (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, pruefpunkt text NOT NULL, erfuellt boolean, anmerkung text)

p8_update_plan (id uuid DEFAULT gen_random_uuid(), projekt_id uuid, update_typ text, intervall text, verantwortlich text, tool text, naechstes_update date)
SCHEMA,

            default => null,
        };
    }
}
