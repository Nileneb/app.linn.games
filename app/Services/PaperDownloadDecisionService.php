<?php

namespace App\Services;

use App\Models\Paper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Papers download decision engine based on algorithmic rules.
 *
 * Instead of manual "download or not" decisions, we evaluate structured criteria:
 * - open_access: prefer OA sources
 * - has_doi: must have valid DOI
 * - from_database: whitelist certain databases
 * - language: filter by language
 * - year: publication year range
 */
class PaperDownloadDecisionService
{
    /**
     * Evaluate if a paper should be downloaded based on project rules
     */
    public function shouldDownload(string $projektId, Paper $paper): bool
    {
        $rules = $this->getActiveRulesForProjekt($projektId);

        if (empty($rules)) {
            // No rules = download everything
            return true;
        }

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $paper)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all active download rules for a project, sorted by priority
     */
    public function getActiveRulesForProjekt(string $projektId): array
    {
        return DB::table('paper_download_rules')
            ->where('projekt_id', $projektId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->toArray();
    }

    /**
     * Evaluate a single rule against a paper
     */
    public function evaluateRule(\stdClass $rule, Paper $paper): bool
    {
        $criteria = json_decode($rule->criteria, true) ?? [];

        // Check each criterion
        foreach ($criteria as $key => $value) {
            if (! $this->checkCriterion($key, $value, $paper)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check a single criterion
     */
    private function checkCriterion(string $key, mixed $value, Paper $paper): bool
    {
        return match ($key) {
            'open_access' => $value === true ? ($paper->is_open_access ?? false) : true,
            'has_doi' => $value === true ? ! empty($paper->doi) : true,
            'databases' => is_array($value) ? in_array($paper->source_database, $value) : true,
            'languages' => is_array($value) ? in_array($paper->language, $value) : true,
            'year_min' => isset($paper->published_year) ? $paper->published_year >= $value : true,
            'year_max' => isset($paper->published_year) ? $paper->published_year <= $value : true,
            'exclude_keywords' => is_array($value) ? ! $this->hasKeywords($paper, $value) : true,
            default => true,
        };
    }

    /**
     * Check if paper contains exclude keywords
     */
    private function hasKeywords(Paper $paper, array $keywords): bool
    {
        $text = strtolower($paper->titel.' '.($paper->abstract ?? ''));

        foreach ($keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a default rule set for a project
     */
    public function createDefaultRules(string $projektId): void
    {
        $ruleTemplates = [
            [
                'name' => 'open_access_priority',
                'priority' => 10,
                'criteria' => [
                    'open_access' => true,
                ],
            ],
            [
                'name' => 'has_doi',
                'priority' => 20,
                'criteria' => [
                    'has_doi' => true,
                ],
            ],
            [
                'name' => 'english_only',
                'priority' => 30,
                'criteria' => [
                    'languages' => ['en', 'de'],
                ],
            ],
        ];

        foreach ($ruleTemplates as $template) {
            DB::table('paper_download_rules')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'projekt_id' => $projektId,
                'name' => $template['name'],
                'description' => null,
                'criteria' => json_encode($template['criteria']),
                'is_active' => true,
                'priority' => $template['priority'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('Default download rules created', ['projekt_id' => $projektId]);
    }

    /**
     * Add a custom rule
     */
    public function addRule(
        string $projektId,
        string $name,
        array $criteria,
        int $priority = 100,
        ?string $description = null,
    ): void {
        DB::table('paper_download_rules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'projekt_id' => $projektId,
            'name' => $name,
            'description' => $description,
            'criteria' => json_encode($criteria),
            'is_active' => true,
            'priority' => $priority,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
