-- ============================================================
-- POSTGRESQL DATENBANKMODELL: Systematische Literaturrecherche
-- Abdeckung: Alle 8 Phasen (P1–P8)
-- Stand: 30. März 2026
-- ============================================================

-- ============================================================
-- ERWEITERUNGEN
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- Für Volltextsuche auf Strings

-- ============================================================
-- ENUM TYPEN
-- ============================================================

CREATE TYPE phase_status AS ENUM ('offen', 'in_bearbeitung', 'abgeschlossen');
CREATE TYPE review_typ AS ENUM ('systematic_review', 'scoping_review', 'evidence_map');
CREATE TYPE strukturmodell AS ENUM ('PICO', 'SPIDER', 'PICOS');
CREATE TYPE kriterium_typ AS ENUM ('einschluss', 'ausschluss');
CREATE TYPE screening_level AS ENUM ('L1_titel_abstract', 'L2_volltext');
CREATE TYPE screening_entscheidung AS ENUM ('eingeschlossen', 'ausgeschlossen', 'unklar');
CREATE TYPE rob_tool AS ENUM (
    'RoB2', 'ROBINS-I', 'CASP_qualitativ', 'AMSTAR2',
    'ROBINS-I_erweitert', 'narrativ'
);
CREATE TYPE rob_urteil AS ENUM ('niedrig', 'moderat', 'hoch', 'kritisch', 'nicht_bewertet');
CREATE TYPE synthese_methode AS ENUM (
    'meta_analyse', 'narrative_synthese',
    'thematische_synthese', 'framework_synthesis'
);
CREATE TYPE grade_urteil AS ENUM ('stark', 'moderat', 'schwach', 'sehr_schwach');
CREATE TYPE studientyp AS ENUM (
    'RCT', 'nicht_randomisiert', 'qualitativ',
    'systematic_review', 'guideline_framework', 'konzeptuell'
);
CREATE TYPE tool_empfehlung AS ENUM (
    'Rayyan', 'Covidence', 'EPPI_Reviewer',
    'DistillerSR', 'ASReview', 'SWIFT_ActiveScreener'
);


-- ============================================================
-- KERN: PROJEKTE
-- ============================================================

CREATE TABLE projekte (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    titel           TEXT NOT NULL,
    forschungsfrage TEXT,
    review_typ      review_typ,
    verantwortlich  TEXT,
    startdatum      DATE,
    letztes_update  TIMESTAMPTZ DEFAULT now(),
    notizen         TEXT,
    erstellt_am     TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
-- PHASEN-TRACKING (Übersicht)
-- ============================================================

CREATE TABLE phasen (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    phase_nr    SMALLINT NOT NULL CHECK (phase_nr BETWEEN 1 AND 8),
    titel       TEXT NOT NULL,
    status      phase_status DEFAULT 'offen',
    notizen     TEXT,
    abgeschlossen_am TIMESTAMPTZ,
    UNIQUE (projekt_id, phase_nr)
);


-- ============================================================
-- PHASE 1: FRAGESTELLUNG STRUKTURIEREN (PICO/SPIDER/PICOS)
-- ============================================================

CREATE TABLE p1_strukturmodell_wahl (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    modell      strukturmodell NOT NULL,
    gewaehlt    BOOLEAN DEFAULT FALSE,
    begruendung TEXT
);

CREATE TABLE p1_komponenten (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    modell              strukturmodell NOT NULL,
    komponente_kuerzel  TEXT NOT NULL,       -- z.B. 'P', 'I', 'C', 'O', 'S'
    komponente_label    TEXT NOT NULL,       -- z.B. 'Population / Sample'
    inhaltlicher_begriff_de TEXT,
    synonyme            TEXT[],              -- Array von Synonymen
    englische_entsprechung TEXT,
    mesh_term           TEXT,
    thesaurus_term      TEXT,
    anmerkungen         TEXT
);

CREATE TABLE p1_kriterien (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    kriterium_typ   kriterium_typ NOT NULL,
    beschreibung    TEXT NOT NULL,
    begruendung     TEXT,
    quellbezug      TEXT    -- z.B. 'CR-03, GS-DE-02'
);

CREATE TABLE p1_warnsignale (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    lfd_nr              SMALLINT NOT NULL,   -- W1, W2, W3
    warnsignal          TEXT NOT NULL,
    moegliche_auswirkung TEXT,
    handlungsempfehlung TEXT
);


-- ============================================================
-- PHASE 2: EXPLORATIVE MAPPING-SUCHE
-- ============================================================

CREATE TABLE p2_review_typ_entscheidung (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    review_typ  review_typ NOT NULL,
    passt       BOOLEAN,
    begruendung TEXT
);

CREATE TABLE p2_mapping_suchstring_komponenten (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    komponente_label    TEXT NOT NULL,       -- z.B. 'Hauptbegriff A'
    suchbegriffe        TEXT[],              -- OR-Gruppe
    sprache             TEXT,
    trunkierung_genutzt BOOLEAN DEFAULT FALSE,
    anmerkung           TEXT
);

CREATE TABLE p2_trefferlisten (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    datenbank       TEXT NOT NULL,
    suchstring      TEXT,
    treffer_gesamt  INTEGER,
    einschaetzung   TEXT,       -- z.B. 'zu viel', 'passend', 'zu wenig'
    anpassung_notwendig BOOLEAN DEFAULT FALSE,
    suchdatum       DATE
);

CREATE TABLE p2_cluster (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    cluster_id      TEXT NOT NULL,   -- z.B. 'C-01'
    cluster_label   TEXT NOT NULL,
    beschreibung    TEXT,
    treffer_schätzung INTEGER,
    relevanz        TEXT CHECK (relevanz IN ('hoch', 'mittel', 'gering'))
);


-- ============================================================
-- PHASE 3: DATENBANKAUSWAHL & GEOGRAFISCHE FILTER
-- ============================================================

CREATE TABLE p3_disziplinen (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    disziplin   TEXT NOT NULL,
    art         TEXT CHECK (art IN ('kerndisziplin', 'angrenzend')),
    relevanz    TEXT,
    anmerkung   TEXT
);

CREATE TABLE p3_datenbankmatrix (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    datenbank       TEXT NOT NULL,
    disziplin       TEXT,
    abdeckung       TEXT,
    besonderheit    TEXT,
    zugang          TEXT CHECK (zugang IN ('frei', 'kostenpflichtig', 'institutionell')),
    empfohlen       BOOLEAN,
    begruendung     TEXT
);

CREATE TABLE p3_geografische_filter (
    id                      UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id              UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    region_land             TEXT NOT NULL,
    validierter_filter_vorhanden BOOLEAN DEFAULT FALSE,
    filtername_quelle       TEXT,
    sensitivitaet_prozent   NUMERIC(5,2),
    hilfsstrategie          TEXT
);

CREATE TABLE p3_graue_literatur (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    quelle      TEXT NOT NULL,
    typ         TEXT,
    url         TEXT,
    suchpfad    TEXT,
    relevanz    TEXT,
    anmerkung   TEXT
);


-- ============================================================
-- PHASE 4: SUCHSTRING-ENTWICKLUNG
-- ============================================================

CREATE TABLE p4_suchstrings (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    datenbank           TEXT NOT NULL,
    suchstring          TEXT NOT NULL,       -- vollständig, kopierbar
    feldeinschraenkung  TEXT,               -- z.B. 'TITLE, ABSTRACT'
    gesetzte_filter     JSONB,              -- {sprache: 'de,en', jahr_von: 2010, publikationstyp: 'Artikel'}
    treffer_anzahl      INTEGER,
    einschaetzung       TEXT,
    anpassung           TEXT,
    version             TEXT DEFAULT 'v1.0',
    suchdatum           DATE,
    erstellt_am         TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE p4_thesaurus_mapping (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    freitext_de         TEXT,
    freitext_en         TEXT,
    mesh_term           TEXT,
    emtree_term         TEXT,
    psycinfo_term       TEXT,
    anmerkung           TEXT
);

CREATE TABLE p4_anpassungsprotokoll (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    suchstring_id   UUID NOT NULL REFERENCES p4_suchstrings(id) ON DELETE CASCADE,
    version         TEXT NOT NULL,
    datum           DATE,
    aenderung       TEXT,
    grund           TEXT,
    treffer_vorher  INTEGER,
    treffer_nachher INTEGER,
    entscheidung    TEXT
);


-- ============================================================
-- PHASE 5: SCREENING & DEDUPLIZIERUNG
-- ============================================================

CREATE TABLE p5_prisma_zahlen (
    id                          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id                  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    identifiziert_gesamt        INTEGER,
    davon_datenbank_treffer     INTEGER,
    davon_graue_literatur       INTEGER,
    nach_deduplizierung         INTEGER,
    ausgeschlossen_l1           INTEGER,
    volltext_geprueft           INTEGER,
    ausgeschlossen_l2           INTEGER,
    eingeschlossen_final        INTEGER,
    aktualisiert_am             TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE p5_screening_kriterien (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    level           screening_level NOT NULL,
    kriterium_typ   kriterium_typ NOT NULL,
    beschreibung    TEXT NOT NULL,
    beispiel        TEXT
);

-- Zentrale Treffertabelle (alle Einträge aus allen Datenbanken)
CREATE TABLE p5_treffer (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    record_id       TEXT NOT NULL,          -- z.B. 'REC-001'
    titel           TEXT,
    autoren         TEXT,
    jahr            SMALLINT,
    journal         TEXT,
    doi             TEXT,
    abstract        TEXT,
    datenbank_quelle TEXT,
    ist_duplikat    BOOLEAN DEFAULT FALSE,
    duplikat_von    UUID REFERENCES p5_treffer(id),
    erstellt_am     TIMESTAMPTZ DEFAULT now(),
    UNIQUE (projekt_id, record_id)
);

CREATE TABLE p5_screening_entscheidungen (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    treffer_id      UUID NOT NULL REFERENCES p5_treffer(id) ON DELETE CASCADE,
    level           screening_level NOT NULL,
    entscheidung    screening_entscheidung NOT NULL,
    ausschlussgrund TEXT,
    reviewer        TEXT,
    datum           DATE,
    anmerkung       TEXT
);

CREATE TABLE p5_tool_entscheidung (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    tool        tool_empfehlung NOT NULL,
    gewaehlt    BOOLEAN DEFAULT FALSE,
    begruendung TEXT
);


-- ============================================================
-- PHASE 6: QUALITÄTSBEWERTUNG / CRITICAL APPRAISAL
-- ============================================================

CREATE TABLE p6_qualitaetsbewertung (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    treffer_id      UUID NOT NULL REFERENCES p5_treffer(id) ON DELETE CASCADE,
    studientyp      studientyp NOT NULL,
    rob_tool        rob_tool NOT NULL,
    gesamturteil    rob_urteil NOT NULL,
    hauptproblem    TEXT,
    im_review_behalten BOOLEAN DEFAULT TRUE,
    anmerkung       TEXT,
    bewertet_von    TEXT,
    bewertet_am     DATE
);

CREATE TABLE p6_luckenanalyse (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    fehlender_aspekt    TEXT NOT NULL,
    fehlender_studientyp TEXT,
    moegliche_konsequenz TEXT,
    empfehlung          TEXT
);


-- ============================================================
-- PHASE 7: INTERDISZIPLINÄRE EVIDENZSYNTHESE
-- ============================================================

CREATE TABLE p7_synthese_methode (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    methode     synthese_methode NOT NULL,
    gewaehlt    BOOLEAN DEFAULT FALSE,
    begruendung TEXT
);

CREATE TABLE p7_datenextraktion (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    treffer_id          UUID NOT NULL REFERENCES p5_treffer(id) ON DELETE CASCADE,
    land                TEXT,
    stichprobe_kontext  TEXT,
    phaenomen_intervention TEXT,
    outcome_ergebnis    TEXT,
    hauptbefund         TEXT,
    qualitaetsurteil    rob_urteil,
    anmerkung           TEXT
);

CREATE TABLE p7_muster_konsistenz (
    id                      UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id              UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    muster_befund           TEXT NOT NULL,
    unterstuetzende_quellen TEXT[],     -- Array von Record-IDs
    widersprechende_quellen TEXT[],
    moegliche_erklaerung    TEXT
);

CREATE TABLE p7_grade_einschaetzung (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id      UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    outcome         TEXT NOT NULL,
    studienanzahl   INTEGER,
    rob_gesamt      rob_urteil,
    inkonsistenz    TEXT,
    indirektheit    TEXT,
    impraezision    TEXT,
    grade_urteil    grade_urteil NOT NULL,
    begruendung     TEXT
);


-- ============================================================
-- PHASE 8: BERICHTERSTATTUNG & DOKUMENTATION
-- ============================================================

CREATE TABLE p8_suchprotokoll (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    suchstring_id       UUID REFERENCES p4_suchstrings(id) ON DELETE SET NULL,
    datenbank           TEXT NOT NULL,
    suchdatum           DATE,
    db_version          TEXT,
    suchstring_final    TEXT NOT NULL,      -- vollständig, kopierbar
    gesetzte_filter     JSONB,
    treffer_gesamt      INTEGER,
    treffer_eindeutig   INTEGER
);

CREATE TABLE p8_limitationen (
    id                          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id                  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    limitationstyp              TEXT NOT NULL,
    beschreibung                TEXT,
    auswirkung_auf_vollstaendigkeit TEXT
);

CREATE TABLE p8_reproduzierbarkeitspruefung (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id  UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    pruefpunkt  TEXT NOT NULL,
    erfuellt    BOOLEAN,
    anmerkung   TEXT
);

CREATE TABLE p8_update_plan (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    projekt_id          UUID NOT NULL REFERENCES projekte(id) ON DELETE CASCADE,
    update_typ          TEXT CHECK (update_typ IN ('living_review', 'periodisch')),
    intervall           TEXT,               -- z.B. 'monatlich', 'alle 2 Jahre'
    verantwortlich      TEXT,
    tool                TEXT,
    naechstes_update    DATE
);


-- ============================================================
-- INDIZES (Performance)
-- ============================================================

-- Projekt-FK auf allen Phasentabellen
CREATE INDEX idx_phasen_projekt ON phasen(projekt_id);
CREATE INDEX idx_p1_komp_projekt ON p1_komponenten(projekt_id);
CREATE INDEX idx_p5_treffer_projekt ON p5_treffer(projekt_id);
CREATE INDEX idx_p5_treffer_record ON p5_treffer(record_id);
CREATE INDEX idx_p5_treffer_duplikat ON p5_treffer(ist_duplikat);
CREATE INDEX idx_p5_screening_treffer ON p5_screening_entscheidungen(treffer_id);
CREATE INDEX idx_p6_bewertung_treffer ON p6_qualitaetsbewertung(treffer_id);
CREATE INDEX idx_p7_extraktion_treffer ON p7_datenextraktion(treffer_id);

-- Volltextsuche auf Treffer-Abstracts
CREATE INDEX idx_p5_treffer_abstract_fts
    ON p5_treffer USING gin(to_tsvector('german', coalesce(abstract, '')));

CREATE INDEX idx_p5_treffer_titel_fts
    ON p5_treffer USING gin(to_tsvector('german', coalesce(titel, '')));


-- ============================================================
-- HILFSFUNKTION: Aktuelle PRISMA-Zahlen berechnen
-- ============================================================

CREATE OR REPLACE FUNCTION berechne_prisma_zahlen(p_projekt_id UUID)
RETURNS TABLE (
    identifiziert_gesamt    BIGINT,
    duplikate               BIGINT,
    nach_deduplizierung     BIGINT,
    ausgeschlossen_l1       BIGINT,
    volltext_geprueft       BIGINT,
    ausgeschlossen_l2       BIGINT,
    eingeschlossen_final    BIGINT
) LANGUAGE sql AS $$
    SELECT
        COUNT(*)                                                    AS identifiziert_gesamt,
        COUNT(*) FILTER (WHERE ist_duplikat)                       AS duplikate,
        COUNT(*) FILTER (WHERE NOT ist_duplikat)                   AS nach_deduplizierung,

        COUNT(*) FILTER (
            WHERE NOT ist_duplikat
            AND EXISTS (
                SELECT 1 FROM p5_screening_entscheidungen se
                WHERE se.treffer_id = t.id
                  AND se.level = 'L1_titel_abstract'
                  AND se.entscheidung = 'ausgeschlossen'
            )
        )                                                           AS ausgeschlossen_l1,

        COUNT(*) FILTER (
            WHERE NOT ist_duplikat
            AND EXISTS (
                SELECT 1 FROM p5_screening_entscheidungen se
                WHERE se.treffer_id = t.id
                  AND se.level = 'L2_volltext'
            )
        )                                                           AS volltext_geprueft,

        COUNT(*) FILTER (
            WHERE NOT ist_duplikat
            AND EXISTS (
                SELECT 1 FROM p5_screening_entscheidungen se
                WHERE se.treffer_id = t.id
                  AND se.level = 'L2_volltext'
                  AND se.entscheidung = 'ausgeschlossen'
            )
        )                                                           AS ausgeschlossen_l2,

        COUNT(*) FILTER (
            WHERE NOT ist_duplikat
            AND EXISTS (
                SELECT 1 FROM p5_screening_entscheidungen se
                WHERE se.treffer_id = t.id
                  AND se.level = 'L2_volltext'
                  AND se.entscheidung = 'eingeschlossen'
            )
        )                                                           AS eingeschlossen_final

    FROM p5_treffer t
    WHERE t.projekt_id = p_projekt_id;
$$;
