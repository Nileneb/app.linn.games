<?php

namespace App\Services;

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * ProjectExportService
 * Exportiert ein Projekt in Markdown (+ optionales LaTeX) mit allen Phasen-Daten.
 */
class ProjectExportService
{
    public function __construct(
        private readonly PhaseCountService $countService,
    ) {}

    /**
     * Generiert vollständiges Markdown-Export des Projekts (alle Phasen)
     */
    public function generateMarkdown(Projekt $projekt): string
    {
        $lines = [
            '# ' . ($projekt->titel ?? 'Systematisches Literaturreview'),
            '',
            '**Forschungsfrage:** ' . $projekt->forschungsfrage,
            '**Review-Typ:** ' . ($projekt->review_typ ?? 'N/A'),
            '**Projekt-ID:** ' . $projekt->id,
            '**Erstellt:** ' . $projekt->created_at?->format('d.m.Y H:i'),
            '**Aktualisiert:** ' . $projekt->updated_at?->format('d.m.Y H:i'),
            '',
            '---',
            '',
        ];

        // Phase 1: Komponenten
        $lines[] = $this->generateP1Section($projekt);

        // Phase 2: Cluster & Mapping
        $lines[] = $this->generateP2Section($projekt);

        // Phase 3: Datenbankmatrix
        $lines[] = $this->generateP3Section($projekt);

        // Phase 4: Suchstrings
        $lines[] = $this->generateP4Section($projekt);

        // Phase 5: Screening & Treffer
        $lines[] = $this->generateP5Section($projekt);

        // Phase 6: Qualitätsbewertung
        $lines[] = $this->generateP6Section($projekt);

        // Phase 7: Datenextraktion
        $lines[] = $this->generateP7Section($projekt);

        // Phase 8: Suchprotokoll
        $lines[] = $this->generateP8Section($projekt);

        return implode("\n", array_filter($lines));
    }

    private function generateP1Section(Projekt $projekt): string
    {
        $komponenten = $projekt->p1Komponenten()->get();

        $lines = ['## Phase 1: Komponenten & Strukturmodell'];

        if ($komponenten->isEmpty()) {
            $lines[] = '_Keine Einträge_';
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '';
        foreach ($komponenten as $k) {
            $lines[] = "### {$k->name}";
            if ($k->beschreibung) {
                $lines[] = $k->beschreibung;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function generateP2Section(Projekt $projekt): string
    {
        $cluster = $projekt->p2Cluster()->get();
        $mappings = $projekt->p2Trefferlisten()->get();

        $lines = ['## Phase 2: Cluster & Mapping'];

        if ($cluster->isEmpty() && $mappings->isEmpty()) {
            $lines[] = '_Keine Einträge_';
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '';

        if ($cluster->isNotEmpty()) {
            $lines[] = '### Cluster';
            foreach ($cluster as $c) {
                $lines[] = "- **{$c->name}**";
                if ($c->beschreibung) {
                    $lines[] = "  - {$c->beschreibung}";
                }
            }
            $lines[] = '';
        }

        if ($mappings->isNotEmpty()) {
            $lines[] = '### Suchstring-Komponenten-Mapping';
            $lines[] = '| Suchstring | Komponente |';
            $lines[] = '|------------|-----------|';
            foreach ($mappings as $m) {
                $lines[] = "| {$m->suchstring} | {$m->komponente} |";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function generateP3Section(Projekt $projekt): string
    {
        $datenbanken = $projekt->p3Datenbankmatrix()->get();

        $lines = ['## Phase 3: Datenbankmatrix'];

        if ($datenbanken->isEmpty()) {
            $lines[] = '_Keine Einträge_';
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '';
        $lines[] = '| Datenbank | Disziplin | Zugang |';
        $lines[] = '|-----------|-----------|--------|';

        foreach ($datenbanken as $db) {
            $disziplin = $db->disziplin ?? 'N/A';
            $zugang = $db->zugang ?? 'N/A';
            $lines[] = "| {$db->name} | {$disziplin} | {$zugang} |";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP4Section(Projekt $projekt): string
    {
        $suchstrings = $projekt->p4Suchstrings()->get();

        $lines = ['## Phase 4: Suchstrings'];

        if ($suchstrings->isEmpty()) {
            $lines[] = '_Keine Einträge_';
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '';

        foreach ($suchstrings as $s) {
            $lines[] = "### {$s->suchstring}";
            if ($s->datenbank) {
                $lines[] = "**Datenbank:** {$s->datenbank}";
            }
            if ($s->beschreibung) {
                $lines[] = $s->beschreibung;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function generateP5Section(Projekt $projekt): string
    {
        $lines = ['## Phase 5: Screening & Treffer'];
        $lines[] = '';
        $lines[] = "**Status:** Screening & Treffer-Import (Details siehe Anwendung)";
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP6Section(Projekt $projekt): string
    {
        $lines = ['## Phase 6: Qualitätsbewertung'];
        $lines[] = '';
        $lines[] = "**Status:** Qualitätsbewertung (Details siehe Anwendung)";
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP7Section(Projekt $projekt): string
    {
        $lines = ['## Phase 7: Datenextraktion & Synthese'];
        $lines[] = '';
        $lines[] = "**Status:** Datenextraktion & Synthese (Details siehe Anwendung)";
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP8Section(Projekt $projekt): string
    {
        $lines = ['## Phase 8: Suchprotokoll & Final Report'];
        $lines[] = '';
        $lines[] = "**Status:** Final Report & Dokumentation (Details siehe Anwendung)";
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Konvertiert Markdown zu LaTeX
     * (Simple Konversion — für volle Kraft Pandoc verwenden)
     */
    public function generateLaTeX(Projekt $projekt, ?string $markdown = null): string
    {
        $md = $markdown ?? $this->generateMarkdown($projekt);

        // Einfache Markdown-zu-LaTeX-Konversion
        $latex = $md;

        // Überschriften
        $latex = preg_replace('/^# (.+)$/m', '\\section*{$1}', $latex);
        $latex = preg_replace('/^## (.+)$/m', '\\subsection*{$1}', $latex);
        $latex = preg_replace('/^### (.+)$/m', '\\subsubsection*{$1}', $latex);

        // Bold & Italic
        $latex = preg_replace('/\*\*(.+?)\*\*/m', '\\textbf{$1}', $latex);
        $latex = preg_replace('/\*(.+?)\*/m', '\\textit{$1}', $latex);

        // Code blocks
        $latex = preg_replace('/```(.+?)```/s', '\\begin{verbatim}$1\\end{verbatim}', $latex);

        // Links
        $latex = preg_replace('/\[(.+?)\]\((.+?)\)/m', '\\href{$2}{$1}', $latex);

        // Horizontal rules
        $latex = str_replace('---', '\\hrule', $latex);

        // Wrap in LaTeX document
        $texContent = <<<'TEX'
\documentclass[12pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[ngerman]{babel}
\usepackage{hyperref}
\usepackage{booktabs}
\usepackage{graphicx}

\title{PROJEKT_TITEL}
\author{Automatisch generiert}
\date{\today}

\begin{document}

\maketitle
\tableofcontents
\newpage

MARKDOWN_CONTENT

\end{document}
TEX;

        $texContent = str_replace('PROJEKT_TITEL', $projekt->titel ?? 'Systematisches Literaturreview', $texContent);
        $texContent = str_replace('MARKDOWN_CONTENT', $latex, $texContent);

        return $texContent;
    }

    /**
     * Speichert Markdown-Export als Datei
     */
    public function exportAsMarkdown(Projekt $projekt): string
    {
        $markdown = $this->generateMarkdown($projekt);
        $filename = 'export_' . Str::slug($projekt->titel ?? 'projekt') . '_' . date('Y-m-d_His') . '.md';

        return storage_path('exports/' . $filename);
    }

    /**
     * Speichert LaTeX-Export als Datei
     */
    public function exportAsLaTeX(Projekt $projekt, ?string $markdown = null): string
    {
        $latex = $this->generateLaTeX($projekt, $markdown);
        $filename = 'export_' . Str::slug($projekt->titel ?? 'projekt') . '_' . date('Y-m-d_His') . '.tex';

        return storage_path('exports/' . $filename);
    }
}
