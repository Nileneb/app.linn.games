<?php

namespace App\Services;

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\File;

class PhaseTemplateService
{
    /**
     * Returns a pre-filled Markdown template for the given phase and project context.
     * For P8, generates the search protocol algorithmically from DB data.
     * For other phases, loads a file template and substitutes project context.
     */
    public function getTemplate(int $phaseNr, Projekt $projekt): string
    {
        return match ($phaseNr) {
            3 => $this->renderFileTemplate(resource_path('templates/phasen/p3-datenbankmatrix.md'), $this->variables($projekt)),
            5 => $this->renderFileTemplate(resource_path('templates/phasen/p5-screening.md'), $this->variables($projekt)),
            7 => $this->renderFileTemplate(resource_path('templates/phasen/p7-synthese.md'), $this->variables($projekt)),
            8 => $this->generateP8Suchprotokoll($projekt),
            default => throw new \InvalidArgumentException("Kein Template für Phase {$phaseNr}"),
        };
    }

    /**
     * Loads a file template and substitutes variables.
     */
    private function renderFileTemplate(string $filePath, array $variables): string
    {
        if (! File::exists($filePath)) {
            throw new \RuntimeException("Template nicht gefunden: {$filePath}");
        }

        $content = File::get($filePath);

        // Simple string replacement for {{key}} placeholders
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }

        return $content;
    }

    /**
     * Generates a search protocol from P1-P5 data.
     */
    private function generateP8Suchprotokoll(Projekt $projekt): string
    {
        $projekt->load([
            'p1Komponenten',
            'p2Cluster',
            'p3Datenbankmatrix',
            'p4Suchstrings',
        ]);

        $lines = [];
        $lines[] = "# Suchprotokoll – {$projekt->titel}";
        $lines[] = "";
        $lines[] = "**Forschungsfrage:** {$projekt->forschungsfrage}";
        $lines[] = "**Review-Typ:** " . ($projekt->review_typ ?? 'nicht festgelegt');
        $lines[] = "**Datum:** " . now()->format('d.m.Y');
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        // Projektkontext
        $lines[] = "## Projektkontext";
        $lines[] = "";
        $lines[] = "| Feld | Wert |";
        $lines[] = "|------|------|";
        $lines[] = "| Forschungsfrage | {$projekt->forschungsfrage} |";
        $lines[] = "| Review-Typ | " . ($projekt->review_typ ?? '—') . " |";
        $lines[] = "| Erstellt | " . ($projekt->erstellt_am?->format('d.m.Y H:i') ?? '—') . " |";
        $lines[] = "";

        // Komponenten
        if ($projekt->p1Komponenten->isNotEmpty()) {
            $lines[] = "## Strukturmodell & Komponenten";
            $lines[] = "";
            $lines[] = "| Kürzel | Label | Modell |";
            $lines[] = "|--------|-------|--------|";
            foreach ($projekt->p1Komponenten as $komp) {
                $lines[] = "| {$komp->komponente_kuerzel} | {$komp->komponente_label} | {$komp->modell} |";
            }
            $lines[] = "";
        }

        // Datenbankmatrix
        if ($projekt->p3Datenbankmatrix->isNotEmpty()) {
            $lines[] = "## Ausgewählte Datenbanken";
            $lines[] = "";
            $lines[] = "| Datenbank | Disziplin | Empfohlen |";
            $lines[] = "|-----------|-----------|-----------|";
            foreach ($projekt->p3Datenbankmatrix as $db) {
                $recommended = $db->empfohlen ? 'Ja' : 'Nein';
                $lines[] = "| {$db->datenbank_name} | {$db->disziplin} | {$recommended} |";
            }
            $lines[] = "";
        }

        // Suchstrings nach Datenbank
        if ($projekt->p4Suchstrings->isNotEmpty()) {
            $lines[] = "## Suchstrings nach Datenbank";
            $lines[] = "";

            $stringsByDb = $projekt->p4Suchstrings->groupBy('datenbank');
            foreach ($stringsByDb as $db => $strings) {
                $lines[] = "### {$db}";
                $lines[] = "";
                foreach ($strings as $string) {
                    $lines[] = "- **{$string->suchstring_typ ?? 'Suchstring'}**: `{$string->suchstring_text}`";
                    if ($string->treffer_anzahl) {
                        $lines[] = "  - Treffer: " . number_format($string->treffer_anzahl);
                    }
                }
                $lines[] = "";
            }
        }

        // Treffer-Übersicht
        $trefferCount = rescue(fn () => $projekt->p5Treffer()->count(), 0);
        $lines[] = "## Treffer-Übersicht";
        $lines[] = "";
        $lines[] = "- **Treffer gesamt:** " . number_format($trefferCount);
        $lines[] = "- **Datum Export:** " . now()->format('d.m.Y H:i');
        $lines[] = "";

        return implode("\n", $lines);
    }

    /**
     * Returns context variables for template substitution.
     */
    private function variables(Projekt $projekt): array
    {
        return [
            'forschungsfrage' => $projekt->forschungsfrage ?? 'nicht festgelegt',
            'review_typ' => $projekt->review_typ ?? 'nicht festgelegt',
            'datum' => now()->format('d.m.Y'),
            'projekt_titel' => $projekt->titel,
        ];
    }
}
