<?php

namespace App\Services;

use App\Models\Recherche\P1Komponente;
use App\Models\Recherche\P2Cluster;
use App\Models\Recherche\P2Trefferliste;
use App\Models\Recherche\P3Datenbankmatrix;
use App\Models\Recherche\P4Suchstring;
use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\P6Qualitaetsbewertung;
use App\Models\Recherche\P7Datenextraktion;
use App\Models\Recherche\Projekt;

/**
 * PhaseCountService
 * Zählt die Anzahl der Einträge pro Phase für Transition-Validierung.
 */
class PhaseCountService
{
    /**
     * Zählt P1-Komponenten
     */
    public function countP1Komponenten(Projekt $projekt): int
    {
        return P1Komponente::where('projekt_id', $projekt->id)->count();
    }

    /**
     * Zählt P2-Cluster
     */
    public function countP2Cluster(Projekt $projekt): int
    {
        return P2Cluster::where('projekt_id', $projekt->id)->count();
    }

    /**
     * Zählt P2-Mappings (Trefferliste)
     */
    public function countP2Mappings(Projekt $projekt): int
    {
        return P2Trefferliste::where('projekt_id', $projekt->id)->count();
    }

    /**
     * Zählt P3-Datenbanken
     */
    public function countP3Datenbanken(Projekt $projekt): int
    {
        return P3Datenbankmatrix::where('projekt_id', $projekt->id)->count();
    }

    /**
     * Zählt P4-Suchstrings
     */
    public function countP4Suchstrings(Projekt $projekt): int
    {
        return P4Suchstring::where('projekt_id', $projekt->id)->count();
    }

    /**
     * Zählt P5-Treffer
     */
    public function countP5Treffer(Projekt $projekt): int
    {
        return P5Treffer::where('projekt_id', $projekt->id)->count();
    }

    /**
     * Zählt P6-Qualitätsbewertungen (über p5_treffer.projekt_id)
     */
    public function countP6Bewertungen(Projekt $projekt): int
    {
        return P6Qualitaetsbewertung::whereHas('treffer', fn ($q) => $q->where('projekt_id', $projekt->id))->count();
    }

    /**
     * Zählt P7-Datenextraktionen (über p5_treffer.projekt_id)
     */
    public function countP7Extraktionen(Projekt $projekt): int
    {
        return P7Datenextraktion::whereHas('treffer', fn ($q) => $q->where('projekt_id', $projekt->id))->count();
    }

    /**
     * Universelle Zählfunktion basierend auf Phase
     */
    public function countByPhase(Projekt $projekt, int $phase): int
    {
        return match ($phase) {
            1 => $this->countP1Komponenten($projekt),
            2 => $this->countP2Cluster($projekt) + $this->countP2Mappings($projekt),
            3 => $this->countP3Datenbanken($projekt),
            4 => $this->countP4Suchstrings($projekt),
            5 => $this->countP5Treffer($projekt),
            6 => $this->countP6Bewertungen($projekt),
            7 => $this->countP7Extraktionen($projekt),
            default => 0,
        };
    }

    /**
     * Gibt detaillierte Counts für alle Phasen zurück
     */
    public function getAllCounts(Projekt $projekt): array
    {
        return [
            1 => [
                'komponenten' => $this->countP1Komponenten($projekt),
            ],
            2 => [
                'cluster' => $this->countP2Cluster($projekt),
                'mappings' => $this->countP2Mappings($projekt),
                'total' => $this->countP2Cluster($projekt) + $this->countP2Mappings($projekt),
            ],
            3 => [
                'datenbanken' => $this->countP3Datenbanken($projekt),
            ],
            4 => [
                'suchstrings' => $this->countP4Suchstrings($projekt),
            ],
            5 => [
                'treffer' => $this->countP5Treffer($projekt),
            ],
            6 => [
                'bewertungen' => $this->countP6Bewertungen($projekt),
            ],
            7 => [
                'extraktionen' => $this->countP7Extraktionen($projekt),
            ],
        ];
    }
}
