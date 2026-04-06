<x-layouts.app :title="$projekt->titel">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <!-- Export Button Group -->
        <x-export-button-group :projekt="$projekt" variant="default" />

        <!-- Project Detail -->
        <livewire:recherche.projekt-detail :projekt="$projekt" />
    </div>
</x-layouts.app>
