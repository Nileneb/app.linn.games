<?php

use App\Models\Recherche\P5Treffer;
use App\Services\MayringSnippetService;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

new class extends Component {
    public string $paperId = '';
    public string $projektId = '';
    public string $selectedText = '';
    public string $category = '';
    public string $notes = '';
    public bool $showForm = false;
    public array $allSnippets = [];
    public array $categories = [];
    public ?P5Treffer $paper = null;

    public function mount(string $paperId, string $projektId): void
    {
        $this->paperId = $paperId;
        $this->projektId = $projektId;
        $this->paper = P5Treffer::findOrFail($paperId);
        $this->loadSnippets();
    }

    public function onTextSelected(string $text): void
    {
        $this->selectedText = $text;
        $this->showForm = true;
    }

    public function saveSnippet(): void
    {
        $this->validate([
            'selectedText' => 'required|string|min:10',
            'category' => 'nullable|string|max:100',
        ]);

        $service = app(MayringSnippetService::class);
        $service->createSnippet(
            $this->projektId,
            $this->paperId,
            $this->selectedText,
            category: $this->category ?: null,
            notes: $this->notes ?: null,
        );

        $this->resetForm();
        $this->loadSnippets();
    }

    public function deleteSnippet(string $snippetId): void
    {
        app(MayringSnippetService::class)->delete($snippetId);
        $this->loadSnippets();
    }

    public function loadSnippets(): void
    {
        $service = app(MayringSnippetService::class);
        $this->allSnippets = $service->groupedByCategory($this->projektId);
        $this->categories = array_keys($this->allSnippets);
    }

    public function resetForm(): void
    {
        $this->selectedText = '';
        $this->category = '';
        $this->notes = '';
        $this->showForm = false;
    }

    public function downloadAsMarkdown()
    {
        $service = app(MayringSnippetService::class);
        $markdown = $service->exportAsMarkdown($this->projektId);

        return response()->streamDownload(
            function () use ($markdown) { echo $markdown; },
            'mayring-analysis-' . now()->format('Y-m-d-His') . '.md',
            ['Content-Type' => 'text/markdown; charset=UTF-8']
        );
    }
}; ?>

<div class="mayring-container">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lora:wght@400;500&display=swap');

        .mayring-container {
            --navy: #1a2340;
            --gold: #d4a574;
            --cream: #f5f3f0;
            --success: #2d5016;
        }

        .mayring-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .mayring-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .mayring-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0.75rem;
            letter-spacing: 0.5px;
        }

        .mayring-subtitle {
            font-family: 'Lora', serif;
            font-size: 1rem;
            color: var(--navy);
            opacity: 0.65;
            font-weight: 400;
        }

        .mayring-guide {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.1) 0%, rgba(45, 80, 22, 0.05) 100%);
            border-left: 3px solid var(--gold);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 1px;
            font-family: 'Lora', serif;
            font-size: 0.95rem;
            color: var(--navy);
            line-height: 1.6;
        }

        .mayring-guide strong {
            color: var(--navy);
            font-weight: 600;
        }

        .mayring-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .mayring-content {
                grid-template-columns: 1fr;
            }
        }

        .mayring-paper-section {
            background: white;
            border: 1px solid rgba(212, 165, 116, 0.2);
            border-radius: 2px;
            padding: 2rem;
            position: relative;
        }

        .mayring-paper-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            color: var(--navy);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(212, 165, 116, 0.2);
        }

        .mayring-paper-content {
            font-family: 'Lora', serif;
            font-size: 0.95rem;
            line-height: 1.8;
            color: var(--navy);
            user-select: text;
            cursor: text;
            opacity: 0.85;
        }

        .mayring-paper-content::selection {
            background: rgba(212, 165, 116, 0.3);
            color: var(--navy);
        }

        .mayring-form {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.08) 0%, rgba(26, 35, 64, 0.03) 100%);
            border: 1px solid rgba(212, 165, 116, 0.25);
            padding: 1.75rem;
            border-radius: 2px;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .mayring-form.hidden {
            display: none;
        }

        .mayring-form-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: var(--navy);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .mayring-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .mayring-form-label {
            font-family: 'Lora', serif;
            font-size: 0.85rem;
            color: var(--navy);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mayring-textarea, .mayring-input {
            font-family: 'Lora', serif;
            padding: 0.875rem;
            border: 1px solid rgba(212, 165, 116, 0.3);
            background: white;
            color: var(--navy);
            font-size: 0.9rem;
            border-radius: 1px;
            resize: vertical;
            transition: all 0.3s ease;
        }

        .mayring-textarea:focus, .mayring-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }

        .mayring-textarea {
            min-height: 80px;
        }

        .mayring-form-actions {
            display: flex;
            gap: 0.75rem;
            padding-top: 0.5rem;
        }

        .mayring-btn {
            font-family: 'Lora', serif;
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--navy);
            background: white;
            color: var(--navy);
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 1px;
            letter-spacing: 0.3px;
            flex: 1;
        }

        .mayring-btn:hover {
            background: var(--navy);
            color: white;
        }

        .mayring-btn-secondary {
            border-color: rgba(212, 165, 116, 0.4);
            color: var(--navy);
        }

        .mayring-btn-secondary:hover {
            background: rgba(212, 165, 116, 0.1);
        }

        .mayring-snippets-section {
            grid-column: 1 / -1;
            padding-top: 2rem;
            border-top: 2px solid rgba(212, 165, 116, 0.15);
        }

        .mayring-snippets-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }

        .mayring-snippets-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--navy);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .mayring-download-btn {
            font-family: 'Lora', serif;
            padding: 0.75rem 1.25rem;
            background: var(--navy);
            color: white;
            border: 1px solid var(--navy);
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            border-radius: 1px;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
        }

        .mayring-download-btn:hover {
            opacity: 0.85;
        }

        .mayring-snippets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .mayring-snippet-card {
            background: white;
            border: 1px solid rgba(212, 165, 116, 0.2);
            padding: 1.5rem;
            border-radius: 2px;
            position: relative;
            transition: all 0.3s ease;
        }

        .mayring-snippet-card:hover {
            box-shadow: 0 8px 20px rgba(26, 35, 64, 0.08);
            border-color: rgba(212, 165, 116, 0.4);
        }

        .mayring-snippet-category {
            display: inline-block;
            background: rgba(212, 165, 116, 0.15);
            color: var(--navy);
            padding: 0.4rem 0.85rem;
            border-radius: 20px;
            font-family: 'Lora', serif;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .mayring-snippet-text {
            font-family: 'Lora', serif;
            font-size: 0.9rem;
            line-height: 1.7;
            color: var(--navy);
            margin-bottom: 1rem;
            font-style: italic;
            opacity: 0.85;
        }

        .mayring-snippet-source {
            font-family: 'Lora', serif;
            font-size: 0.8rem;
            color: var(--gold);
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .mayring-snippet-delete {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: rgba(139, 46, 46, 0.6);
            cursor: pointer;
            font-size: 1.25rem;
            transition: color 0.3s ease;
        }

        .mayring-snippet-delete:hover {
            color: rgba(139, 46, 46, 1);
        }

        .mayring-empty {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--navy);
            opacity: 0.6;
        }

        .mayring-empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.4;
        }

        .mayring-empty-text {
            font-family: 'Lora', serif;
            font-size: 1rem;
        }
    </style>

    <div class="mayring-wrapper">
        <div class="mayring-header">
            <h2 class="mayring-title">Mayring-Analyse</h2>
            <p class="mayring-subtitle">Strukturierte Textanalyse mit Quellentraceability</p>
        </div>

        <div class="mayring-guide">
            <strong>So funktioniert's:</strong> Markiere Textausschnitte im Paper unten. Diese werden automatisch mit der Quellenreferenz verlinkt. Füge optional eine Kategorie hinzu, um Daten später zu gruppieren.
        </div>

        <div class="mayring-content">
            <!-- Paper Content -->
            <div class="mayring-paper-section">
                @if ($paper && $paper->abstract)
                    <h3 class="mayring-paper-title">{{ Str::limit($paper->title ?? 'Paper', 50) }}</h3>
                    <div class="mayring-paper-content" wire:mouseup="onTextSelected(window.getSelection().toString())">
                        {{ $paper->abstract }}
                    </div>
                @else
                    <p style="color: var(--navy); opacity: 0.5; font-family: Lora, serif;">Keine Inhalte verfügbar</p>
                @endif
            </div>

            <!-- Quick Form (Sidebar) -->
            @if ($showForm && $selectedText)
                <div class="mayring-form">
                    <div class="mayring-form-title">Snippet erfassen</div>

                    <div class="mayring-form-group">
                        <label class="mayring-form-label">Ausgewählter Text</label>
                        <textarea class="mayring-textarea" readonly>{{ $selectedText }}</textarea>
                    </div>

                    <div class="mayring-form-group">
                        <label class="mayring-form-label">Kategorie (optional)</label>
                        <input
                            type="text"
                            wire:model="category"
                            class="mayring-input"
                            placeholder="z.B. Methode, Ergebnis"
                        />
                    </div>

                    <div class="mayring-form-group">
                        <label class="mayring-form-label">Anmerkungen (optional)</label>
                        <textarea
                            wire:model="notes"
                            class="mayring-textarea"
                            style="min-height: 60px;"
                            placeholder="Persönliche Notizen..."
                        ></textarea>
                    </div>

                    <div class="mayring-form-actions">
                        <button wire:click="saveSnippet" class="mayring-btn">
                            Speichern
                        </button>
                        <button wire:click="resetForm" class="mayring-btn mayring-btn-secondary">
                            Abbrechen
                        </button>
                    </div>
                </div>
            @else
                <div style="padding: 2rem; text-align: center; color: var(--navy); opacity: 0.5; font-family: Lora, serif;">
                    ↓ Text oben markieren
                </div>
            @endif
        </div>

        <!-- Extracted Snippets -->
        <div class="mayring-snippets-section">
            <div class="mayring-snippets-header">
                <h3 class="mayring-snippets-title">
                    Extrahierte Snippets
                    @if (!empty($allSnippets))
                        <span style="font-size: 0.6em; opacity: 0.6;">({{ count($allSnippets) }})</span>
                    @endif
                </h3>
                @if (!empty($allSnippets))
                    <button wire:click="downloadAsMarkdown" class="mayring-download-btn">
                        ↓ Als Markdown
                    </button>
                @endif
            </div>

            @if (empty($allSnippets))
                <div class="mayring-empty">
                    <div class="mayring-empty-icon">∅</div>
                    <p class="mayring-empty-text">Noch keine Snippets extrahiert</p>
                </div>
            @else
                @foreach ($allSnippets as $category => $snippets)
                    <div style="margin-bottom: 2rem;">
                        @if ($category !== 'Uncategorized')
                            <h4 style="font-family: Playfair Display, serif; font-size: 1.1rem; color: var(--navy); margin-bottom: 1rem; font-weight: 700;">
                                {{ $category }}
                            </h4>
                        @endif
                        <div class="mayring-snippets-grid">
                            @foreach ($snippets as $snippet)
                                <div class="mayring-snippet-card">
                                    @if ($snippet->category)
                                        <span class="mayring-snippet-category">{{ $snippet->category }}</span>
                                    @endif
                                    <p class="mayring-snippet-text">{{ Str::limit($snippet->snippet_text, 150) }}</p>
                                    <p class="mayring-snippet-source">{{ $snippet->source_reference }}</p>
                                    @if ($snippet->notes)
                                        <p style="font-family: Lora, serif; font-size: 0.8rem; color: var(--navy); opacity: 0.6; font-style: italic;">
                                            {{ Str::limit($snippet->notes, 80) }}
                                        </p>
                                    @endif
                                    <button
                                        wire:click="deleteSnippet('{{ $snippet->id }}')"
                                        wire:confirm="Löschen?"
                                        class="mayring-snippet-delete"
                                        title="Löschen"
                                    >
                                        ✕
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <script>
        function getSelection() {
            return window.getSelection ? window.getSelection().toString() : '';
        }
    </script>
</div>
