<?php

use App\Models\Recherche\{Projekt, P3Datenbankmatrix, P3Disziplin, P3GeografischerFilter, P3GraueLiteratur};
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    // --- Datenbankmatrix ---
    public bool $showDbForm = false;
    public ?string $editingDbId = null;
    public string $dbDatenbank = '';
    public string $dbDisziplin = '';
    public string $dbAbdeckung = '';
    public string $dbBesonderheit = '';
    public string $dbZugang = '';
    public bool $dbEmpfohlen = false;
    public string $dbBegruendung = '';

    // --- Disziplin ---
    public bool $showDisForm = false;
    public ?string $editingDisId = null;
    public string $disDisziplin = '';
    public string $disArt = '';
    public string $disRelevanz = '';
    public string $disAnmerkung = '';

    // --- GeografischerFilter ---
    public bool $showGeoForm = false;
    public ?string $editingGeoId = null;
    public string $geoRegion = '';
    public bool $geoFilterVorhanden = false;
    public string $geoFiltername = '';
    public ?string $geoSensitivitaet = null;
    public string $geoHilfsstrategie = '';

    // --- GraueLiteratur ---
    public bool $showGrauForm = false;
    public ?string $editingGrauId = null;
    public string $grauQuelle = '';
    public string $grauTyp = '';
    public string $grauUrl = '';
    public string $grauSuchpfad = '';
    public string $grauRelevanz = '';
    public string $grauAnmerkung = '';

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt;
    }

    // ─── Datenbankmatrix CRUD ────────────────────────────────

    public function newDb(): void { $this->cancelDb(); $this->showDbForm = true; }

    public function saveDb(): void
    {
        $this->validate(['dbDatenbank' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'datenbank' => $this->dbDatenbank,
            'disziplin' => $this->dbDisziplin ?: null,
            'abdeckung' => $this->dbAbdeckung ?: null,
            'besonderheit' => $this->dbBesonderheit ?: null,
            'zugang' => $this->dbZugang ?: null,
            'empfohlen' => $this->dbEmpfohlen,
            'begruendung' => $this->dbBegruendung ?: null,
        ];
        if ($this->editingDbId) {
            P3Datenbankmatrix::where('projekt_id', $this->projekt->id)->findOrFail($this->editingDbId)->update($data);
        } else {
            P3Datenbankmatrix::create($data);
        }
        $this->cancelDb();
    }

    public function editDb(string $id): void
    {
        $r = P3Datenbankmatrix::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingDbId = $id;
        $this->dbDatenbank = $r->datenbank ?? '';
        $this->dbDisziplin = $r->disziplin ?? '';
        $this->dbAbdeckung = $r->abdeckung ?? '';
        $this->dbBesonderheit = $r->besonderheit ?? '';
        $this->dbZugang = $r->zugang ?? '';
        $this->dbEmpfohlen = (bool) $r->empfohlen;
        $this->dbBegruendung = $r->begruendung ?? '';
        $this->showDbForm = true;
    }

    public function deleteDb(string $id): void
    {
        P3Datenbankmatrix::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelDb(): void
    {
        $this->showDbForm = false;
        $this->editingDbId = null;
        $this->reset(['dbDatenbank', 'dbDisziplin', 'dbAbdeckung', 'dbBesonderheit', 'dbZugang', 'dbEmpfohlen', 'dbBegruendung']);
    }

    // ─── Disziplin CRUD ──────────────────────────────────────

    public function newDis(): void { $this->cancelDis(); $this->showDisForm = true; }

    public function saveDis(): void
    {
        $this->validate(['disDisziplin' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'disziplin' => $this->disDisziplin,
            'art' => $this->disArt ?: null,
            'relevanz' => $this->disRelevanz ?: null,
            'anmerkung' => $this->disAnmerkung ?: null,
        ];
        if ($this->editingDisId) {
            P3Disziplin::where('projekt_id', $this->projekt->id)->findOrFail($this->editingDisId)->update($data);
        } else {
            P3Disziplin::create($data);
        }
        $this->cancelDis();
    }

    public function editDis(string $id): void
    {
        $r = P3Disziplin::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingDisId = $id;
        $this->disDisziplin = $r->disziplin ?? '';
        $this->disArt = $r->art ?? '';
        $this->disRelevanz = $r->relevanz ?? '';
        $this->disAnmerkung = $r->anmerkung ?? '';
        $this->showDisForm = true;
    }

    public function deleteDis(string $id): void
    {
        P3Disziplin::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelDis(): void
    {
        $this->showDisForm = false;
        $this->editingDisId = null;
        $this->reset(['disDisziplin', 'disArt', 'disRelevanz', 'disAnmerkung']);
    }

    // ─── GeografischerFilter CRUD ────────────────────────────

    public function newGeo(): void { $this->cancelGeo(); $this->showGeoForm = true; }

    public function saveGeo(): void
    {
        $this->validate(['geoRegion' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'region_land' => $this->geoRegion,
            'validierter_filter_vorhanden' => $this->geoFilterVorhanden,
            'filtername_quelle' => $this->geoFiltername ?: null,
            'sensitivitaet_prozent' => $this->geoSensitivitaet !== null && $this->geoSensitivitaet !== '' ? (float) $this->geoSensitivitaet : null,
            'hilfsstrategie' => $this->geoHilfsstrategie ?: null,
        ];
        if ($this->editingGeoId) {
            P3GeografischerFilter::where('projekt_id', $this->projekt->id)->findOrFail($this->editingGeoId)->update($data);
        } else {
            P3GeografischerFilter::create($data);
        }
        $this->cancelGeo();
    }

    public function editGeo(string $id): void
    {
        $r = P3GeografischerFilter::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingGeoId = $id;
        $this->geoRegion = $r->region_land ?? '';
        $this->geoFilterVorhanden = (bool) $r->validierter_filter_vorhanden;
        $this->geoFiltername = $r->filtername_quelle ?? '';
        $this->geoSensitivitaet = $r->sensitivitaet_prozent !== null ? (string) $r->sensitivitaet_prozent : null;
        $this->geoHilfsstrategie = $r->hilfsstrategie ?? '';
        $this->showGeoForm = true;
    }

    public function deleteGeo(string $id): void
    {
        P3GeografischerFilter::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelGeo(): void
    {
        $this->showGeoForm = false;
        $this->editingGeoId = null;
        $this->reset(['geoRegion', 'geoFilterVorhanden', 'geoFiltername', 'geoSensitivitaet', 'geoHilfsstrategie']);
    }

    // ─── GraueLiteratur CRUD ─────────────────────────────────

    public function newGrau(): void { $this->cancelGrau(); $this->showGrauForm = true; }

    public function saveGrau(): void
    {
        $this->validate(['grauQuelle' => 'required|string|max:255']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'quelle' => $this->grauQuelle,
            'typ' => $this->grauTyp ?: null,
            'url' => $this->grauUrl ?: null,
            'suchpfad' => $this->grauSuchpfad ?: null,
            'relevanz' => $this->grauRelevanz ?: null,
            'anmerkung' => $this->grauAnmerkung ?: null,
        ];
        if ($this->editingGrauId) {
            P3GraueLiteratur::where('projekt_id', $this->projekt->id)->findOrFail($this->editingGrauId)->update($data);
        } else {
            P3GraueLiteratur::create($data);
        }
        $this->cancelGrau();
    }

    public function editGrau(string $id): void
    {
        $r = P3GraueLiteratur::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingGrauId = $id;
        $this->grauQuelle = $r->quelle ?? '';
        $this->grauTyp = $r->typ ?? '';
        $this->grauUrl = $r->url ?? '';
        $this->grauSuchpfad = $r->suchpfad ?? '';
        $this->grauRelevanz = $r->relevanz ?? '';
        $this->grauAnmerkung = $r->anmerkung ?? '';
        $this->showGrauForm = true;
    }

    public function deleteGrau(string $id): void
    {
        P3GraueLiteratur::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelGrau(): void
    {
        $this->showGrauForm = false;
        $this->editingGrauId = null;
        $this->reset(['grauQuelle', 'grauTyp', 'grauUrl', 'grauSuchpfad', 'grauRelevanz', 'grauAnmerkung']);
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        return [
            'datenbanken' => P3Datenbankmatrix::where('projekt_id', $pid)->get(),
            'disziplinen' => P3Disziplin::where('projekt_id', $pid)->get(),
            'geoFilter' => P3GeografischerFilter::where('projekt_id', $pid)->get(),
            'graueLiteratur' => P3GraueLiteratur::where('projekt_id', $pid)->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="scoping_mapping_agent"
        label="🗂️ KI: Datenbankauswahl schärfen"
        :phase-nr="3"
        :key="'agent-p3-'.$projekt->id"
    />

    {{-- ═══ Datenbankmatrix ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Datenbankmatrix
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $datenbanken->count() }})</span>
            </h3>
            <button wire:click="newDb" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showDbForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Datenbank *</label>
                        <input wire:model="dbDatenbank" type="text" placeholder="z.B. PubMed" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Disziplin</label>
                        <input wire:model="dbDisziplin" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Zugang</label>
                        <input wire:model="dbZugang" type="text" placeholder="z.B. Uni-Zugang" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Abdeckung</label>
                        <input wire:model="dbAbdeckung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Besonderheit</label>
                        <input wire:model="dbBesonderheit" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Empfohlen</label>
                        <label class="mt-1 flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input wire:model="dbEmpfohlen" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Ja
                        </label>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung</label>
                        <input wire:model="dbBegruendung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                @error('dbDatenbank') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveDb" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelDb" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($datenbanken->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Datenbank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Disziplin</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Zugang</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Empfohlen</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($datenbanken as $db)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $db->datenbank }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $db->disziplin ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $db->zugang ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($db->empfohlen)
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-400">Ja</span>
                                    @else <span class="text-neutral-400">—</span> @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editDb('{{ $db->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteDb('{{ $db->id }}')" wire:confirm="Datenbank wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Datenbanken konfiguriert.</p>
        @endif
    </div>

    {{-- ═══ Disziplinen ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Disziplinen
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $disziplinen->count() }})</span>
            </h3>
            <button wire:click="newDis" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showDisForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Disziplin *</label>
                        <input wire:model="disDisziplin" type="text" placeholder="z.B. Medizin" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Art</label>
                        <input wire:model="disArt" type="text" placeholder="z.B. Haupt/Neben" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Relevanz</label>
                        <input wire:model="disRelevanz" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkung</label>
                        <input wire:model="disAnmerkung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                @error('disDisziplin') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveDis" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelDis" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($disziplinen->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Disziplin</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Art</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Relevanz</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Anmerkung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($disziplinen as $d)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $d->disziplin }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $d->art ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $d->relevanz ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-500">{{ str()->limit($d->anmerkung, 40) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editDis('{{ $d->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteDis('{{ $d->id }}')" wire:confirm="Disziplin wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Disziplinen definiert.</p>
        @endif
    </div>

    {{-- ═══ Geografische Filter ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Geografische Filter
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $geoFilter->count() }})</span>
            </h3>
            <button wire:click="newGeo" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showGeoForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Region/Land *</label>
                        <input wire:model="geoRegion" type="text" placeholder="z.B. Europa" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Filter vorhanden</label>
                        <label class="mt-1 flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input wire:model="geoFilterVorhanden" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Ja
                        </label>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Sensitivität (%)</label>
                        <input wire:model="geoSensitivitaet" type="number" step="0.01" min="0" max="100" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Filtername/Quelle</label>
                        <input wire:model="geoFiltername" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Hilfsstrategie</label>
                        <input wire:model="geoHilfsstrategie" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                @error('geoRegion') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveGeo" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelGeo" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($geoFilter->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Region/Land</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Filter</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Sensitivität</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Hilfsstrategie</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($geoFilter as $gf)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $gf->region_land }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">
                                    @if ($gf->validierter_filter_vorhanden)
                                        <span class="text-green-600 dark:text-green-400">✓</span> {{ $gf->filtername_quelle ?? '' }}
                                    @else <span class="text-neutral-400">—</span> @endif
                                </td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gf->sensitivitaet_prozent !== null ? number_format($gf->sensitivitaet_prozent, 1) . '%' : '—' }}</td>
                                <td class="px-4 py-2 text-neutral-500">{{ str()->limit($gf->hilfsstrategie, 40) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editGeo('{{ $gf->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteGeo('{{ $gf->id }}')" wire:confirm="Filter wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine geografischen Filter konfiguriert.</p>
        @endif
    </div>

    {{-- ═══ Graue Literatur ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Graue Literatur
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $graueLiteratur->count() }})</span>
            </h3>
            <button wire:click="newGrau" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showGrauForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Quelle *</label>
                        <input wire:model="grauQuelle" type="text" placeholder="z.B. OpenGrey" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Typ</label>
                        <input wire:model="grauTyp" type="text" placeholder="z.B. Datenbank, Register" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Relevanz</label>
                        <input wire:model="grauRelevanz" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">URL</label>
                        <input wire:model="grauUrl" type="url" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchpfad</label>
                        <input wire:model="grauSuchpfad" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkung</label>
                    <input wire:model="grauAnmerkung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                @error('grauQuelle') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveGrau" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelGrau" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($graueLiteratur->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Quelle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Typ</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">URL</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Relevanz</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($graueLiteratur as $gl)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $gl->quelle }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gl->typ ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-500">
                                    @if ($gl->url)
                                        <span class="text-xs text-blue-600 dark:text-blue-400">{{ str()->limit($gl->url, 30) }}</span>
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gl->relevanz ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editGrau('{{ $gl->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteGrau('{{ $gl->id }}')" wire:confirm="Eintrag wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Quellen für graue Literatur.</p>
        @endif
    </div>
</div>
