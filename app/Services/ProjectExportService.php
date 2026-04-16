<?php

namespace App\Services;

use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\P6Qualitaetsbewertung;
use App\Models\Recherche\P7Datenextraktion;
use App\Models\Recherche\P7GradeEinschaetzung;
use App\Models\Recherche\P8Limitation;
use App\Models\Recherche\P8Reproduzierbarkeitspruefung;
use App\Models\Recherche\P8Suchprotokoll;
use App\Models\Recherche\P8UpdatePlan;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Str;

class ProjectExportService
{
    public function generateMarkdown(Projekt $projekt): string
    {
        $lines = [
            '# '.($projekt->titel ?? 'Systematisches Literaturreview'),
            '',
            '**Forschungsfrage:** '.$projekt->forschungsfrage,
            '**Review-Typ:** '.($projekt->review_typ ?? 'N/A'),
            '**Projekt-ID:** '.$projekt->id,
            '**Erstellt:** '.$projekt->created_at?->format('d.m.Y H:i'),
            '**Aktualisiert:** '.$projekt->updated_at?->format('d.m.Y H:i'),
            '',
            '---',
            '',
        ];

        $lines[] = $this->generateP1Section($projekt);
        $lines[] = $this->generateP2Section($projekt);
        $lines[] = $this->generateP3Section($projekt);
        $lines[] = $this->generateP4Section($projekt);
        $lines[] = $this->generateP5Section($projekt);
        $lines[] = $this->generateP6Section($projekt);
        $lines[] = $this->generateP7Section($projekt);
        $lines[] = $this->generateP8Section($projekt);

        return implode("\n", array_filter($lines));
    }

    private function generateP1Section(Projekt $projekt): string
    {
        $komponenten = $projekt->p1Komponenten()->get();
        $lines = ['## Phase 1: Komponenten & Strukturmodell'];

        if ($komponenten->isEmpty()) {
            $lines[] = '_Keine Einträge_';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '';
        $lines[] = '| Kürzel | Komponente | Synonyme | MeSH | Englisch |';
        $lines[] = '|--------|-----------|----------|------|----------|';
        foreach ($komponenten as $k) {
            $synonyme = is_array($k->synonyme) ? implode(', ', $k->synonyme) : ($k->synonyme ?? '');
            $lines[] = "| {$k->komponente_kuerzel} | {$k->komponente_label} | {$synonyme} | {$k->mesh_term} | {$k->englische_entsprechung} |";
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP2Section(Projekt $projekt): string
    {
        $cluster = $projekt->p2Cluster()->get();
        $mappings = $projekt->p2Trefferlisten()->get();
        $lines = ['## Phase 2: Cluster & Mapping'];

        if ($cluster->isEmpty() && $mappings->isEmpty()) {
            $lines[] = '_Keine Einträge_';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '';
        if ($cluster->isNotEmpty()) {
            $lines[] = '### Cluster';
            foreach ($cluster as $c) {
                $lines[] = "- **{$c->cluster_label}** (ID: {$c->cluster_id})";
                if ($c->beschreibung) {
                    $lines[] = "  {$c->beschreibung}";
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

            return implode("\n", $lines)."\n";
        }

        $lines[] = '';
        $lines[] = '| Datenbank | Disziplin | Zugang | Empfohlen |';
        $lines[] = '|-----------|-----------|--------|-----------|';
        foreach ($datenbanken as $db) {
            $empfohlen = $db->empfohlen ? 'Ja' : 'Nein';
            $lines[] = "| {$db->datenbank} | {$db->disziplin} | {$db->zugang} | {$empfohlen} |";
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

            return implode("\n", $lines)."\n";
        }

        $lines[] = '';
        foreach ($suchstrings as $s) {
            $lines[] = "### {$s->datenbank}";
            $lines[] = "```";
            $lines[] = $s->suchstring;
            $lines[] = "```";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function generateP5Section(Projekt $projekt): string
    {
        $treffer = P5Treffer::where('projekt_id', $projekt->id)->get();
        $lines = ['## Phase 5: Screening & Treffer'];

        if ($treffer->isEmpty()) {
            $lines[] = '_Keine Treffer_';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '';
        $lines[] = "**{$treffer->count()} Treffer** aus ".
            $treffer->pluck('datenbank_quelle')->unique()->implode(', ');
        $lines[] = '';
        $lines[] = '| # | Titel | Autoren | Jahr | DOI | Quelle |';
        $lines[] = '|---|-------|---------|------|-----|--------|';
        foreach ($treffer as $i => $t) {
            $titel = mb_substr($t->titel ?? '', 0, 80);
            $autoren = mb_substr($t->autoren ?? '', 0, 40);
            $doi = $t->doi ? "[{$t->doi}](https://doi.org/{$t->doi})" : '';
            $lines[] = "| ".($i + 1)." | {$titel} | {$autoren} | {$t->jahr} | {$doi} | {$t->datenbank_quelle} |";
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP6Section(Projekt $projekt): string
    {
        $bewertungen = P6Qualitaetsbewertung::whereHas('treffer', fn ($q) => $q->where('projekt_id', $projekt->id))->with('treffer')->get();
        $lines = ['## Phase 6: Qualitätsbewertung'];

        if ($bewertungen->isEmpty()) {
            $lines[] = '_Keine Bewertungen_';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '';
        $lines[] = '| Studie | Studientyp | RoB-Tool | Urteil | Im Review |';
        $lines[] = '|--------|-----------|----------|--------|-----------|';
        foreach ($bewertungen as $b) {
            $titel = mb_substr($b->treffer?->titel ?? '', 0, 50);
            $imReview = $b->im_review_behalten ? 'Ja' : 'Nein';
            $lines[] = "| {$titel} | {$b->studientyp} | {$b->rob_tool} | {$b->gesamturteil} | {$imReview} |";
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generateP7Section(Projekt $projekt): string
    {
        $extraktionen = P7Datenextraktion::whereHas('treffer', fn ($q) => $q->where('projekt_id', $projekt->id))->with('treffer')->get();
        $grade = P7GradeEinschaetzung::where('projekt_id', $projekt->id)->get();
        $lines = ['## Phase 7: Datenextraktion & Synthese'];

        if ($extraktionen->isEmpty() && $grade->isEmpty()) {
            $lines[] = '_Keine Daten_';

            return implode("\n", $lines)."\n";
        }

        if ($extraktionen->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '### Datenextraktion';
            $lines[] = '| Studie | Land | Intervention | Hauptbefund |';
            $lines[] = '|--------|------|-------------|-------------|';
            foreach ($extraktionen as $e) {
                $titel = mb_substr($e->treffer?->titel ?? '', 0, 40);
                $lines[] = "| {$titel} | {$e->land} | {$e->phaenomen_intervention} | {$e->hauptbefund} |";
            }
            $lines[] = '';
        }

        if ($grade->isNotEmpty()) {
            $lines[] = '### GRADE-Einschätzung';
            $lines[] = '| Outcome | Studien | RoB | GRADE-Urteil |';
            $lines[] = '|---------|---------|-----|-------------|';
            foreach ($grade as $g) {
                $lines[] = "| {$g->outcome} | {$g->studienanzahl} | {$g->rob_gesamt} | {$g->grade_urteil} |";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function generateP8Section(Projekt $projekt): string
    {
        $protokoll = P8Suchprotokoll::where('projekt_id', $projekt->id)->get();
        $limitationen = P8Limitation::where('projekt_id', $projekt->id)->get();
        $lines = ['## Phase 8: Suchprotokoll & Final Report'];

        if ($protokoll->isEmpty() && $limitationen->isEmpty()) {
            $lines[] = '_Noch keine Dokumentation_';

            return implode("\n", $lines)."\n";
        }

        if ($protokoll->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '### Suchprotokoll';
            $lines[] = '| Datenbank | Suchdatum | Treffer | Suchstring |';
            $lines[] = '|-----------|-----------|---------|------------|';
            foreach ($protokoll as $sp) {
                $datum = $sp->suchdatum?->format('d.m.Y') ?? '';
                $suchstring = mb_substr($sp->suchstring_final ?? '', 0, 60);
                $lines[] = "| {$sp->datenbank} | {$datum} | {$sp->treffer_gesamt} | {$suchstring}... |";
            }
            $lines[] = '';
        }

        if ($limitationen->isNotEmpty()) {
            $lines[] = '### Limitationen';
            foreach ($limitationen as $lim) {
                $lines[] = "- **{$lim->limitationstyp}:** {$lim->beschreibung}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function generateLaTeX(Projekt $projekt, ?string $markdown = null): string
    {
        $md = $markdown ?? $this->generateMarkdown($projekt);
        $latex = $md;

        $latex = preg_replace('/^# (.+)$/m', '\\section*{$1}', $latex);
        $latex = preg_replace('/^## (.+)$/m', '\\subsection*{$1}', $latex);
        $latex = preg_replace('/^### (.+)$/m', '\\subsubsection*{$1}', $latex);
        $latex = preg_replace('/\*\*(.+?)\*\*/m', '\\textbf{$1}', $latex);
        $latex = preg_replace('/\*(.+?)\*/m', '\\textit{$1}', $latex);
        $latex = preg_replace('/```(.+?)```/s', '\\begin{verbatim}$1\\end{verbatim}', $latex);
        $latex = preg_replace('/\[(.+?)\]\((.+?)\)/m', '\\href{$2}{$1}', $latex);
        $latex = str_replace('---', '\\hrule', $latex);

        $texContent = <<<'TEX'
\documentclass[12pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[ngerman]{babel}
\usepackage{hyperref}
\usepackage{booktabs}
\usepackage{longtable}

\title{PROJEKT_TITEL}
\author{Automatisch generiert via app.linn.games}
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
}
