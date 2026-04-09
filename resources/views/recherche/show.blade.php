<x-layouts.app :title="$projekt->titel">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <!-- Export Button Group -->
        <x-export-button-group :projekt="$projekt" variant="default" />

        <!-- Cluster Explorer -->
        <div class="flex">
            <a href="{{ route('recherche.galaxy', $projekt) }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-indigo-500/30 bg-indigo-950/40 px-3 py-1.5 text-xs font-medium text-indigo-300 hover:bg-indigo-900/60 transition-colors">
                ✦ Cluster Explorer
            </a>
        </div>

        <!-- Project Detail -->
        <livewire:recherche.projekt-detail :projekt="$projekt" />
    </div>
</x-layouts.app>
