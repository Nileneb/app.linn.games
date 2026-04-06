<?php

namespace App\Services;

use App\Models\Recherche\P7MayringSnippet;
use App\Models\Recherche\P5Treffer;
use Illuminate\Support\Facades\Auth;

/**
 * MayringSnippetService
 * Verwaltet Snippet-Extraktion und Quellenreferenz-Generierung für Mayring-Analyse.
 */
class MayringSnippetService
{
    /**
     * Extrahiert einen Snippet mit automatischer Quellenreferenz
     */
    public function createSnippet(
        string $projektId,
        string $paperId,
        string $snippetText,
        ?int $chunkIndex = null,
        ?string $category = null,
        ?string $notes = null,
    ): P7MayringSnippet {
        // Hole das Paper um Metadaten für die Quellenreferenz zu bekommen
        $paper = P5Treffer::findOrFail($paperId);

        // Generiere automatische Quellenreferenz
        $sourceReference = $this->generateSourceReference($paper, $chunkIndex);

        return P7MayringSnippet::create([
            'projekt_id' => $projektId,
            'paper_id' => $paperId,
            'snippet_text' => trim($snippetText),
            'source_reference' => $sourceReference,
            'chunk_index' => $chunkIndex,
            'created_by' => Auth::id(),
            'category' => $category,
            'notes' => $notes,
        ]);
    }

    /**
     * Generiert eine Quellenreferenz basierend auf Paper-Metadaten
     * Format: "Autor Jahr, S. XX" (wenn vorhanden, sonst Fallback)
     */
    public function generateSourceReference(P5Treffer $paper, ?int $chunkIndex = null): string
    {
        $authors = trim($paper->authors ?? '');
        $year = $paper->year ?? 'n.d.';
        $title = $paper->title ?? 'Untitled';

        // Extrahiere Erstautor
        if ($authors) {
            $authorParts = explode(';', $authors);
            $firstAuthor = trim($authorParts[0]);

            // Falls mehrere Autoren, nutze "et al."
            if (count($authorParts) > 1) {
                $firstAuthor .= ' et al.';
            }
        } else {
            $firstAuthor = 'Unknown';
        }

        // Basis-Referenz: "Author(s) Year"
        $reference = "{$firstAuthor} {$year}";

        // Wenn Chunk-Index vorhanden, füge Seiten/Absatz-Info hinzu
        if ($chunkIndex !== null) {
            $reference .= ", p. {$chunkIndex}";
        }

        return $reference;
    }

    /**
     * Gibt alle Snippets für ein Projekt gruppiert nach Kategorie zurück
     */
    public function groupedByCategory(string $projektId): array
    {
        $snippets = P7MayringSnippet::where('projekt_id', $projektId)
            ->with('paper')
            ->orderBy('category')
            ->get();

        return $snippets->groupBy(fn ($s) => $s->category ?? 'Uncategorized')->toArray();
    }

    /**
     * Exportiert Snippets als strukturiertes Markdown
     */
    public function exportAsMarkdown(string $projektId): string
    {
        $snippets = $this->groupedByCategory($projektId);

        $lines = ['# Mayring-Analyse: Extrahierte Snippets', '', ''];

        foreach ($snippets as $category => $items) {
            $lines[] = "## {$category}";
            $lines[] = '';

            foreach ($items as $snippet) {
                $lines[] = $snippet->toMarkdownWithReference();
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Löscht einen Snippet
     */
    public function delete(string $snippetId): bool
    {
        $snippet = P7MayringSnippet::findOrFail($snippetId);
        return (bool) $snippet->delete();
    }

    /**
     * Aktualisiert Kategorie oder Anmerkungen
     */
    public function update(string $snippetId, array $data): P7MayringSnippet
    {
        $snippet = P7MayringSnippet::findOrFail($snippetId);
        $snippet->update($data);

        return $snippet;
    }

    /**
     * Gibt Statistiken für die Mayring-Analyse zurück
     */
    public function getStats(string $projektId): array
    {
        $snippets = P7MayringSnippet::where('projekt_id', $projektId)->get();

        return [
            'total_snippets' => $snippets->count(),
            'categories' => $snippets->groupBy('category')->count(),
            'papers_referenced' => $snippets->pluck('paper_id')->unique()->count(),
            'by_category' => $snippets->groupBy('category')->map->count()->toArray(),
        ];
    }
}
