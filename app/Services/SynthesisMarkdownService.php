<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Generates markdown synthesis files with complete source traceability.
 *
 * Takes agent response data and retrieved document chunks, formats them
 * as markdown with HTML comments encoding paper_id, chunk_index, and
 * similarity scores for full reproducibility.
 */
class SynthesisMarkdownService
{
    /**
     * Generate synthesis markdown with embedded traceability metadata.
     *
     * @param  array  $agentData  Parsed JSON response from agent (expects 'data' key)
     * @param  array<object>  $retrievedChunks  Results from RetrieverService::retrieve()
     * @return string Formatted markdown content
     */
    public function generateSynthesis(int $phaseNr, array $agentData, array $retrievedChunks): string
    {
        $title = $this->getTitleForPhase($phaseNr);
        $chunks = new Collection($retrievedChunks);
        $chunksByPaper = $chunks->groupBy('paper_id');

        $md = [];
        $md[] = "# Phase $phaseNr - Synthesis & Evidence Summary";
        $md[] = '';

        // Table of contents
        $md[] = '## Table of Contents';
        $md[] = '';
        $md[] = match ($phaseNr) {
            5 => '- [Screening Criteria](#screening-criteria)'."\n".'- [Screening Decisions](#screening-decisions)',
            6 => '- [Quality Assessment](#quality-assessment)'."\n".'- [Risk of Bias Summary](#risk-of-bias-summary)',
            7 => '- [Data Extraction](#data-extraction)'."\n".'- [Pattern Analysis](#pattern-analysis)',
            8 => '- [Search Protocol](#search-protocol)'."\n".'- [Limitations](#limitations)'."\n".'- [Reproducibility Checklist](#reproducibility-checklist)',
            default => '- Overview',
        };
        $md[] = '';
        $md[] = '---';
        $md[] = '';

        // Phase-specific content sections
        try {
            match ($phaseNr) {
                5 => $this->appendP5Content($md, $agentData, $chunksByPaper),
                6 => $this->appendP6Content($md, $agentData, $chunksByPaper),
                7 => $this->appendP7Content($md, $agentData, $chunksByPaper),
                8 => $this->appendP8Content($md, $agentData, $chunksByPaper),
                default => $md[] = '## Content'."\n".'Agent response data available.',
            };
        } catch (\Throwable $e) {
            $md[] = '## Content';
            $md[] = '';
            $md[] = '> [Fehler beim Generieren des Phaseninhalts: '.$e->getMessage().']';
        }

        $md[] = '';
        $md[] = '---';
        $md[] = '';
        $md[] = '## References';
        $md[] = '';
        $md[] = '### Full Paper Index';
        $md[] = '';
        $md[] = '| Paper ID | Title | Chunks | Avg Similarity |';
        $md[] = '|----------|-------|--------|----------------|';

        foreach ($chunksByPaper as $paperId => $paperChunks) {
            $firstChunk = $paperChunks->first();
            $avgSim = number_format($paperChunks->avg('similarity'), 2);
            $md[] = "| `{$paperId}` | {$firstChunk->title} | ".count($paperChunks)." | {$avgSim} |";
        }

        $md[] = '';
        $md[] = '---';
        $md[] = '';
        $md[] = '## Traceability Log';
        $md[] = '';
        $md[] = 'This document was generated with complete source attribution. All quoted sections include:';
        $md[] = '- **paper_id**: Unique identifier in p5_treffer table';
        $md[] = '- **chunk_index**: Sequential position in paper_embeddings (0-based)';
        $md[] = '- **similarity**: Embedding similarity score (0.00–1.00, higher = more relevant)';
        $md[] = '';
        $md[] = 'To trace back any evidence:';
        $md[] = '1. Find the HTML comment with `paper_id: ABC-123`';
        $md[] = '2. Query: `SELECT * FROM p5_treffer WHERE id = \'ABC-123\'`';
        $md[] = '3. Retrieve full text from: `retrieval_storage_path` field';
        $md[] = '4. Locate chunk via `chunk_index` from `paper_embeddings` table';
        $md[] = '';
        $md[] = '**Chunks used in synthesis:** '.count($retrievedChunks);
        $md[] = '**Unique papers referenced:** '.count($chunksByPaper);
        $md[] = '**Average similarity:** '.number_format($chunks->avg('similarity'), 2);
        $md[] = '**Generation date:** '.now()->toDateTimeString();

        return implode("\n", $md);
    }

    /**
     * P5 Screening synthesis
     */
    private function appendP5Content(array &$md, array $agentData, Collection $chunksByPaper): void
    {
        $md[] = '## Screening Criteria';
        $md[] = '';

        $criteria = $agentData['screening_criteria'] ?? [];
        if (is_array($criteria) && ! empty($criteria)) {
            $md[] = '### Inclusion Criteria';
            $md[] = '';
            foreach ($criteria as $criterion) {
                if (is_array($criterion)) {
                    $type = $criterion['kriterium_typ'] ?? 'criterion';
                    $desc = $criterion['beschreibung'] ?? $criterion['description'] ?? '';
                    if ($type === 'einschluss' || $type === 'inclusion') {
                        $md[] = "- {$desc}";
                    }
                }
            }
        }

        $md[] = '';
        $md[] = '### Exclusion Criteria';
        $md[] = '';

        if (is_array($criteria) && ! empty($criteria)) {
            foreach ($criteria as $criterion) {
                if (is_array($criterion)) {
                    $type = $criterion['kriterium_typ'] ?? 'criterion';
                    $desc = $criterion['beschreibung'] ?? $criterion['description'] ?? '';
                    if ($type === 'ausschluss' || $type === 'exclusion') {
                        $md[] = "- {$desc}";
                    }
                }
            }
        }

        $md[] = '';
        $md[] = '## Screening Decisions';
        $md[] = '';

        $decisions = $agentData['screening_entscheidungen'] ?? [];
        if (is_array($decisions) && ! empty($decisions)) {
            foreach ($decisions as $idx => $decision) {
                if (! is_array($decision)) {
                    continue;
                }

                $paperId = $decision['treffer_id'] ?? $decision['paper_id'] ?? "paper-{$idx}";
                $title = $decision['titel'] ?? $decision['title'] ?? 'Untitled';
                $status = $decision['entscheidung'] ?? $decision['status'] ?? 'unknown';
                $reason = $decision['grund'] ?? $decision['reason'] ?? '';

                $md[] = "### Paper: \"{$title}\"";
                $md[] = "<!-- paper_id: {$paperId}; source: Screening -->";
                $md[] = '';
                $md[] = "**Decision:** {$status}";

                if ($reason) {
                    $md[] = "**Reason:** {$reason}";
                }

                $md[] = '';

                // Add chunks for this paper if available
                if ($chunksByPaper->has($paperId)) {
                    $md[] = '**Relevant excerpt:**';
                    $md[] = '';
                    foreach ($chunksByPaper->get($paperId) as $chunk) {
                        $md[] = "> {$chunk->text_chunk}";
                        $md[] = "<!-- chunk_index: {$chunk->chunk_index}; similarity: ".
                                number_format($chunk->similarity, 2).'; source: Abstract -->';
                        $md[] = '';
                    }
                }

                $md[] = '---';
                $md[] = '';
            }
        }
    }

    /**
     * P6 Quality Assessment synthesis
     */
    private function appendP6Content(array &$md, array $agentData, Collection $chunksByPaper): void
    {
        $md[] = '## Quality Assessment';
        $md[] = '';

        $assessments = $agentData['qualitaetsbewertung'] ?? $agentData['quality_assessments'] ?? [];
        if (is_array($assessments) && ! empty($assessments)) {
            foreach ($assessments as $assessment) {
                if (! is_array($assessment)) {
                    continue;
                }

                $paperId = $assessment['treffer_id'] ?? $assessment['paper_id'] ?? '';
                $title = $assessment['titel'] ?? $assessment['title'] ?? 'Untitled';
                $studientyp = $assessment['studientyp'] ?? $assessment['study_type'] ?? '';
                $tool = $assessment['rob_tool'] ?? $assessment['quality_tool'] ?? '';
                $urteil = $assessment['gesamturteil'] ?? $assessment['overall_judgement'] ?? '';

                $md[] = "### {$title}";
                $md[] = "<!-- paper_id: {$paperId}; study_type: {$studientyp} -->";
                $md[] = '';
                $md[] = "**Study Type:** {$studientyp}";
                $md[] = "**Assessment Tool:** {$tool}";
                $md[] = "**Overall Risk of Bias:** {$urteil}";
                $md[] = '';

                // Add evidence from chunks
                if ($chunksByPaper->has($paperId)) {
                    $md[] = '**Evidence from full text:**';
                    $md[] = '';
                    foreach ($chunksByPaper->get($paperId) as $chunk) {
                        $md[] = "> {$chunk->text_chunk}";
                        $md[] = "<!-- chunk_index: {$chunk->chunk_index}; similarity: ".
                                number_format($chunk->similarity, 2).'; source: Methods -->';
                        $md[] = '';
                    }
                }

                $md[] = '---';
                $md[] = '';
            }
        }
    }

    /**
     * P7 Data Extraction synthesis
     */
    private function appendP7Content(array &$md, array $agentData, Collection $chunksByPaper): void
    {
        $md[] = '## Data Extraction';
        $md[] = '';

        $extractions = $agentData['datenextraktion'] ?? $agentData['data_extractions'] ?? [];
        if (is_array($extractions) && ! empty($extractions)) {
            foreach ($extractions as $extraction) {
                if (! is_array($extraction)) {
                    continue;
                }

                $paperId = $extraction['treffer_id'] ?? $extraction['paper_id'] ?? '';
                $title = $extraction['titel'] ?? $extraction['title'] ?? 'Untitled';

                $md[] = "### {$title}";
                $md[] = "<!-- paper_id: {$paperId}; source: Methods/Results -->";
                $md[] = '';

                // Key characteristics
                if (! empty($extraction['stichprobe'])) {
                    $md[] = "**Sample Size:** {$extraction['stichprobe']}";
                }
                if (! empty($extraction['land'])) {
                    $md[] = "**Country:** {$extraction['land']}";
                }
                if (! empty($extraction['intervention'])) {
                    $md[] = "**Intervention:** {$extraction['intervention']}";
                }

                $md[] = '';

                // Outcomes and findings
                $outcomes = $extraction['befunde'] ?? $extraction['outcomes'] ?? [];
                if (is_array($outcomes) && ! empty($outcomes)) {
                    $md[] = '**Findings:**';
                    $md[] = '';
                    foreach ($outcomes as $outcome) {
                        if (is_array($outcome)) {
                            $type = $outcome['outcome_type'] ?? '';
                            $value = $outcome['value'] ?? $outcome['result'] ?? '';
                            $md[] = "- **{$type}:** {$value}";
                        }
                    }
                    $md[] = '';
                }

                // Add chunks
                if ($chunksByPaper->has($paperId)) {
                    $md[] = '**Evidence from text:**';
                    $md[] = '';
                    foreach ($chunksByPaper->get($paperId) as $chunk) {
                        $md[] = "> {$chunk->text_chunk}";
                        $md[] = "<!-- chunk_index: {$chunk->chunk_index}; similarity: ".
                                number_format($chunk->similarity, 2).'; source: Results -->';
                        $md[] = '';
                    }
                }

                $md[] = '---';
                $md[] = '';
            }
        }

        // Pattern analysis
        $md[] = '## Pattern Analysis';
        $md[] = '';

        $patterns = $agentData['muster_konsistenz'] ?? $agentData['patterns'] ?? [];
        if (is_string($patterns)) {
            $md[] = $patterns;
        } elseif (is_array($patterns)) {
            $md[] = json_encode($patterns, JSON_PRETTY_PRINT);
        }
    }

    /**
     * P8 Documentation synthesis
     */
    private function appendP8Content(array &$md, array $agentData, Collection $chunksByPaper): void
    {
        $md[] = '## Search Protocol';
        $md[] = '';

        $protocol = $agentData['suchprotokoll'] ?? $agentData['search_protocol'] ?? [];
        if (is_array($protocol)) {
            if (! empty($protocol['datenbanken'])) {
                $md[] = '**Databases:**';
                $md[] = '';
                foreach ((array) $protocol['datenbanken'] as $db) {
                    $md[] = "- {$db}";
                }
                $md[] = '';
            }

            if (! empty($protocol['suchstrings'])) {
                $md[] = '**Search Strings:**';
                $md[] = '';
                foreach ((array) $protocol['suchstrings'] as $string) {
                    $md[] = "```\n{$string}\n```";
                    $md[] = '';
                }
            }
        }

        $md[] = '## Limitations';
        $md[] = '';

        $limitations = $agentData['limitationen'] ?? $agentData['limitations'] ?? [];
        if (is_array($limitations) && ! empty($limitations)) {
            foreach ($limitations as $limitation) {
                if (is_array($limitation)) {
                    $desc = $limitation['beschreibung'] ?? $limitation['description'] ?? '';
                    $md[] = "- {$desc}";
                } else {
                    $md[] = "- {$limitation}";
                }
            }
        }

        $md[] = '';
        $md[] = '## Reproducibility Checklist';
        $md[] = '';

        $checklist = $agentData['reproduzierbarkeitspruefung'] ?? $agentData['reproducibility_checklist'] ?? [];
        if (is_array($checklist) && ! empty($checklist)) {
            foreach ($checklist as $item) {
                if (is_array($item)) {
                    $check = $item['item'] ?? $item['beschreibung'] ?? '';
                    $status = (bool) ($item['erfuellt'] ?? $item['completed'] ?? false) ? '✓' : '✗';
                    $md[] = "- [{$status}] {$check}";
                } else {
                    $md[] = "- [ ] {$item}";
                }
            }
        }
    }

    /**
     * Get appropriate title for phase
     */
    private function getTitleForPhase(int $phaseNr): string
    {
        return match ($phaseNr) {
            5 => 'Screening',
            6 => 'Quality Assessment',
            7 => 'Data Extraction & Synthesis',
            8 => 'Documentation & Reproducibility',
            default => "Phase {$phaseNr}",
        };
    }
}
