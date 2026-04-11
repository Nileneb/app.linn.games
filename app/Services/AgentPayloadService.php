<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes and persists the db_payload from agent responses.
 *
 * Takes structured JSON responses from agents and writes the contained
 * database payload (tables and rows) into the actual database.
 */
class AgentPayloadService
{
    /**
     * Parse and persist db_payload from agent JSON response.
     *
     * @param  array  $agentResponse  Parsed JSON response with optional db_payload
     * @param  string  $projektId  Project UUID for RLS context
     * @return array{success: bool, tables_written: int, rows_written: int, error?: string}
     */
    public function persistPayload(array $agentResponse, string $projektId): array
    {
        $dbPayload = $agentResponse['db_payload'] ?? null;

        if (! $dbPayload || ! is_array($dbPayload)) {
            return ['success' => true, 'tables_written' => 0, 'rows_written' => 0];
        }

        $tables = $dbPayload['tables'] ?? [];
        if (! is_array($tables) || empty($tables)) {
            return ['success' => true, 'tables_written' => 0, 'rows_written' => 0];
        }

        $tablesWritten = 0;
        $rowsWritten = 0;
        $errors = [];

        try {
            // PostgreSQL SET LOCAL does not support PDO parameter binding.
            // UUID format is validated above (matches [0-9a-f-]{36}) so interpolation is safe.
            if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $projektId)) {
                throw new \InvalidArgumentException("Invalid projekt_id format: {$projektId}");
            }
            DB::statement("SET LOCAL app.current_projekt_id = '{$projektId}'");

            foreach ($tables as $tableName => $rows) {
                if (! is_string($tableName) || ! is_array($rows) || empty($rows)) {
                    continue;
                }

                try {
                    $rowsWritten += $this->insertRows($tableName, $rows, $projektId);
                    $tablesWritten++;

                    Log::info("AgentPayloadService: Rows written to {$tableName}", [
                        'table' => $tableName,
                        'row_count' => count($rows),
                        'projekt_id' => $projektId,
                    ]);
                } catch (\Throwable $e) {
                    $error = "Table {$tableName}: ".$e->getMessage();
                    $errors[] = $error;
                    Log::warning("AgentPayloadService: Failed to write to {$tableName}", [
                        'table' => $tableName,
                        'exception' => $e->getMessage(),
                        'projekt_id' => $projektId,
                    ]);
                }
            }

            return [
                'success' => empty($errors),
                'tables_written' => $tablesWritten,
                'rows_written' => $rowsWritten,
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            Log::error('AgentPayloadService: RLS context error', [
                'exception' => $e->getMessage(),
                'projekt_id' => $projektId,
            ]);

            return [
                'success' => false,
                'tables_written' => 0,
                'rows_written' => 0,
                'error' => 'RLS context setup failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Insert rows into a table.
     *
     * @param  string  $tableName  Table name (validated against whitelist)
     * @param  array  $rows  Array of row objects/arrays to insert
     * @param  string  $projektId  Project UUID for RLS
     * @return int Number of rows inserted
     */
    private function insertRows(string $tableName, array $rows, string $projektId): int
    {
        // Whitelist of allowed tables (only agent output tables — must match actual DB table names)
        $allowedTables = [
            // P1
            'p1_komponenten',
            'p1_kriterien',
            'p1_strukturmodell_wahl',
            'p1_warnsignale',
            // P2
            'p2_cluster',
            'p2_review_typ_entscheidung',
            'p2_mapping_suchstring_komponenten',
            'p2_trefferlisten',
            // P3
            'p3_datenbankmatrix',
            'p3_disziplinen',
            'p3_geografische_filter',
            'p3_graue_literatur',
            // P4
            'p4_suchstrings',
            'p4_thesaurus_mapping',
            'p4_anpassungsprotokoll',
            // P5
            'p5_treffer',
            'p5_screening_kriterien',
            'p5_screening_entscheidungen',
            'p5_prisma_zahlen',
            'p5_tool_entscheidung',
            // P6
            'p6_qualitaetsbewertung',
            'p6_luckenanalyse',
            // P7
            'p7_datenextraktion',
            'p7_muster_konsistenz',
            'p7_grade_einschaetzung',
            'p7_synthese_methode',
            // P8
            'p8_suchprotokoll',
            'p8_limitationen',
            'p8_reproduzierbarkeitspruefung',
            'p8_update_plan',
        ];

        if (! in_array($tableName, $allowedTables, true)) {
            throw new \InvalidArgumentException("Table {$tableName} is not whitelisted for agent payload insertion");
        }

        $inserted = 0;

        foreach ($rows as $row) {
            if (! is_array($row) && ! is_object($row)) {
                continue;
            }

            $data = (array) $row;

            // Ensure projekt_id is set
            if (! isset($data['projekt_id'])) {
                $data['projekt_id'] = $projektId;
            }

            // Add timestamps if missing
            $now = now();
            if (! isset($data['created_at'])) {
                $data['created_at'] = $now;
            }
            if (! isset($data['updated_at'])) {
                $data['updated_at'] = $now;
            }

            try {
                DB::table($tableName)->insert($data);
                $inserted++;
            } catch (\Throwable $e) {
                Log::warning("Failed to insert row into {$tableName}", [
                    'row' => $data,
                    'exception' => $e->getMessage(),
                ]);
                // Continue with next row instead of failing entire batch
            }
        }

        return $inserted;
    }
}
