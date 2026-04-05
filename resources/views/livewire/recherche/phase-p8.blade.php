<?php

use App\Livewire\Concerns\HasProjektContext;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{P4Suchstring, P8Suchprotokoll, P8Limitation, P8Reproduzierbarkeitspruefung, P8UpdatePlan};
use Livewire\Volt\Component;

new class extends Component {
    use HasProjektContext;

    // --- Suchprotokoll ---
    public bool $showSpForm = false;
    public ?string $expandedSp = null;
    public ?string $editingSpId = null;
    public string $spSuchstringId = '';
    public string $spDatenbank = '';
    public ?string $spSuchdatum = null;
    public string $spDbVersion = '';
    public string $spSuchstringFinal = '';
    public string $spFilter = '';
    public ?int $spTrefferGesamt = null;
    public ?int $spTrefferEindeutig = null;

    // --- Limitation ---
    public bool $showLimForm = false;
    public ?string $editingLimId = null;
    public string $limTyp = '';
    public string $limBeschreibung = '';
    public string $limAuswirkung = '';

    // --- Reproduzierbarkeit ---
    public bool $showRepForm = false;
    public ?string $editingRepId = null;
    public string $repPruefpunkt = '';
    public ?bool $repErfuellt = null;
    public string $repAnmerkung = '';

    // --- Update-Plan ---
    public bool $showUpForm = false;
    public ?string $editingUpId = null;
    public string $upTyp = 'periodisch';
    public string $upIntervall = '';
    public string $upVerantwortlich = '';
    public string $upTool = '';
    public ?string $upNaechstesUpdate = null;

    // ─── Suchprotokoll CRUD ─────────────────────────────────

    public function newSp(): void { $this->cancelSp(); $this->showSpForm = true; }

    public function saveSp(): void
    {
        $this->validate(['spDatenbank' => 'required|string', 'spSuchstringFinal' => 'required|string']);
        if ($this->spSuchstringId) {
            P4Suchstring::where('projekt_id', $this->projekt->id)->findOrFail($this->spSuchstringId);
        }
        $data = [
            'suchstring_id' => $this->spSuchstringId ?: null,
            'datenbank' => $this->spDatenbank,
            'suchdatum' => $this->spSuchdatum ?: null,
            'db_version' => $this->spDbVersion ?: null,
            'suchstring_final' => $this->spSuchstringFinal,
            'gesetzte_filter' => $this->spFilter ? array_map('trim', explode(',', $this->spFilter)) : null,
            'treffer_gesamt' => $this->spTrefferGesamt,
            'treffer_eindeutig' => $this->spTrefferEindeutig,
        ];
        $data['projekt_id'] = $this->projekt->id;
        if ($this->editingSpId) {
            P8Suchprotokoll::where('projekt_id', $this->projekt->id)->findOrFail($this->editingSpId)->update($data);
        } else {
            P8Suchprotokoll::create($data);
        }
        $this->cancelSp();
    }

    public function editSp(string $id): void
    {
        $r = P8Suchprotokoll::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingSpId = $id;
        $this->spSuchstringId = $r->suchstring_id ?? '';
        $this->spDatenbank = $r->datenbank ?? '';
        $this->spSuchdatum = $r->suchdatum?->format('Y-m-d');
        $this->spDbVersion = $r->db_version ?? '';
        $this->spSuchstringFinal = $r->suchstring_final ?? '';
        $this->spFilter = is_array($r->gesetzte_filter) ? implode(', ', $r->gesetzte_filter) : '';
        $this->spTrefferGesamt = $r->treffer_gesamt;
        $this->spTrefferEindeutig = $r->treffer_eindeutig;
        $this->showSpForm = true;
    }

    public function toggleExpandSp(string $id): void
    {
        $this->expandedSp = $this->expandedSp === $id ? null : $id;
    }

    public function deleteSp(string $id): void
    {
        P8Suchprotokoll::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelSp(): void
    {
        $this->showSpForm = false;
        $this->editingSpId = null;
        $this->reset(['spSuchstringId', 'spDatenbank', 'spSuchdatum', 'spDbVersion', 'spSuchstringFinal', 'spFilter', 'spTrefferGesamt', 'spTrefferEindeutig']);
    }

    // ─── Limitation CRUD ─────────────────────────────────────

    public function newLim(): void { $this->cancelLim(); $this->showLimForm = true; }

    public function saveLim(): void
    {
        $this->validate(['limTyp' => 'required|string']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'limitationstyp' => $this->limTyp,
            'beschreibung' => $this->limBeschreibung ?: null,
            'auswirkung_auf_vollstaendigkeit' => $this->limAuswirkung ?: null,
        ];
        if ($this->editingLimId) {
            P8Limitation::where('projekt_id', $this->projekt->id)->findOrFail($this->editingLimId)->update($data);
        } else {
            P8Limitation::create($data);
        }
        $this->cancelLim();
    }

    public function editLim(string $id): void
    {
        $r = P8Limitation::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingLimId = $id;
        $this->limTyp = $r->limitationstyp ?? '';
        $this->limBeschreibung = $r->beschreibung ?? '';
        $this->limAuswirkung = $r->auswirkung_auf_vollstaendigkeit ?? '';
        $this->showLimForm = true;
    }

    public function deleteLim(string $id): void
    {
        P8Limitation::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelLim(): void
    {
        $this->showLimForm = false;
        $this->editingLimId = null;
        $this->reset(['limTyp', 'limBeschreibung', 'limAuswirkung']);
    }

    // ─── Reproduzierbarkeit CRUD ─────────────────────────────

    public function newRep(): void { $this->cancelRep(); $this->showRepForm = true; }

    public function saveRep(): void
    {
        $this->validate(['repPruefpunkt' => 'required|string']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'pruefpunkt' => $this->repPruefpunkt,
            'erfuellt' => $this->repErfuellt,
            'anmerkung' => $this->repAnmerkung ?: null,
        ];
        if ($this->editingRepId) {
            P8Reproduzierbarkeitspruefung::where('projekt_id', $this->projekt->id)->findOrFail($this->editingRepId)->update($data);
        } else {
            P8Reproduzierbarkeitspruefung::create($data);
        }
        $this->cancelRep();
    }

    public function editRep(string $id): void
    {
        $r = P8Reproduzierbarkeitspruefung::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingRepId = $id;
        $this->repPruefpunkt = $r->pruefpunkt ?? '';
        $this->repErfuellt = $r->erfuellt;
        $this->repAnmerkung = $r->anmerkung ?? '';
        $this->showRepForm = true;
    }

    public function deleteRep(string $id): void
    {
        P8Reproduzierbarkeitspruefung::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelRep(): void
    {
        $this->showRepForm = false;
        $this->editingRepId = null;
        $this->reset(['repPruefpunkt', 'repErfuellt', 'repAnmerkung']);
    }

    // ─── Update-Plan CRUD ────────────────────────────────────

    public function newUp(): void { $this->cancelUp(); $this->showUpForm = true; }

    public function saveUp(): void
    {
        $data = [
            'projekt_id' => $this->projekt->id,
            'update_typ' => $this->upTyp,
            'intervall' => $this->upIntervall ?: null,
            'verantwortlich' => $this->upVerantwortlich ?: null,
            'tool' => $this->upTool ?: null,
            'naechstes_update' => $this->upNaechstesUpdate ?: null,
        ];
        if ($this->editingUpId) {
            P8UpdatePlan::where('projekt_id', $this->projekt->id)->findOrFail($this->editingUpId)->update($data);
        } else {
            P8UpdatePlan::create($data);
        }
        $this->cancelUp();
    }

    public function editUp(string $id): void
    {
        $r = P8UpdatePlan::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingUpId = $id;
        $this->upTyp = $r->update_typ ?? 'periodisch';
        $this->upIntervall = $r->intervall ?? '';
        $this->upVerantwortlich = $r->verantwortlich ?? '';
        $this->upTool = $r->tool ?? '';
        $this->upNaechstesUpdate = $r->naechstes_update?->format('Y-m-d');
        $this->showUpForm = true;
    }

    public function deleteUp(string $id): void
    {
        P8UpdatePlan::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelUp(): void
    {
        $this->showUpForm = false;
        $this->editingUpId = null;
        $this->reset(['upTyp', 'upIntervall', 'upVerantwortlich', 'upTool', 'upNaechstesUpdate']);
        $this->upTyp = 'periodisch';
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        $suchstrings = rescue(fn () => P4Suchstring::where('projekt_id', $pid)->get(), collect());
        $suchstringIds = $suchstrings->pluck('id');
        return [
            'suchprotokolle' => rescue(fn () => P8Suchprotokoll::where('projekt_id', $pid)->with('suchstring')->get(), collect()),
            'limitationen' => rescue(fn () => P8Limitation::where('projekt_id', $pid)->get(), collect()),
            'reproduzierbarkeit' => rescue(fn () => P8Reproduzierbarkeitspruefung::where('projekt_id', $pid)->get(), collect()),
            'updatePlaene' => rescue(fn () => P8UpdatePlan::where('projekt_id', $pid)->get(), collect()),
            'suchstrings' => $suchstrings,
            'latestAgentResult' => rescue(fn () => PhaseAgentResult::where('projekt_id', $pid)->where('phase_nr', 8)->whereNotNull('content')->latest()->first()),
        ];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>

    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="review_agent"
        label="📋 KI: Dokumentation finalisieren"
        :phase-nr="8"
        :key="'agent-p8-'.$projekt->id"
    />
    {{-- KI-Vorschlag (letztes Agent-Ergebnis) --}}
    <x-agent-suggestion :result="$latestAgentResult" />


    {{-- ═══ Suchprotokoll ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Suchprotokoll
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $suchprotokolle->count() }})</span>
                {{-- 📊 Visualisierung: Treffer-pro-Datenbank Balkendiagramm --}}
            </h3>
            <button wire:click="newSp" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        <x-crud.form :visible="$showSpForm" save-action="saveSp" cancel-action="cancelSp"
            title="Suchprotokoll {{ $editingSpId ? 'bearbeiten' : 'hinzufügen' }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Datenbank *</label>
                    <input wire:model="spDatenbank" type="text" placeholder="z.B. PubMed" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    @error('spDatenbank') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchdatum</label>
                    <input wire:model="spSuchdatum" type="date" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">DB-Version</label>
                    <input wire:model="spDbVersion" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Verknüpfter Suchstring</label>
                    <select wire:model="spSuchstringId" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="">— keiner —</option>
                        @foreach ($suchstrings as $ss)
                            <option value="{{ $ss->id }}">{{ $ss->datenbank }} {{ $ss->version ? '('.$ss->version.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchstring (final) *</label>
                <textarea wire:model="spSuchstringFinal" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm font-mono dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                @error('spSuchstringFinal') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer gesamt</label>
                    <input wire:model="spTrefferGesamt" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer eindeutig</label>
                    <input wire:model="spTrefferEindeutig" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Filter <span class="font-normal text-neutral-400">(kommagetrennt)</span></label>
                <input wire:model="spFilter" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
            </div>
        </x-crud.form>

        {{-- Treffer-pro-Datenbank Balkendiagramm --}}
        @if ($suchprotokolle->isNotEmpty())
            @php
                $dbGroups = $suchprotokolle->groupBy('datenbank')->map(function ($items, $db) {
                    return [
                        'gesamt'    => $items->sum('treffer_gesamt'),
                        'eindeutig' => $items->sum('treffer_eindeutig'),
                    ];
                })->sortByDesc('gesamt');
                $barMax = max($dbGroups->max('gesamt'), 1);
            @endphp
            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-xs font-semibold text-neutral-600 dark:text-neutral-300">Treffer pro Datenbank</p>
                    <div class="flex items-center gap-3 text-[10px] text-neutral-400 dark:text-neutral-500">
                        <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-blue-500"></span>Gesamt</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-indigo-400"></span>Eindeutig</span>
                    </div>
                </div>
                <div class="space-y-1.5">
                    @foreach ($dbGroups as $db => $vals)
                        <div class="flex items-center gap-2">
                            <span class="w-28 truncate text-right text-xs text-neutral-600 dark:text-neutral-400" title="{{ $db }}">{{ $db }}</span>
                            <div class="flex-1 space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <div class="h-3.5 rounded bg-blue-500 transition-all" style="width: {{ max(($vals['gesamt'] / $barMax) * 100, 2) }}%"></div>
                                    <span class="text-[10px] font-medium text-neutral-500 dark:text-neutral-400">{{ number_format($vals['gesamt']) }}</span>
                                </div>
                                @if ($vals['eindeutig'])
                                    <div class="flex items-center gap-1.5">
                                        <div class="h-3.5 rounded bg-indigo-400 transition-all" style="width: {{ max(($vals['eindeutig'] / $barMax) * 100, 2) }}%"></div>
                                        <span class="text-[10px] font-medium text-neutral-500 dark:text-neutral-400">{{ number_format($vals['eindeutig']) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($suchprotokolle->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Datenbank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Suchstring</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Treffer</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Akt.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($suchprotokolle as $sp)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/20">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $sp->datenbank }}
                                    @if ($sp->db_version) <span class="text-xs text-neutral-400">({{ $sp->db_version }})</span> @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $sp->suchdatum?->format('d.m.Y') ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($sp->suchstring_final)
                                        <button wire:click="toggleExpandSp('{{ $sp->id }}')" class="font-mono text-xs text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-neutral-100">
                                            {{ str()->limit($sp->suchstring_final, 60) }}
                                            <span class="ml-1 text-[10px] text-neutral-400">{{ $expandedSp === $sp->id ? '▲' : '▼' }}</span>
                                        </button>
                                        @if ($expandedSp === $sp->id)
                                            <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded bg-neutral-100 p-2 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $sp->suchstring_final }}</pre>
                                        @endif
                                    @else
                                        <span class="text-xs text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-neutral-600 dark:text-neutral-300">
                                    {{ $sp->treffer_gesamt !== null ? number_format($sp->treffer_gesamt) : '—' }}
                                    @if ($sp->treffer_eindeutig !== null)
                                        <span class="text-xs text-neutral-400">({{ number_format($sp->treffer_eindeutig) }} unique)</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editSp('{{ $sp->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteSp('{{ $sp->id }}')" wire:confirm="Suchprotokoll löschen?" class="ml-1 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 00-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch kein Suchprotokoll dokumentiert.</p>
        @endif
    </div>

    {{-- ═══ Limitationen ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Limitationen
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $limitationen->count() }})</span>
            </h3>
            <button wire:click="newLim" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        <x-crud.form :visible="$showLimForm" save-action="saveLim" cancel-action="cancelLim"
            title="Limitation {{ $editingLimId ? 'bearbeiten' : 'hinzufügen' }}">
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Limitationstyp *</label>
                <input wire:model="limTyp" type="text" placeholder="z.B. Sprachbias, Zeitliche Einschränkung" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                @error('limTyp') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Beschreibung</label>
                <textarea wire:model="limBeschreibung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Auswirkung auf Vollständigkeit</label>
                <textarea wire:model="limAuswirkung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </div>
        </x-crud.form>

        @if ($limitationen->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($limitationen as $lim)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div>
                            <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $lim->limitationstyp }}</span>
                            @if ($lim->beschreibung)
                                <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $lim->beschreibung }}</p>
                            @endif
                            @if ($lim->auswirkung_auf_vollstaendigkeit)
                                <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">Auswirkung: {{ $lim->auswirkung_auf_vollstaendigkeit }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button wire:click="editLim('{{ $lim->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteLim('{{ $lim->id }}')" wire:confirm="Limitation löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 00-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Limitationen dokumentiert.</p>
        @endif
    </div>

    {{-- ═══ Reproduzierbarkeit ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Reproduzierbarkeits-Checkliste
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $reproduzierbarkeit->count() }})</span>
                {{-- 📊 Visualisierung: Fortschritts-Ring — Erfüllte/Offene Prüfpunkte --}}
            </h3>
            <button wire:click="newRep" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        <x-crud.form :visible="$showRepForm" save-action="saveRep" cancel-action="cancelRep"
            title="Reproduzierbarkeit {{ $editingRepId ? 'bearbeiten' : 'hinzufügen' }}">
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Prüfpunkt *</label>
                <input wire:model="repPruefpunkt" type="text" placeholder="z.B. Suchstring dokumentiert, PRISMA-Diagramm erstellt" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                @error('repPruefpunkt') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Status</label>
                    <select wire:model="repErfuellt" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="">— Offen —</option>
                        <option value="1">Erfüllt</option>
                        <option value="0">Nicht erfüllt</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkung</label>
                    <input wire:model="repAnmerkung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
            </div>
        </x-crud.form>

        @if ($reproduzierbarkeit->isNotEmpty())
            @php
                $erfuellt = $reproduzierbarkeit->where('erfuellt', true)->count();
                $total = $reproduzierbarkeit->count();
                $pct = $total > 0 ? round(($erfuellt / $total) * 100) : 0;
            @endphp
            {{-- Progress bar --}}
            <div class="border-b border-neutral-100 px-4 py-2 dark:border-neutral-700/50">
                <div class="flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ $erfuellt }}/{{ $total }} ({{ $pct }}%)</span>
                </div>
            </div>
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($reproduzierbarkeit as $rep)
                    <div class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-3">
                            @if ($rep->erfuellt === true)
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                </span>
                            @elseif ($rep->erfuellt === false)
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                                </span>
                            @else
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-700 dark:text-neutral-500">
                                    <span class="text-xs">?</span>
                                </span>
                            @endif
                            <div>
                                <span class="text-sm text-neutral-900 dark:text-neutral-100">{{ $rep->pruefpunkt }}</span>
                                @if ($rep->anmerkung)
                                    <span class="ml-2 text-xs text-neutral-500">{{ $rep->anmerkung }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button wire:click="editRep('{{ $rep->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteRep('{{ $rep->id }}')" wire:confirm="Prüfpunkt löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 00-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Prüfpunkte definiert.</p>
        @endif
    </div>

    {{-- ═══ Update-Plan ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Update-Plan
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $updatePlaene->count() }})</span>
            </h3>
            <button wire:click="newUp" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        <x-crud.form :visible="$showUpForm" save-action="saveUp" cancel-action="cancelUp"
            title="Update-Plan {{ $editingUpId ? 'bearbeiten' : 'hinzufügen' }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Typ</label>
                    <select wire:model="upTyp" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="periodisch">Periodisch</option>
                        <option value="living_review">Living Review</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Intervall</label>
                    <input wire:model="upIntervall" type="text" placeholder="z.B. 6 Monate" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Nächstes Update</label>
                    <input wire:model="upNaechstesUpdate" type="date" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Verantwortlich</label>
                    <input wire:model="upVerantwortlich" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Tool</label>
                <input wire:model="upTool" type="text" placeholder="z.B. PubMed Alerts" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
            </div>
        </x-crud.form>

        @if ($updatePlaene->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($updatePlaene as $up)
                    <div class="flex items-center justify-between p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $up->update_typ === 'living_review' ? 'Living Review' : 'Periodisch' }}</span>
                                @if ($up->intervall)
                                    <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $up->intervall }}</span>
                                @endif
                                @if ($up->naechstes_update)
                                    <span class="text-xs text-neutral-500">Nächstes: {{ $up->naechstes_update->format('d.m.Y') }}</span>
                                @endif
                            </div>
                            @if ($up->verantwortlich || $up->tool)
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ $up->verantwortlich }}{{ $up->verantwortlich && $up->tool ? ' · ' : '' }}{{ $up->tool }}
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button wire:click="editUp('{{ $up->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteUp('{{ $up->id }}')" wire:confirm="Update-Plan löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 00-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch kein Update-Plan definiert.</p>
        @endif
    </div>
</div>
