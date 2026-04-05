<?php

use App\Livewire\Concerns\HasProjektContext;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{P2ReviewTypEntscheidung, P2Cluster, P2MappingSuchstringKomponente, P2Trefferliste};
use Livewire\Volt\Component;

new class extends Component {
    use HasProjektContext;

    // --- ReviewTypEntscheidung ---
    public bool $showRevForm = false;
    public ?string $editingRevId = null;
    public string $revReviewTyp = '';
    public bool $revPasst = false;
    public string $revBegruendung = '';

    // --- Cluster ---
    public bool $showCluForm = false;
    public ?string $editingCluId = null;
    public string $cluClusterId = '';
    public string $cluLabel = '';
    public string $cluBeschreibung = '';
    public ?int $cluTrefferSchaetzung = null;
    public string $cluRelevanz = '';

    // --- MappingSuchstringKomponente ---
    public bool $showMapForm = false;
    public ?string $editingMapId = null;
    public string $mapKomponenteLabel = '';
    public string $mapSuchbegriffe = '';
    public string $mapSprache = '';
    public bool $mapTrunkierung = false;
    public string $mapAnmerkung = '';

    // --- Trefferliste ---
    public bool $showTrefForm = false;
    public ?string $editingTrefId = null;
    public string $trefDatenbank = '';
    public string $trefSuchstring = '';
    public ?int $trefTrefferGesamt = null;
    public string $trefEinschaetzung = '';
    public bool $trefAnpassungNotwendig = false;
    public ?string $trefSuchdatum = null;

    // ─── ReviewTypEntscheidung CRUD ──────────────────────────

    public function newRev(): void { $this->cancelRev(); $this->showRevForm = true; }

    public function saveRev(): void
    {
        $this->validate(['revReviewTyp' => 'required|string|max:100']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'review_typ' => $this->revReviewTyp,
            'passt' => $this->revPasst,
            'begruendung' => $this->revBegruendung ?: null,
        ];
        if ($this->editingRevId) {
            P2ReviewTypEntscheidung::where('projekt_id', $this->projekt->id)->findOrFail($this->editingRevId)->update($data);
        } else {
            P2ReviewTypEntscheidung::create($data);
        }
        $this->cancelRev();
    }

    public function editRev(string $id): void
    {
        $r = P2ReviewTypEntscheidung::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingRevId = $id;
        $this->revReviewTyp = $r->review_typ ?? '';
        $this->revPasst = (bool) $r->passt;
        $this->revBegruendung = $r->begruendung ?? '';
        $this->showRevForm = true;
    }

    public function deleteRev(string $id): void
    {
        P2ReviewTypEntscheidung::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelRev(): void
    {
        $this->showRevForm = false;
        $this->editingRevId = null;
        $this->reset(['revReviewTyp', 'revPasst', 'revBegruendung']);
    }

    // ─── Cluster CRUD ────────────────────────────────────────

    public function newClu(): void { $this->cancelClu(); $this->showCluForm = true; }

    public function saveClu(): void
    {
        $this->validate(['cluLabel' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'cluster_id' => $this->cluClusterId ?: null,
            'cluster_label' => $this->cluLabel,
            'beschreibung' => $this->cluBeschreibung ?: null,
            'treffer_schaetzung' => $this->cluTrefferSchaetzung,
            'relevanz' => $this->cluRelevanz ?: null,
        ];
        if ($this->editingCluId) {
            P2Cluster::where('projekt_id', $this->projekt->id)->findOrFail($this->editingCluId)->update($data);
        } else {
            P2Cluster::create($data);
        }
        $this->cancelClu();
    }

    public function editClu(string $id): void
    {
        $r = P2Cluster::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingCluId = $id;
        $this->cluClusterId = $r->cluster_id ?? '';
        $this->cluLabel = $r->cluster_label ?? '';
        $this->cluBeschreibung = $r->beschreibung ?? '';
        $this->cluTrefferSchaetzung = $r->treffer_schaetzung;
        $this->cluRelevanz = $r->relevanz ?? '';
        $this->showCluForm = true;
    }

    public function deleteClu(string $id): void
    {
        P2Cluster::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelClu(): void
    {
        $this->showCluForm = false;
        $this->editingCluId = null;
        $this->reset(['cluClusterId', 'cluLabel', 'cluBeschreibung', 'cluTrefferSchaetzung', 'cluRelevanz']);
    }

    // ─── MappingSuchstringKomponente CRUD ────────────────────

    public function newMap(): void { $this->cancelMap(); $this->showMapForm = true; }

    public function saveMap(): void
    {
        $this->validate(['mapKomponenteLabel' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'komponente_label' => $this->mapKomponenteLabel,
            'suchbegriffe' => $this->mapSuchbegriffe ? array_map('trim', explode(',', $this->mapSuchbegriffe)) : null,
            'sprache' => $this->mapSprache ?: null,
            'trunkierung_genutzt' => $this->mapTrunkierung,
            'anmerkung' => $this->mapAnmerkung ?: null,
        ];
        if ($this->editingMapId) {
            P2MappingSuchstringKomponente::where('projekt_id', $this->projekt->id)->findOrFail($this->editingMapId)->update($data);
        } else {
            P2MappingSuchstringKomponente::create($data);
        }
        $this->cancelMap();
    }

    public function editMap(string $id): void
    {
        $r = P2MappingSuchstringKomponente::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingMapId = $id;
        $this->mapKomponenteLabel = $r->komponente_label ?? '';
        $this->mapSuchbegriffe = is_array($r->suchbegriffe) ? implode(', ', $r->suchbegriffe) : '';
        $this->mapSprache = $r->sprache ?? '';
        $this->mapTrunkierung = (bool) $r->trunkierung_genutzt;
        $this->mapAnmerkung = $r->anmerkung ?? '';
        $this->showMapForm = true;
    }

    public function deleteMap(string $id): void
    {
        P2MappingSuchstringKomponente::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelMap(): void
    {
        $this->showMapForm = false;
        $this->editingMapId = null;
        $this->reset(['mapKomponenteLabel', 'mapSuchbegriffe', 'mapSprache', 'mapTrunkierung', 'mapAnmerkung']);
    }

    // ─── Trefferliste CRUD ───────────────────────────────────

    public function newTref(): void { $this->cancelTref(); $this->showTrefForm = true; }

    public function saveTref(): void
    {
        $this->validate(['trefDatenbank' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'datenbank' => $this->trefDatenbank,
            'suchstring' => $this->trefSuchstring ?: null,
            'treffer_gesamt' => $this->trefTrefferGesamt,
            'einschaetzung' => $this->trefEinschaetzung ?: null,
            'anpassung_notwendig' => $this->trefAnpassungNotwendig,
            'suchdatum' => $this->trefSuchdatum ?: null,
        ];
        if ($this->editingTrefId) {
            P2Trefferliste::where('projekt_id', $this->projekt->id)->findOrFail($this->editingTrefId)->update($data);
        } else {
            P2Trefferliste::create($data);
        }
        $this->cancelTref();
    }

    public function editTref(string $id): void
    {
        $r = P2Trefferliste::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingTrefId = $id;
        $this->trefDatenbank = $r->datenbank ?? '';
        $this->trefSuchstring = $r->suchstring ?? '';
        $this->trefTrefferGesamt = $r->treffer_gesamt;
        $this->trefEinschaetzung = $r->einschaetzung ?? '';
        $this->trefAnpassungNotwendig = (bool) $r->anpassung_notwendig;
        $this->trefSuchdatum = $r->suchdatum?->format('Y-m-d');
        $this->showTrefForm = true;
    }

    public function deleteTref(string $id): void
    {
        P2Trefferliste::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelTref(): void
    {
        $this->showTrefForm = false;
        $this->editingTrefId = null;
        $this->reset(['trefDatenbank', 'trefSuchstring', 'trefTrefferGesamt', 'trefEinschaetzung', 'trefAnpassungNotwendig', 'trefSuchdatum']);
    }

    // ─── Data ────────────────────────────────────────────────

    public function hasRunningAgentJob(): bool
    {
        return rescue(fn () => PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('phase_nr', 2)
            ->where('status', 'pending')
            ->exists(), false);
    }

    public function with(): array
    {
        $pid = $this->projekt->id;
        return [
            'reviewTypen' => rescue(fn () => P2ReviewTypEntscheidung::where('projekt_id', $pid)->get(), collect()),
            'cluster' => rescue(fn () => P2Cluster::where('projekt_id', $pid)->get(), collect()),
            'mappings' => rescue(fn () => P2MappingSuchstringKomponente::where('projekt_id', $pid)->get(), collect()),
            'trefferlisten' => rescue(fn () => P2Trefferliste::where('projekt_id', $pid)->orderBy('suchdatum', 'desc')->get(), collect()),
            'latestAgentResult' => rescue(fn () => PhaseAgentResult::where('projekt_id', $pid)->where('phase_nr', 2)->whereNotNull('content')->latest()->first()),
        ];
    }
}; ?>

@if($this->hasRunningAgentJob())
    <div class="space-y-6" wire:poll.10s>
@else
    <div class="space-y-6">
@endif
    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="scoping_mapping_agent"
        label="🧭 KI: Mapping schärfen"
        :phase-nr="2"
        :key="'agent-p2-'.$projekt->id"
    />
    {{-- KI-Vorschlag (letztes Agent-Ergebnis) --}}
    <x-agent-suggestion :result="$latestAgentResult" />


    {{-- ═══ Review-Typ-Entscheidung ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Review-Typ-Entscheidung
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $reviewTypen->count() }})</span>
            </h3>
            <button wire:click="newRev" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showRevForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelRev"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Review-Typ-Entscheidung {{ $editingRevId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelRev" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Review-Typ *</label>
                            <input wire:model="revReviewTyp" type="text" placeholder="z.B. systematic_review" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            @error('revReviewTyp') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung</label>
                            <input wire:model="revBegruendung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <input wire:model="revPasst" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Passt zum Review-Typ
                    </label>
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelRev" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveRev" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($reviewTypen->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Review-Typ</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Passt</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Begründung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($reviewTypen as $rt)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $rt->review_typ }}</td>
                                <td class="px-4 py-2">
                                    @if ($rt->passt)
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-400">Ja</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/50 dark:text-red-400">Nein</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($rt->begruendung, 60) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editRev('{{ $rt->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteRev('{{ $rt->id }}')" wire:confirm="Eintrag wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Review-Typ-Entscheidungen.</p>
        @endif
    </div>

    {{-- ═══ Cluster ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Cluster
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $cluster->count() }})</span>
            </h3>
            <button wire:click="newClu" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showCluForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelClu"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Cluster {{ $editingCluId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelClu" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Cluster-ID</label>
                            <input wire:model="cluClusterId" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Label *</label>
                            <input wire:model="cluLabel" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            @error('cluLabel') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer-Schätzung</label>
                            <input wire:model="cluTrefferSchaetzung" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Relevanz</label>
                            <input wire:model="cluRelevanz" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Beschreibung</label>
                        <input wire:model="cluBeschreibung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelClu" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveClu" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($cluster->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Label</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Beschreibung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Treffer</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Relevanz</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($cluster as $c)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-neutral-500">{{ $c->cluster_id }}</td>
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $c->cluster_label }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($c->beschreibung, 50) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $c->treffer_schaetzung ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $c->relevanz ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editClu('{{ $c->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteClu('{{ $c->id }}')" wire:confirm="Cluster wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Cluster vorhanden.</p>
        @endif
    </div>

    {{-- ═══ Suchstring-Komponenten-Mapping ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Suchstring-Komponenten-Mapping
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $mappings->count() }})</span>
            </h3>
            <button wire:click="newMap" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showMapForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelMap"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Suchstring-Mapping {{ $editingMapId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelMap" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Komponente *</label>
                            <input wire:model="mapKomponenteLabel" type="text" placeholder="z.B. Population" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            @error('mapKomponenteLabel') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Sprache</label>
                            <input wire:model="mapSprache" type="text" placeholder="z.B. DE, EN" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchbegriffe <span class="font-normal text-neutral-400">(kommagetrennt)</span></label>
                        <input wire:model="mapSuchbegriffe" type="text" placeholder="Begriff 1, Begriff 2, ..." class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkung</label>
                        <input wire:model="mapAnmerkung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <input wire:model="mapTrunkierung" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Trunkierung genutzt
                    </label>
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelMap" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveMap" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($mappings->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Komponente</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Suchbegriffe</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Sprache</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Trunkierung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($mappings as $m)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $m->komponente_label }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">
                                    @if (is_array($m->suchbegriffe))
                                        {{ implode(', ', array_slice($m->suchbegriffe, 0, 4)) }}
                                        @if (count($m->suchbegriffe) > 4) <span class="text-neutral-400">+{{ count($m->suchbegriffe) - 4 }}</span> @endif
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2 text-neutral-500">{{ $m->sprache ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($m->trunkierung_genutzt)
                                        <span class="text-green-600 dark:text-green-400">✓</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editMap('{{ $m->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteMap('{{ $m->id }}')" wire:confirm="Mapping wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Mappings vorhanden.</p>
        @endif
    </div>

    {{-- ═══ Trefferlisten (Vorabsuche) ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Trefferlisten (Vorabsuche)
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $trefferlisten->count() }})</span>
            </h3>
            <button wire:click="newTref" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showTrefForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelTref"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Trefferliste {{ $editingTrefId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelTref" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Datenbank *</label>
                            <input wire:model="trefDatenbank" type="text" placeholder="z.B. PubMed" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            @error('trefDatenbank') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer gesamt</label>
                            <input wire:model="trefTrefferGesamt" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchdatum</label>
                            <input wire:model="trefSuchdatum" type="date" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Einschätzung</label>
                            <input wire:model="trefEinschaetzung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchstring</label>
                        <textarea wire:model="trefSuchstring" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm font-mono dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <input wire:model="trefAnpassungNotwendig" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Anpassung notwendig
                    </label>
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelTref" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveTref" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($trefferlisten->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Datenbank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Treffer</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Einschätzung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Anpassung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($trefferlisten as $tl)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $tl->datenbank }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ number_format($tl->treffer_gesamt ?? 0) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-neutral-500">{{ $tl->suchdatum?->format('d.m.Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($tl->einschaetzung, 40) }}</td>
                                <td class="px-4 py-2">
                                    @if ($tl->anpassung_notwendig)
                                        <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-400">Ja</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editTref('{{ $tl->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteTref('{{ $tl->id }}')" wire:confirm="Trefferliste wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Trefferlisten vorhanden.</p>
        @endif
    </div>
</div>
