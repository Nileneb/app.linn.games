<?php

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;
    public string $phase;
    public string $renderedContent = '';

    private const ALLOWED_PHASES = ['recherche', 'screening', 'auswertung'];

    public function mount(Projekt $projekt, string $phase): void
    {
        $this->projekt = $projekt;
        $this->phase = $phase;

        // Authorize using ProjektPolicy
        $this->authorize('view', $this->projekt);

        // Validate phase parameter to prevent path traversal
        if (!in_array($this->phase, self::ALLOWED_PHASES, true)) {
            abort(404, "Phase '{$this->phase}' nicht gefunden.");
        }

        $basePath = "recherche/{$this->projekt->id}/{$this->phase}";

        // List files in the directory
        if (!Storage::disk('local')->exists($basePath)) {
            abort(404, "Ergebnisse für Phase '{$this->phase}' nicht gefunden.");
        }

        $files = Storage::disk('local')->files($basePath);

        // Filter for markdown files only
        $mdFiles = array_filter($files, fn ($f) => Str::endsWith($f, '.md'));

        if (empty($mdFiles)) {
            abort(404, 'Keine Ergebnisdateien vorhanden.');
        }

        $combinedContent = '';
        foreach ($mdFiles as $filePath) {
            $content = Storage::disk('local')->get($filePath);
            $combinedContent .= $content . "\n\n---\n\n";
        }

        // Render markdown with safety options to prevent XSS
        $this->renderedContent = Str::markdown(
            $combinedContent,
            [
                'html_input' => 'strip',      // Strip HTML tags from markdown source
                'allow_unsafe_links' => false, // Reject javascript: and data: URLs
            ]
        );
    }
}; ?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ $projekt->titel }} - {{ ucfirst($phase) }}
                </h1>
            </div>
            <div class="border-t border-gray-200">
                <div class="px-4 py-5 sm:p-6">
                    <article class="prose prose-sm sm:prose lg:prose-lg mx-auto">
                        {!! $renderedContent !!}
                    </article>
                </div>
            </div>
        </div>
    </div>
</div>
