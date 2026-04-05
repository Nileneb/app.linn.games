<?php

use App\Jobs\ProcessMayringBatchJob;
use App\Models\ChunkCodierung;
use App\Models\Recherche\Projekt;
use App\Models\Workspace;
use App\Services\CreditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    public bool $showConfirm = false;

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt;
    }

    public function getTotalChunksProperty(): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM paper_embeddings WHERE projekt_id = ?::uuid',
            [$this->projekt->id]
        )?->cnt ?? 0;
    }

    public function getCompletedProperty(): int
    {
        return ChunkCodierung::where('projekt_id', $this->projekt->id)
            ->where('status', 'completed')
            ->count();
    }

    public function getFailedProperty(): int
    {
        return ChunkCodierung::where('projekt_id', $this->projekt->id)
            ->where('status', 'failed')
            ->count();
    }

    public function getPendingProperty(): int
    {
        return ChunkCodierung::where('projekt_id', $this->projekt->id)
            ->where('status', 'pending')
            ->count();
    }

    public function getEstimatedCentsCostProperty(): int
    {
        $remaining = $this->totalChunks - $this->completed;
        if ($remaining <= 0) {
            return 0;
        }
        // Rough estimate: ~300 tokens input + ~150 tokens output per chunk
        $tokensPerChunk = 450;
        return app(CreditService::class)->toCents($remaining * $tokensPerChunk);
    }

    public function getBalanceCentsProperty(): int
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();
        if ($workspaceId === null) {
            return 0;
        }
        return (int) Workspace::where('id', $workspaceId)->value('credits_balance_cents');
    }

    public function getProgressPercentProperty(): int
    {
        if ($this->totalChunks === 0) {
            return 0;
        }
        return (int) round(($this->completed / $this->totalChunks) * 100);
    }

    public function getIsRunningProperty(): bool
    {
        return $this->pending > 0;
    }

    public function startCodierung(): void
    {
        $this->authorize('view', $this->projekt);
        $this->showConfirm = false;

        if ($this->totalChunks === 0) {
            return;
        }

        ProcessMayringBatchJob::dispatch($this->projekt->id);
    }

    public function abortCodierung(): void
    {
        $this->authorize('view', $this->projekt);

        // Mark all pending codierungen as failed so the next poll reflects abort
        ChunkCodierung::where('projekt_id', $this->projekt->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed', 'error_message' => 'Abgebrochen']);
    }

    public function getResults()
    {
        return ChunkCodierung::where('projekt_id', $this->projekt->id)
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->limit(50)
            ->get();
    }
}; ?>

<div wire:poll.5s class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                Mayring-Codierung
            </h3>
            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                Systematische qualitative Inhaltsanalyse aller Dokument-Abschnitte
            </p>
        </div>
    </div>

    {{-- No chunks notice --}}
    @if ($this->totalChunks === 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
            Keine Dokument-Abschnitte vorhanden. Bitte erst Dokumente hochladen und indexieren.
        </div>
    @else
        {{-- Progress card --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between text-sm text-neutral-600 dark:text-neutral-400">
                <span>Fortschritt</span>
                <span class="font-medium text-neutral-900 dark:text-neutral-100">
                    {{ $this->completed }} / {{ $this->totalChunks }} Chunks codiert
                </span>
            </div>

            <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                <div
                    class="h-full rounded-full bg-blue-500 transition-all duration-500"
                    style="width: {{ $this->progressPercent }}%"
                ></div>
            </div>

            <div class="mt-2 flex gap-4 text-xs text-neutral-500 dark:text-neutral-400">
                @if ($this->pending > 0)
                    <span class="flex items-center gap-1">
                        <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-yellow-400"></span>
                        {{ $this->pending }} ausstehend
                    </span>
                @endif
                @if ($this->failed > 0)
                    <span class="flex items-center gap-1">
                        <span class="inline-block h-2 w-2 rounded-full bg-red-400"></span>
                        {{ $this->failed }} fehlgeschlagen
                    </span>
                @endif
            </div>
        </div>

        {{-- Credit estimate + controls --}}
        @if (! $this->isRunning)
            @if (! $this->showConfirm)
                <div class="flex items-center gap-3">
                    @if ($this->completed < $this->totalChunks)
                        <button
                            wire:click="$set('showConfirm', true)"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600"
                        >
                            {{ $this->completed > 0 ? 'Codierung fortsetzen' : 'Codierung starten' }}
                        </button>
                    @else
                        <span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">
                            Alle Chunks codiert
                        </span>
                    @endif
                </div>
            @else
                {{-- Confirm dialog --}}
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/50 dark:bg-blue-900/20">
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-100">
                        Codierung starten?
                    </p>
                    <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                        {{ $this->totalChunks - $this->completed }} Abschnitte werden verarbeitet.
                        Geschätzte Kosten: ~{{ number_format($this->estimatedCentsCost / 100, 2, ',', '.') }} €
                        (Guthaben: {{ number_format($this->balanceCents / 100, 2, ',', '.') }} €)
                    </p>
                    @if ($this->estimatedCentsCost > $this->balanceCents)
                        <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">
                            Achtung: Guthaben könnte nicht ausreichen.
                        </p>
                    @endif
                    <div class="mt-3 flex gap-2">
                        <button
                            wire:click="startCodierung"
                            class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                        >
                            Ja, starten
                        </button>
                        <button
                            wire:click="$set('showConfirm', false)"
                            class="rounded-md bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 ring-1 ring-neutral-300 hover:bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-600"
                        >
                            Abbrechen
                        </button>
                    </div>
                </div>
            @endif
        @else
            <button
                wire:click="abortCodierung"
                wire:confirm="Laufende Codierung abbrechen?"
                class="inline-flex items-center gap-2 rounded-lg bg-red-100 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50"
            >
                Codierung abbrechen
            </button>
        @endif

        {{-- Results table --}}
        @php $results = $this->getResults(); @endphp
        @if ($results->isNotEmpty())
            <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500">Kategorie</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500">Paraphrase</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500">Generalisierung</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-neutral-500">Reduktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white dark:divide-neutral-700/50 dark:bg-neutral-900">
                        @foreach ($results as $row)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="max-w-[160px] truncate px-3 py-2 font-medium text-neutral-900 dark:text-neutral-100" title="{{ $row->kategorie }}">
                                    {{ $row->kategorie ?? '–' }}
                                </td>
                                <td class="max-w-[200px] truncate px-3 py-2 text-neutral-600 dark:text-neutral-400" title="{{ $row->paraphrase }}">
                                    {{ $row->paraphrase ?? '–' }}
                                </td>
                                <td class="max-w-[200px] truncate px-3 py-2 text-neutral-600 dark:text-neutral-400" title="{{ $row->generalisierung }}">
                                    {{ $row->generalisierung ?? '–' }}
                                </td>
                                <td class="max-w-[200px] truncate px-3 py-2 text-neutral-600 dark:text-neutral-400" title="{{ $row->reduktion }}">
                                    {{ $row->reduktion ?? '–' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($this->completed > 50)
                    <div class="bg-neutral-50 px-3 py-2 text-center text-xs text-neutral-500 dark:bg-neutral-800/30 dark:text-neutral-400">
                        Zeige 50 von {{ $this->completed }} Ergebnissen
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
