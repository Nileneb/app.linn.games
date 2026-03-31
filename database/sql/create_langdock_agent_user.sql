-- =============================================================
-- MCP-Postgres DB-User: langdock_agent
-- Restricted read/write access to Recherche tables only.
-- Run as superuser or DB owner against the application database.
-- =============================================================

-- 1. Create role (idempotent)
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'langdock_agent') THEN
        CREATE ROLE langdock_agent LOGIN PASSWORD 'CHANGE_ME_IN_ENV';
    END IF;
END
$$;

-- 2. Allow connection
GRANT CONNECT ON DATABASE linn_games TO langdock_agent;

-- 3. Schema usage
GRANT USAGE ON SCHEMA public TO langdock_agent;

-- 4. Read-only on user / auth tables and migrations (needed for MCP health check)
GRANT SELECT ON users, migrations, chat_messages, webhooks TO langdock_agent;
GRANT USAGE, SELECT ON SEQUENCE migrations_id_seq TO langdock_agent;

-- 5. Full CRUD on all Recherche tables
GRANT SELECT, INSERT, UPDATE, DELETE ON
    projekte,
    phasen,
    p1_strukturmodell_wahl,
    p1_komponenten,
    p1_kriterien,
    p1_warnsignale,
    p2_review_typ_entscheidung,
    p2_mapping_suchstring_komponenten,
    p2_trefferlisten,
    p2_cluster,
    p3_disziplinen,
    p3_datenbankmatrix,
    p3_geografische_filter,
    p3_graue_literatur,
    p4_suchstrings,
    p4_thesaurus_mapping,
    p4_anpassungsprotokoll,
    p5_prisma_zahlen,
    p5_screening_kriterien,
    p5_treffer,
    p5_screening_entscheidungen,
    p5_tool_entscheidung,
    p6_qualitaetsbewertung,
    p6_luckenanalyse,
    p7_synthese_methode,
    p7_datenextraktion,
    p7_muster_konsistenz,
    p7_grade_einschaetzung,
    p8_suchprotokoll,
    p8_limitationen,
    p8_reproduzierbarkeitspruefung,
    p8_update_plan
TO langdock_agent;

-- 6. Allow calling the PRISMA helper function
GRANT EXECUTE ON FUNCTION berechne_prisma_zahlen(UUID) TO langdock_agent;

-- 7. Deny access to sensitive tables
REVOKE ALL ON
    password_reset_tokens,
    sessions,
    cache,
    cache_locks,
    jobs,
    job_batches,
    failed_jobs,
    permissions,
    roles,
    model_has_permissions,
    model_has_roles,
    role_has_permissions,
    consents,
    contacts,
    page_views
FROM langdock_agent;
