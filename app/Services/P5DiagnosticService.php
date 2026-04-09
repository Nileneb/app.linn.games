<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Debug and diagnose P5 schema queries and RLS issues.
 *
 * The problem: P5-treffer query returns empty despite 104 hits being imported.
 * Likely cause: RLS policies not respecting app.current_projekt_id SET LOCAL.
 */
class P5DiagnosticService
{
    /**
     * Run full diagnostic on P5 schema
     */
    public function diagnose(string $projektId, int $userId = 0): array
    {
        $results = [
            'projekt_id' => $projektId,
            'timestamp' => now(),
            'checks' => [],
        ];

        // 1. Verify app.current_projekt_id is set
        $results['checks']['env_variable'] = $this->checkEnvVariable($projektId);

        // 2. Count all hits in p5_treffer
        $results['checks']['total_hits'] = $this->countTotalHits();

        // 3. Count hits for this projekt WITHOUT RLS
        $results['checks']['hits_without_rls'] = $this->countHitsWithoutRls($projektId);

        // 4. Count hits with RLS enabled
        $results['checks']['hits_with_rls'] = $this->countHitsWithRls($projektId, $userId);

        // 5. Check RLS policy details
        $results['checks']['rls_status'] = $this->checkRlsStatus();

        // 6. Sample hit data (first 5)
        $results['checks']['sample_hits'] = $this->getSampleHits($projektId, 5);

        // 7. Check screening_kriterien
        $results['checks']['screening_kriterien'] = $this->checkScreeningKriterien($projektId);

        Log::warning('P5 Schema Diagnostic', $results);

        return $results;
    }

    private function checkEnvVariable(string $projektId): array
    {
        try {
            DB::statement('SET LOCAL app.current_projekt_id = ?', [$projektId]);
            $value = DB::selectOne("SELECT current_setting('app.current_projekt_id') as value")?->value;

            return [
                'status' => $value === $projektId ? 'ok' : 'mismatch',
                'set_value' => $projektId,
                'retrieved_value' => $value,
                'explanation' => 'SET LOCAL sets session variable for current transaction',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function countTotalHits(): array
    {
        try {
            $count = DB::selectOne('SELECT COUNT(*) as count FROM p5_treffer')?->count;

            return [
                'status' => 'ok',
                'total_count' => $count,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function countHitsWithoutRls(string $projektId): array
    {
        try {
            // Disable RLS temporarily for this query
            $count = DB::selectOne(
                'SELECT COUNT(*) as count FROM p5_treffer WHERE projekt_id = ?',
                [$projektId],
            )?->count;

            return [
                'status' => 'ok',
                'count_for_projekt' => $count,
                'method' => 'Direct WHERE clause (RLS bypassed)',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function countHitsWithRls(string $projektId, int $userId): array
    {
        try {
            DB::statement('SET LOCAL app.current_projekt_id = ?', [$projektId]);

            if ($userId > 0) {
                DB::statement('SET LOCAL app.current_user_id = ?', [$userId]);
            }

            $count = DB::selectOne('SELECT COUNT(*) as count FROM p5_treffer')?->count;

            return [
                'status' => 'ok',
                'count_with_rls' => $count,
                'method' => 'With SET LOCAL app.current_projekt_id',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRlsStatus(): array
    {
        try {
            $policies = DB::select(
                "SELECT schemaname, tablename, policyname, qual, with_check FROM pg_policies WHERE tablename = 'p5_treffer'",
            );

            return [
                'status' => 'ok',
                'policy_count' => count($policies),
                'policies' => array_map(fn ($p) => [
                    'name' => $p->policyname,
                    'qual' => $p->qual,
                    'with_check' => $p->with_check,
                ], $policies),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getSampleHits(string $projektId, int $limit = 5): array
    {
        try {
            $hits = DB::select(
                'SELECT id, projekt_id, titel, abstract FROM p5_treffer WHERE projekt_id = ? LIMIT ?',
                [$projektId, $limit],
            );

            return [
                'status' => 'ok',
                'count' => count($hits),
                'samples' => $hits,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkScreeningKriterien(string $projektId): array
    {
        try {
            $kriterien = DB::select(
                'SELECT id, projekt_id, typ, beschreibung FROM p5_screening_kriterien WHERE projekt_id = ?',
                [$projektId],
            );

            return [
                'status' => 'ok',
                'count' => count($kriterien),
                'kriterien' => $kriterien,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the actual SQL query that should be run for screening
     */
    public function getScreeningQuery(string $projektId): string
    {
        return <<<SQL
-- Setup current projekt context
SET LOCAL app.current_projekt_id = '$projektId';

-- Query P5 treffer with screening state
SELECT 
    t.id,
    t.projekt_id,
    t.titel,
    t.abstract,
    t.retrieval_datenbank,
    sk.typ as screening_kriterium_typ,
    COUNT(CASE WHEN s.entscheidung IS NOT NULL THEN 1 END) as has_screens,
    MAX(s.erstellt_am) as last_screen_time
FROM p5_treffer t
LEFT JOIN p5_screening_kriterien sk ON sk.projekt_id = t.projekt_id
LEFT JOIN p5_screening s ON s.treffer_id = t.id
WHERE t.projekt_id = '$projektId'
GROUP BY t.id, t.projekt_id, t.titel, t.abstract, t.retrieval_datenbank, sk.id, sk.typ
ORDER BY t.erstellt_am DESC;
SQL;
    }
}
