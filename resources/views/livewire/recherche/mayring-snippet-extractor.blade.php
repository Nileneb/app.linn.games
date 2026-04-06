<?php

use App\Models\Recherche\P5Treffer;
use App\Services\MayringSnippetService;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $paperId;
    public string $projektId;
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
            projektId: $this->projektId,
            paperId: $this->paperId,
            snippetText: $this->selectedText,
            category: $this->category ?: null,
            notes: $this->notes ?: null,
        );

        $this->dispatch('snippet-saved');
        $this->resetForm();
        $this->loadSnippets();
    }

    public function deleteSnippet(string $snippetId): void
    {
        $service = app(MayringSnippetService::class);
        $service->delete($snippetId);
        $this->dispatch('snippet-deleted');
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

    public function downloadAsMarkdown(): \Illuminate\Http\Response
    {
        $service = app(MayringSnippetService::class);
        $markdown = $service->exportAsMarkdown($this->projektId);

        return response()->streamDownload(
            function () use ($markdown) { echo $markdown; },
            'mayring-analysis-' . now()->format('Y-m-d-His') . '.md',
            ['Content-Type' => 'text/markdown']
        );
    }
}; ?>

<div class="space-y-6">
    <!-- Text Selection Guide -->
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/30 dark:bg-amber-900/10">
        <p class="text-sm text-amber-800 dark:text-amber-200">
            <strong>Mayring-Analyse:</strong> Markiere Textausschnitte im Paper unten, um sie zur Analyse zu extrahieren.
            Jeder Ausschnitt wird automatisch mit Quellenreferenz versehen.
        </p>
    </div>

    <!-- Paper Content (with selection support) -->
    @if ($paper && $paper->abstract)
        <div class="space-y-2">
            <h3 class="font-semibold text-gray-900 dark:text-white">Paper-Inhalt (Auszug)</h3>
            <div
                id="paper-content"
                class="prose prose-sm dark:prose-invert rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                @mouseup="$wire.call('onTextSelected', getSelection().toString())"
            >
                <p>{{ $paper->abstract }}</p>
            </div>
        </div>
    @endif

    <!-- Snippet Creation Form -->
    @if ($showForm && $selectedText)
        <div class="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900/30 dark:bg-indigo-900/10">
            <h4 class="font-semibold text-indigo-900 dark:text-indigo-100">Neuen Snippet erstellen</h4>

            <div class="space-y-3">
                <!-- Selected Text (Read-only) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Ausgewählter Text
                    </label>
                    <textarea
                        class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                        rows="3"
                        readonly
                    >{{ $selectedText }}</textarea>
                </div>

                <!-- Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Kategorie (optional)
                    </label>
                    <input
                        type="text"
                        id="category"
                        wire:model="category"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                        placeholder="z.B. Methode, Ergebnis, Limitation"
                    />
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Anmerkungen (optional)
                    </label>
                    <textarea
                        id="notes"
                        wire:model="notes"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                        rows="2"
                        placeholder="Persönliche Anmerkungen zu diesem Ausschnitt"
                    ></textarea>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-2">
                    <button
                        wire:click="saveSnippet"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7.707 10.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 10-1.414-1.414L8 11.586l-1.293-1.293z" />
                        </svg>
                        Snippet speichern
                    </button>
                    <button
                        wire:click="resetForm"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Abbrechen
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Extracted Snippets -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-white">Extrahierte Snippets</h3>
            @if (!empty($allSnippets))
                <button
                    wire:click="downloadAsMarkdown"
                    class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download MD
                </button>
            @endif
        </div>

        @if (empty($allSnippets))
            <p class="text-sm text-gray-500 dark:text-gray-400">Noch keine Snippets extrahiert.</p>
        @else
            @foreach ($allSnippets as $category => $snippets)
                <div class="space-y-2">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300">{{ $category }}</h4>

                    <div class="space-y-2">
                        @foreach ($snippets as $snippet)
                            <div class="flex gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex-1 space-y-1">
                                    <p class="text-sm text-gray-900 dark:text-gray-100">
                                        "{{ Str::limit($snippet->snippet_text, 100) }}"
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <strong>Quelle:</strong> {{ $snippet->source_reference }}
                                    </p>
                                    @if ($snippet->notes)
                                        <p class="text-xs italic text-gray-600 dark:text-gray-300">
                                            {{ $snippet->notes }}
                                        </p>
                                    @endif
                                </div>
                                <button
                                    wire:click="deleteSnippet('{{ $snippet->id }}')"
                                    wire:confirm="Snippet löschen?"
                                    class="self-start text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
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
    return window.getSelection ? window.getSelection() : document.selection.createRange().text;
}
</script>
