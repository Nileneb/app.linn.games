<?php

use App\Models\Recherche\{Projekt, P5Treffer, P6Qualitaetsbewertung, P6Luckenanalyse};
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    // --- Qualitätsbewertung ---
    public bool $showQbForm = false;
    public ?string $editingQbId = null;
    public string $qbTrefferId = '';
    public string $qbStudientyp = 'RCT';
    public string $qbRobTool = 'RoB2';
    public string $qbGesamturteil = 'nicht_bewertet';
    public string $qbHauptproblem = '';
    public bool $qbImReviewBehalten = true;
    public string $qbAnmerkung = '';
    public string $qbBewertetVon = '';

    // --- Lückenanalyse ---
    public bool $showLaForm = false;
    public ?string $editingLaId = null;
    public string $laFehlenderAspekt = '';
    public string $laFehlenderStudientyp = '';
    public string $laMoeglicheKonsequenz = '';
    public string $laEmpfehlung = '';

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt;
    }

    // ─── Qualitätsbewertung CRUD ─────────────────────────────

    public function newQb(): void { $this->cancelQb(); $this->showQbForm = true; }

    public function saveQb(): void
    {
        $this->validate([
            'qbTrefferId' => 'required|string',
            'qbStudientyp' => 'required|string',
            'qbRobTool' => 'required|string',
            'qbGesamturteil' => 'required|string',
        ]);
        P5Treffer::where('projekt_id', $this->projekt->id)->findOrFail($this->qbTrefferId);
        $data = [
            'treffer_id' => $this->qbTrefferId,
            'studientyp' => $this->qbStudientyp,
            'rob_tool' => $this->qbRobTool,
            'gesamturteil' => $this->qbGesamturteil,
            'hauptproblem' => $this->qbHauptproblem ?: null,
            'im_review_behalten' => $this->qbImReviewBehalten,
            'anmerkung' => $this->qbAnmerkung ?: null,
            'bewertet_von' => $this->qbBewertetVon ?: null,
            'bewertet_am' => now()->toDateString(),
        ];
        if ($this->editingQbId) {
            P6Qualitaetsbewertung::findOrFail($this->editingQbId)->update($data);
        } else {
            P6Qualitaetsbewertung::create($data);
        }
        $this->cancelQb();
    }

    public function editQb(string $id): void
    {
        $r = P6Qualitaetsbewertung::findOrFail($id);
        P5Treffer::where('projekt_id', $this->projekt->id)->findOrFail($r->treffer_id);
        $this->editingQbId = $id;
        $this->qbTrefferId = $r->treffer_id;
        $this->qbStudientyp = $r->studientyp ?? 'RCT';
        $this->qbRobTool = $r->rob_tool ?? 'RoB2';
        $this->qbGesamturteil = $r->gesamturteil ?? 'nicht_bewertet';
        $this->qbHauptproblem = $r->hauptproblem ?? '';
        $this->qbImReviewBehalten = (bool) $r->im_review_behalten;
        $this->qbAnmerkung = $r->anmerkung ?? '';
        $this->qbBewertetVon = $r->bewertet_von ?? '';
        $this->showQbForm = true;
    }

    public function deleteQb(string $id): void
    {
        $r = P6Qualitaetsbewertung::findOrFail($id);
        P5Treffer::where('projekt_id', $this->projekt->id)->findOrFail($r->treffer_id);
        $r->delete();
    }

    public function cancelQb(): void
    {
        $this->showQbForm = false;
        $this->editingQbId = null;
        $this->reset(['qbTrefferId', 'qbStudientyp', 'qbRobTool', 'qbGesamturteil', 'qbHauptproblem', 'qbImReviewBehalten', 'qbAnmerkung', 'qbBewertetVon']);
        $this->qbStudientyp = 'RCT';
        $this->qbRobTool = 'RoB2';
        $this->qbGesamturteil = 'nicht_bewertet';
        $this->qbImReviewBehalten = true;
    }

    // ─── Lückenanalyse CRUD ──────────────────────────────────

    public function newLa(): void { $this->cancelLa(); $this->showLaForm = true; }

    public function saveLa(): void
    {
        $this->validate(['laFehlenderAspekt' => 'required|string']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'fehlender_aspekt' => $this->laFehlenderAspekt,
            'fehlender_studientyp' => $this->laFehlenderStudientyp ?: null,
            'moegliche_konsequenz' => $this->laMoeglicheKonsequenz ?: null,
            'empfehlung' => $this->laEmpfehlung ?: null,
        ];
        if ($this->editingLaId) {
            P6Luckenanalyse::where('projekt_id', $this->projekt->id)->findOrFail($this->editingLaId)->update($data);
        } else {
            P6Luckenanalyse::create($data);
        }
        $this->cancelLa();
    }

    public function editLa(string $id): void
    {
        $r = P6Luckenanalyse::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingLaId = $id;
        $this->laFehlenderAspekt = $r->fehlender_aspekt ?? '';
        $this->laFehlenderStudientyp = $r->fehlender_studientyp ?? '';
        $this->laMoeglicheKonsequenz = $r->moegliche_konsequenz ?? '';
        $this->laEmpfehlung = $r->empfehlung ?? '';
        $this->showLaForm = true;
    }

    public function deleteLa(string $id): void
    {
        P6Luckenanalyse::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelLa(): void
    {
        $this->showLaForm = false;
        $this->editingLaId = null;
        $this->reset(['laFehlenderAspekt', 'laFehlenderStudientyp', 'laMoeglicheKonsequenz', 'laEmpfehlung']);
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        $treffer = P5Treffer::where('projekt_id', $pid)->where('ist_duplikat', false)->get();
        $bewertungen = P6Qualitaetsbewertung::whereIn('treffer_id', $treffer->pluck('id'))->with('treffer')->get();
        return [
            'treffer' => $treffer,
            'bewertungen' => $bewertungen,
            'lucken' => P6Luckenanalyse::where('projekt_id', $pid)->get(),
            'robVerteilung' => $bewertungen->groupBy('gesamturteil')->map->count(),
        ];
    }
}; ?>

<div class="space-y-6">

    {{-- KI-Agent Button --}}
    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="review_agent"
        label="📝 KI: Codierung starten"
        :phase-nr="6"
        :key="'agent-p6-'.$projekt->id"
    />

    {{-- ═══ Qualitätsbewertung (Risk of Bias) ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Qualitätsbewertung (Risk of Bias)
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $bewertungen->count() }})</span>
                {{-- 📊 Visualisierung: RoB Traffic-Light / Summary Chart --}}
            </h3>
            <button wire:click="newQb" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showQbForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer *</label>
                        <select wire:model="qbTrefferId" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="">— Treffer wählen —</option>
                            @foreach ($treffer as $t)
                                <option value="{{ $t->id }}">{{ str()->limit($t->titel ?? $t->record_id, 50) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Studientyp *</label>
                        <select wire:model="qbStudientyp" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="RCT">RCT</option>
                            <option value="nicht_randomisiert">Nicht-randomisiert</option>
                            <option value="qualitativ">Qualitativ</option>
                            <option value="mixed_methods">Mixed Methods</option>
                            <option value="Kohortenstudie">Kohortenstudie</option>
                            <option value="Fallkontrolle">Fallkontrolle</option>
                            <option value="Querschnitt">Querschnitt</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">RoB-Tool *</label>
                        <select wire:model="qbRobTool" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="RoB2">RoB 2</option>
                            <option value="ROBINS-I">ROBINS-I</option>
                            <option value="CASP_qualitativ">CASP (qualitativ)</option>
                            <option value="AMSTAR2">AMSTAR 2</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Gesamturteil *</label>
                        <select wire:model="qbGesamturteil" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="niedrig">Niedrig</option>
                            <option value="moderat">Moderat</option>
                            <option value="hoch">Hoch</option>
                            <option value="kritisch">Kritisch</option>
                            <option value="nicht_bewertet">Nicht bewertet</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Bewertet von</label>
                        <input wire:model="qbBewertetVon" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div class="flex items-end pb-0.5">
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input wire:model="qbImReviewBehalten" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600">
                            Im Review behalten
                        </label>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Hauptproblem</label>
                    <textarea wire:model="qbHauptproblem" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                @error('qbTrefferId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveQb" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelQb" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        {{-- RoB Summary (inline Visualization) --}}
        @if ($robVerteilung->isNotEmpty())
            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">RoB-Verteilung:</span>
                    @php
                        $robColors = [
                            'niedrig' => 'bg-green-500',
                            'moderat' => 'bg-amber-500',
                            'hoch' => 'bg-orange-500',
                            'kritisch' => 'bg-red-500',
                            'nicht_bewertet' => 'bg-neutral-400',
                        ];
                        $total = $robVerteilung->sum();
                    @endphp
                    <div class="flex h-5 flex-1 overflow-hidden rounded">
                        @foreach ($robVerteilung as $urteil => $count)
                            <div class="{{ $robColors[$urteil] ?? 'bg-neutral-400' }} flex items-center justify-center text-xs font-medium text-white" style="width: {{ ($count / $total) * 100 }}%" title="{{ ucfirst(str_replace('_', ' ', $urteil)) }}: {{ $count }}">
                                @if (($count / $total) * 100 > 10) {{ $count }} @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-1 flex flex-wrap gap-3 text-xs text-neutral-500">
                    @foreach ($robVerteilung as $urteil => $count)
                        <span class="flex items-center gap-1">
                            <span class="{{ $robColors[$urteil] ?? 'bg-neutral-400' }} inline-block h-2 w-2 rounded-full"></span>
                            {{ ucfirst(str_replace('_', ' ', $urteil)) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- RoB Traffic-Light Matrix --}}
        @if ($bewertungen->isNotEmpty())
            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <p class="mb-2 text-xs font-semibold text-neutral-600 dark:text-neutral-300">Risk-of-Bias Traffic-Light</p>
                @php
                    $tlColors = [
                        'niedrig'        => 'bg-green-500',
                        'moderat'        => 'bg-amber-400',
                        'hoch'           => 'bg-orange-500',
                        'kritisch'       => 'bg-red-500',
                        'nicht_bewertet' => 'bg-neutral-300 dark:bg-neutral-600',
                    ];
                    $tlLabels = [
                        'niedrig'        => 'Niedrig',
                        'moderat'        => 'Moderat',
                        'hoch'           => 'Hoch',
                        'kritisch'       => 'Kritisch',
                        'nicht_bewertet' => 'N/A',
                    ];
                @endphp
                <div class="space-y-1">
                    @foreach ($bewertungen as $qb)
                        <div class="flex items-center gap-2">
                            <span class="w-44 truncate text-xs text-neutral-700 dark:text-neutral-300" title="{{ $qb->treffer?->titel ?? '—' }}">{{ str()->limit($qb->treffer?->titel ?? '—', 35) }}</span>
                            <span class="{{ $tlColors[$qb->gesamturteil] ?? 'bg-neutral-300' }} inline-flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full" title="{{ $tlLabels[$qb->gesamturteil] ?? $qb->gesamturteil }}">
                                @if ($qb->gesamturteil === 'niedrig')
                                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                @elseif ($qb->gesamturteil === 'kritisch' || $qb->gesamturteil === 'hoch')
                                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                @elseif ($qb->gesamturteil === 'moderat')
                                    <span class="text-[10px] font-bold text-white">~</span>
                                @else
                                    <span class="text-[10px] font-bold text-white">?</span>
                                @endif
                            </span>
                            <span class="text-[10px] text-neutral-400">{{ $tlLabels[$qb->gesamturteil] ?? $qb->gesamturteil }}</span>
                        </div>
                    @endforeach
                </div>
                {{-- Legende --}}
                <div class="mt-2 flex flex-wrap gap-3 text-[10px] text-neutral-500 dark:text-neutral-400">
                    @foreach ($tlColors as $key => $color)
                        <span class="flex items-center gap-1">
                            <span class="{{ $color }} inline-block h-2.5 w-2.5 rounded-full"></span>
                            {{ $tlLabels[$key] }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($bewertungen->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/30">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Treffer</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Studientyp</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Tool</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Urteil</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Im Review</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Akt.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($bewertungen as $qb)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/20">
                                <td class="px-4 py-2 text-neutral-900 dark:text-neutral-100">{{ str()->limit($qb->treffer?->titel ?? '—', 40) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str_replace('_', ' ', $qb->studientyp) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str_replace('_', ' ', $qb->rob_tool) }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'rounded px-1.5 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $qb->gesamturteil === 'niedrig',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $qb->gesamturteil === 'moderat',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' => $qb->gesamturteil === 'hoch',
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $qb->gesamturteil === 'kritisch',
                                        'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400' => $qb->gesamturteil === 'nicht_bewertet',
                                    ])>{{ ucfirst(str_replace('_', ' ', $qb->gesamturteil)) }}</span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if ($qb->im_review_behalten)
                                        <span class="text-green-600">✓</span>
                                    @else
                                        <span class="text-red-500">✗</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editQb('{{ $qb->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteQb('{{ $qb->id }}')" wire:confirm="Bewertung löschen?" class="ml-1 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Qualitätsbewertungen vorhanden.</p>
        @endif
    </div>

    {{-- ═══ Lückenanalyse ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Lückenanalyse
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $lucken->count() }})</span>
                {{-- 📊 Visualisierung: Gap-Map / Evidence Matrix --}}
            </h3>
            <button wire:click="newLa" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showLaForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Fehlender Aspekt *</label>
                    <textarea wire:model="laFehlenderAspekt" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Fehlender Studientyp</label>
                        <input wire:model="laFehlenderStudientyp" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Mögliche Konsequenz</label>
                        <input wire:model="laMoeglicheKonsequenz" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Empfehlung</label>
                        <input wire:model="laEmpfehlung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                @error('laFehlenderAspekt') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveLa" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelLa" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($lucken->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($lucken as $l)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div>
                            <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $l->fehlender_aspekt }}</p>
                            <div class="mt-1 flex flex-wrap gap-3 text-xs text-neutral-500">
                                @if ($l->fehlender_studientyp)
                                    <span>Studientyp: {{ $l->fehlender_studientyp }}</span>
                                @endif
                                @if ($l->moegliche_konsequenz)
                                    <span>Konsequenz: {{ $l->moegliche_konsequenz }}</span>
                                @endif
                                @if ($l->empfehlung)
                                    <span class="text-blue-600 dark:text-blue-400">→ {{ $l->empfehlung }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button wire:click="editLa('{{ $l->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteLa('{{ $l->id }}')" wire:confirm="Lücke löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Lücken identifiziert.</p>
        @endif
    </div>
</div>
