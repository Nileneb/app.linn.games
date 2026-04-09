<?php

namespace App\Models\Recherche;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P7MayringSnippet
 * Repräsentiert einen markierten Text-Ausschnitt aus einem Paper
 * mit automatischer Quellenreferenz (Mayring-Analyse).
 */
class P7MayringSnippet extends Model
{
    use HasUuids;

    protected $table = 'p7_mayring_snippets';

    protected $fillable = [
        'projekt_id',
        'paper_id',
        'snippet_text',
        'source_reference',
        'chunk_index',
        'created_by',
        'category',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Relation: Projekt
     */
    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class);
    }

    /**
     * Relation: Paper (P5Treffer)
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(P5Treffer::class, 'paper_id');
    }

    /**
     * Erstellt einen neuen Snippet mit automatischer Quellenreferenz
     */
    public static function createFromSelection(
        string $projektId,
        string $paperId,
        string $snippetText,
        string $sourceReference,
        ?int $chunkIndex = null,
        ?string $createdBy = null,
        ?string $category = null,
    ): self {
        return self::create([
            'projekt_id' => $projektId,
            'paper_id' => $paperId,
            'snippet_text' => trim($snippetText),
            'source_reference' => $sourceReference,
            'chunk_index' => $chunkIndex,
            'created_by' => $createdBy,
            'category' => $category,
        ]);
    }

    /**
     * Formatiert den Snippet mit Markdown-Kommentar für volle Traceability
     */
    public function toMarkdownWithReference(): string
    {
        $markdown = "> {$this->snippet_text}\n";
        $markdown .= "<!-- source: {$this->source_reference}; paper_id: {$this->paper_id}; chunk: {$this->chunk_index} -->\n";

        if ($this->category) {
            $markdown .= "_Kategorie: {$this->category}_\n";
        }

        if ($this->notes) {
            $markdown .= "_Anmerkungen: {$this->notes}_\n";
        }

        return $markdown;
    }

    /**
     * Scope: Alle Snippets für ein Projekt
     */
    public function scopeForProject($query, string $projektId)
    {
        return $query->where('projekt_id', $projektId);
    }

    /**
     * Scope: Alle Snippets für ein Paper
     */
    public function scopeForPaper($query, string $paperId)
    {
        return $query->where('paper_id', $paperId);
    }

    /**
     * Scope: Nach Kategorie filtern
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Gibt alle einzigartigen Kategorien für ein Projekt zurück
     */
    public static function categoriesForProject(string $projektId): array
    {
        return self::where('projekt_id', $projektId)
            ->whereNotNull('category')
            ->distinct('category')
            ->pluck('category')
            ->all();
    }
}
