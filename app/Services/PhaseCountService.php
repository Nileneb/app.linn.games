<?php

namespace App\Services;

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\DB;

class PhaseCountService
{
    /**
     * Returns an associative array of relevant counts for the given phase.
     * Keys match the threshold config keys (e.g., 'min_components' → 'components').
     *
     * @return array<string, int>
     */
    public function countForPhase(Projekt $projekt, int $phaseNr): array
    {
        return match ($phaseNr) {
            1 => $this->countPhase1($projekt),
            2 => $this->countPhase2($projekt),
            3 => $this->countPhase3($projekt),
            4 => $this->countPhase4($projekt),
            5 => $this->countPhase5($projekt),
            6 => $this->countPhase6($projekt),
            7 => $this->countPhase7($projekt),
            8 => [],
            default => [],
        };
    }

    private function countPhase1(Projekt $projekt): array
    {
        return [
            'components' => rescue(
                fn () => $projekt->p1Komponenten()->count(),
                0
            ),
        ];
    }

    private function countPhase2(Projekt $projekt): array
    {
        return [
            'cluster' => rescue(
                fn () => $projekt->p2Cluster()->count(),
                0
            ),
            'mapping' => rescue(
                fn () => $projekt->p2MappingSuchstringKomponenten()->count(),
                0
            ),
        ];
    }

    private function countPhase3(Projekt $projekt): array
    {
        return [
            'databases' => rescue(
                fn () => $projekt->p3Datenbankmatrix()->count(),
                0
            ),
        ];
    }

    private function countPhase4(Projekt $projekt): array
    {
        return [
            'searchstrings' => rescue(
                fn () => $projekt->p4Suchstrings()->count(),
                0
            ),
        ];
    }

    private function countPhase5(Projekt $projekt): array
    {
        return [
            'treffer' => rescue(
                fn () => $projekt->p5Treffer()->count(),
                0
            ),
        ];
    }

    private function countPhase6(Projekt $projekt): array
    {
        return [
            'assessments' => rescue(
                fn () => $projekt->p6Qualitaetsbewertungen()->count(),
                0
            ),
        ];
    }

    private function countPhase7(Projekt $projekt): array
    {
        return [
            'extractions' => rescue(
                fn () => DB::table('p7_datenextraktion')
                    ->join('p5_treffer', 'p7_datenextraktion.treffer_id', '=', 'p5_treffer.id')
                    ->where('p5_treffer.projekt_id', $projekt->id)
                    ->count(),
                0
            ),
        ];
    }
}
