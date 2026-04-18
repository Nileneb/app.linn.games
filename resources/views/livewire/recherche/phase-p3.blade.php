<?php

use App\Livewire\Concerns\{HasProjektContext, LoadsPhaseAgentResult};
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{P3Datenbankmatrix, P3Disziplin, P3GeografischerFilter, P3GraueLiteratur};
use Livewire\Volt\Component;
use App\Services\TransitionValidator;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use HasProjektContext, LoadsPhaseAgentResult;

    // --- Phase Transition ---
    public bool $showOverrideForm = false;
    public string $overrideBegruendung = '';

    // --- Datenbankmatrix ---
    public ?string $editingDbId = null;
    public string $dbDatenbank = '';
    public string $dbDisziplin = '';
    public string $dbAbdeckung = '';
    public string $dbBesonderheit = '';
    public string $dbZugang = '';
    public bool $dbEmpfohlen = false;
    public string $dbBegruendung = '';

    // --- Disziplin ---
    public ?string $editingDisId = null;
    public string $disDisziplin = '';
    public string $disArt = '';
    public string $disRelevanz = '';
    public string $disAnmerkung = '';

    // --- GeografischerFilter ---
    public ?string $editingGeoId = null;
    public string $geoRegion = '';
    public bool $geoFilterVorhanden = false;
    public string $geoFiltername = '';
    public ?string $geoSensitivitaet = null;
    public string $geoHilfsstrategie = '';

    // --- GraueLiteratur ---
    public ?string $editingGrauId = null;
    public string $grauQuelle = '';
    public string $grauTyp = '';
    public string $grauUrl = '';
    public string $grauSuchpfad = '';
    public string $grauRelevanz = '';
    public string $grauAnmerkung = '';

    // ─── Datenbankmatrix CRUD ────────────────────────────────

    public function newDb(): void { $this->cancelDb(); $this->editingDbId = 'new'; }

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
        if ($this->editingDbId && $this->editingDbId !== 'new') {
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
    }

    public function deleteDb(string $id): void
    {
        P3Datenbankmatrix::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelDb(): void
    {
        $this->editingDbId = null;
        $this->reset(['dbDatenbank', 'dbDisziplin', 'dbAbdeckung', 'dbBesonderheit', 'dbZugang', 'dbEmpfohlen', 'dbBegruendung']);
    }

    // ─── Disziplin CRUD ──────────────────────────────────────

    public function newDis(): void { $this->cancelDis(); $this->editingDisId = 'new'; }

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
        if ($this->editingDisId && $this->editingDisId !== 'new') {
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
    }

    public function deleteDis(string $id): void
    {
        P3Disziplin::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelDis(): void
    {
        $this->editingDisId = null;
        $this->reset(['disDisziplin', 'disArt', 'disRelevanz', 'disAnmerkung']);
    }

    // ─── GeografischerFilter CRUD ────────────────────────────

    public function newGeo(): void { $this->cancelGeo(); $this->editingGeoId = 'new'; }

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
        if ($this->editingGeoId && $this->editingGeoId !== 'new') {
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
    }

    public function deleteGeo(string $id): void
    {
        P3GeografischerFilter::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelGeo(): void
    {
        $this->editingGeoId = null;
        $this->reset(['geoRegion', 'geoFilterVorhanden', 'geoFiltername', 'geoSensitivitaet', 'geoHilfsstrategie']);
    }

    // ─── GraueLiteratur CRUD ─────────────────────────────────

    public function newGrau(): void { $this->cancelGrau(); $this->editingGrauId = 'new'; }

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
        if ($this->editingGrauId && $this->editingGrauId !== 'new') {
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
    }

    public function deleteGrau(string $id): void
    {
        P3GraueLiteratur::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelGrau(): void
    {
        $this->editingGrauId = null;
        $this->reset(['grauQuelle', 'grauTyp', 'grauUrl', 'grauSuchpfad', 'grauRelevanz', 'grauAnmerkung']);
    }

    // ─── Phase Transition Methods ────────────────────────────

    public function requestOverride(): void
    {
        $this->showOverrideForm = true;
    }

    public function confirmOverride(): void
    {
        $this->validate(['overrideBegruendung' => 'required|string|min:10']);
        Log::info('Phase transition override', [
            'projekt_id'  => $this->projekt->id,
            'phase_nr'    => 3,
            'begruendung' => $this->overrideBegruendung,
            'user_id'     => auth()->id(),
        ]);
        $this->dispatch('phase-override-confirmed', phaseNr: 3);
        $this->showOverrideForm = false;
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $validator = app(TransitionValidator::class);
        $pid = $this->projekt->id;
        return [
            'datenbanken' => rescue(
                fn () => P3Datenbankmatrix::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'disziplinen' => rescue(
                fn () => P3Disziplin::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'geoFilter' => rescue(
                fn () => P3GeografischerFilter::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'graueLiteratur' => rescue(
                fn () => P3GraueLiteratur::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'transitionStatus' => $validator->getTransitionStatus($this->projekt, 3, 4),
        ];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>
    {{-- Datenbankmatrix --}}
    <div class="rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Datenbankmatrix
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $datenbanken->count() }})</span>
            </h3>
            <button wire:click="newDb" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        {{-- Neue Datenbank Form --}}
        @if ($editingDbId === 'new' || $editingDbId)
            <div class="border-b border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h4 class="mb-3 text-xs font-semibold text-neutral-700 dark:text-neutral-300">{{ $editingDbId === 'new' ? 'Neue Datenbank' : 'Datenbank bearbeiten' }}</h4>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input wire:model="dbDatenbank" type="text" placeholder="Datenbank *" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="dbDisziplin" type="text" placeholder="Disziplin" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="dbAbdeckung" type="text" placeholder="Abdeckung" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="dbBesonderheit" type="text" placeholder="Besonderheit" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="dbZugang" type="text" placeholder="Zugang" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="dbBegruendung" type="text" placeholder="Begründung" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <label class="mt-2 flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300">
                    <input wire:model="dbEmpfohlen" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Empfohlen
                </label>
                @error('dbDatenbank') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-2 flex gap-2">
                    <button wire:click="saveDb" class="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelDb" class="rounded border border-neutral-300 px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300">Abbrechen</button>
                </div>
            </div>
        @endif

        {{-- Tabelle --}}
        @if ($datenbanken->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Datenbank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Disziplin</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Zugang</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($datenbanken as $db)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $db->datenbank }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $db->disziplin ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $db->zugang ?? '—' }}</td>
                                <td class="flex justify-end gap-2 px-4 py-2 text-right">
                                    <button wire:click="editDb('{{ $db->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">✎</button>
                                    <button wire:click="deleteDb('{{ $db->id }}')" wire:confirm="Wirklich löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400">×</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-xs text-neutral-500 dark:text-neutral-400">Noch keine Datenbanken konfiguriert.</p>
        @endif
    </div>

    {{-- Disziplinen --}}
    <div class="rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Disziplinen
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $disziplinen->count() }})</span>
            </h3>
            <button wire:click="newDis" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($editingDisId === 'new' || $editingDisId)
            <div class="border-b border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h4 class="mb-3 text-xs font-semibold text-neutral-700 dark:text-neutral-300">{{ $editingDisId === 'new' ? 'Neue Disziplin' : 'Disziplin bearbeiten' }}</h4>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input wire:model="disDisziplin" type="text" placeholder="Disziplin *" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="disArt" type="text" placeholder="Art" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="disRelevanz" type="text" placeholder="Relevanz" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="disAnmerkung" type="text" placeholder="Anmerkung" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                @error('disDisziplin') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-2 flex gap-2">
                    <button wire:click="saveDis" class="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelDis" class="rounded border border-neutral-300 px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($disziplinen->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Disziplin</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Art</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Relevanz</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($disziplinen as $d)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $d->disziplin }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $d->art ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $d->relevanz ?? '—' }}</td>
                                <td class="flex justify-end gap-2 px-4 py-2 text-right">
                                    <button wire:click="editDis('{{ $d->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">✎</button>
                                    <button wire:click="deleteDis('{{ $d->id }}')" wire:confirm="Wirklich löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400">×</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-xs text-neutral-500 dark:text-neutral-400">Noch keine Disziplinen definiert.</p>
        @endif
    </div>

    {{-- Geografische Filter --}}
    <div class="rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Geografische Filter
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $geoFilter->count() }})</span>
            </h3>
            <button wire:click="newGeo" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($editingGeoId === 'new' || $editingGeoId)
            <div class="border-b border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h4 class="mb-3 text-xs font-semibold text-neutral-700 dark:text-neutral-300">{{ $editingGeoId === 'new' ? 'Neuer Filter' : 'Filter bearbeiten' }}</h4>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input wire:model="geoRegion" type="text" placeholder="Region/Land *" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="geoSensitivitaet" type="number" step="0.01" placeholder="Sensitivität %" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="geoFiltername" type="text" placeholder="Filtername" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="geoHilfsstrategie" type="text" placeholder="Hilfsstrategie" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <label class="mt-2 flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300">
                    <input wire:model="geoFilterVorhanden" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Filter vorhanden
                </label>
                @error('geoRegion') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-2 flex gap-2">
                    <button wire:click="saveGeo" class="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelGeo" class="rounded border border-neutral-300 px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($geoFilter->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Region</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Sensitivität</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Filter</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($geoFilter as $gf)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $gf->region_land }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gf->sensitivitaet_prozent ? number_format($gf->sensitivitaet_prozent, 1) . '%' : '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gf->validierter_filter_vorhanden ? '✓ ' . ($gf->filtername_quelle ?? '') : '—' }}</td>
                                <td class="flex justify-end gap-2 px-4 py-2 text-right">
                                    <button wire:click="editGeo('{{ $gf->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">✎</button>
                                    <button wire:click="deleteGeo('{{ $gf->id }}')" wire:confirm="Wirklich löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400">×</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-xs text-neutral-500 dark:text-neutral-400">Noch keine geografischen Filter konfiguriert.</p>
        @endif
    </div>

    {{-- Graue Literatur --}}
    <div class="rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Graue Literatur
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $graueLiteratur->count() }})</span>
            </h3>
            <button wire:click="newGrau" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($editingGrauId === 'new' || $editingGrauId)
            <div class="border-b border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <h4 class="mb-3 text-xs font-semibold text-neutral-700 dark:text-neutral-300">{{ $editingGrauId === 'new' ? 'Neue Quelle' : 'Quelle bearbeiten' }}</h4>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input wire:model="grauQuelle" type="text" placeholder="Quelle *" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="grauTyp" type="text" placeholder="Typ" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="grauRelevanz" type="text" placeholder="Relevanz" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="grauUrl" type="url" placeholder="URL" class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="grauSuchpfad" type="text" placeholder="Suchpfad" class="col-span-2 rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <input wire:model="grauAnmerkung" type="text" placeholder="Anmerkung" class="col-span-2 rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                @error('grauQuelle') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-2 flex gap-2">
                    <button wire:click="saveGrau" class="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelGrau" class="rounded border border-neutral-300 px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($graueLiteratur->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Quelle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Typ</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Relevanz</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($graueLiteratur as $gl)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $gl->quelle }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gl->typ ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $gl->relevanz ?? '—' }}</td>
                                <td class="flex justify-end gap-2 px-4 py-2 text-right">
                                    <button wire:click="editGrau('{{ $gl->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">✎</button>
                                    <button wire:click="deleteGrau('{{ $gl->id }}')" wire:confirm="Wirklich löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400">×</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-xs text-neutral-500 dark:text-neutral-400">Noch keine Quellen hinzugefügt.</p>
        @endif
    </div>

    {{-- ═══ Phase Transition Status ═══ --}}
    <div class="mt-4">
        <x-phase-transition-status
            :status="$transitionStatus"
            :phase-nr="3"
            override-action="requestOverride"
        />
        @if ($showOverrideForm)
            <div class="mt-3 w-full">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung für Ausnahme *</label>
                    <textarea wire:model="overrideBegruendung" rows="2"
                        class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"
                        placeholder="Bitte begründe, warum du trotz fehlender Kriterien weitergehen möchtest…"></textarea>
                    @error('overrideBegruendung') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="mt-2 flex gap-2">
                    <button wire:click="confirmOverride" class="rounded bg-amber-600 px-3 py-1 text-xs font-medium text-white hover:bg-amber-700">Bestätigen & fortfahren</button>
                    <button wire:click="$set('showOverrideForm', false)" class="rounded border border-neutral-300 px-3 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300">Abbrechen</button>
                </div>
            </div>
        @endif
    </div>
</div>
