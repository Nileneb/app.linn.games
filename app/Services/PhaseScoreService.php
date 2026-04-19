<?php

namespace App\Services;

use App\Models\PhaseAgentResult;

class PhaseScoreService
{
    /**
     * Normalisiert Score + leitet Level server-seitig ab (verhindert AI-Fehler bei level/score-Diskrepanz).
     * Persistiert result_data nur wenn bewertung['score'] vorhanden ist.
     */
    public function calculateAndPersistP1Score(PhaseAgentResult $result, array $parsed): void
    {
        $bewertung = $parsed['meta']['qualitaets_bewertung'] ?? null;

        if (! is_array($bewertung) || ! isset($bewertung['score'])) {
            return;
        }

        $score = max(0, min(100, (int) $bewertung['score']));
        $bewertung['score'] = $score;
        $bewertung['level'] = match (true) {
            $score >= 80 => 'sehr_gut',
            $score >= 60 => 'gut',
            $score >= 40 => 'befriedigend',
            default      => 'schwach',
        };

        $result->update(['result_data' => ['qualitaets_bewertung' => $bewertung]]);
    }
}
