<?php

use App\Models\Recherche\{Projekt, P5Treffer, P5ScreeningKriterium, P5ScreeningEntscheidung, P5ToolEntscheidung, P5PrismaZahlen};
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    // --- PRISMA Zahlen ---
    public bool $showPrismaForm = false;
    public ?string $editingPrismaId = null;
    public ?int $prismaIdentGesamt = null;
    public ?int $prismaDatenbankTreffer = null;
    public ?int $prismaGraueLit = null;
    public ?int $prismaNachDedup = null;
    public ?int $prismaAusgeschlossenL1 = null;
    public ?int $prismaVolltextGeprueft = null;
    public ?int $prismaAusgeschlossenL2 = null;
    public ?int $prismaEingeschlossen = null;

    // --- Screening-Kriterien ---
    public bool $showSkForm = false;
    public ?string $editingSkId = null;
    public string $skLevel = 'L1_titel_abstract';
    public string $skTyp = 'einschluss';
    public string $skBeschreibung = '';
    public string $skBeispiel = '';

    // --- Screening-Tool ---
    public bool $showToolForm = false;
    public ?string $editingToolId = null;
    public string $toolName = 'Rayyan';
    public bool $toolGewaehlt = false;
    public string $toolBegruendung = '';

    // --- Treffer Screening ---
    public bool $showScreenForm = false;
    public string $screenTrefferId = '';
    public string $screenLevel = 'L1_titel_abstract';
    public string $screenEntscheidung = 'eingeschlossen';
    public string $screenAusschlussgrund = '';
    public string $screenReviewer = '';
    public string $screenAnmerkung = '';

    // --- Filter ---
    public string $trefferFilter = 'alle';

    public function mount(Projekt $projekt): void
    {
        abort_unless($projekt->user_id === Auth::id(), 403);
        $this->projekt = $projekt;
    }

    // ─── PRISMA CRUD ─────────────────────────────────────────

    public function newPrisma(): void { $this->cancelPrisma(); $this->showPrismaForm = true; }

    public function savePrisma(): void
    {
        $data = [
            'projekt_id' => $this->projekt->id,
            'identifiziert_gesamt' => $this->prismaIdentGesamt,
            'davon_datenbank_treffer' => $this->prismaDatenbankTreffer,
            'davon_graue_literatur' => $this->prismaGraueLit,
            'nach_deduplizierung' => $this->prismaNachDedup,
            'ausgeschlossen_l1' => $this->prismaAusgeschlossenL1,
            'volltext_geprueft' => $this->prismaVolltextGeprueft,
            'ausgeschlossen_l2' => $this->prismaAusgeschlossenL2,
            'eingeschlossen_final' => $this->prismaEingeschlossen,
        ];
        if ($this->editingPrismaId) {
            P5PrismaZahlen::where('projekt_id', $this->projekt->id)->findOrFail($this->editingPrismaId)->update($data);
        } else {
            P5PrismaZahlen::create($data);
        }
        $this->cancelPrisma();
    }

    public function editPrisma(string $id): void
    {
        $r = P5PrismaZahlen::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingPrismaId = $id;
        $this->prismaIdentGesamt = $r->identifiziert_gesamt;
        $this->prismaDatenbankTreffer = $r->davon_datenbank_treffer;
        $this->prismaGraueLit = $r->davon_graue_literatur;
        $this->prismaNachDedup = $r->nach_deduplizierung;
        $this->prismaAusgeschlossenL1 = $r->ausgeschlossen_l1;
        $this->prismaVolltextGeprueft = $r->volltext_geprueft;
        $this->prismaAusgeschlossenL2 = $r->ausgeschlossen_l2;
        $this->prismaEingeschlossen = $r->eingeschlossen_final;
        $this->showPrismaForm = true;
    }

    public function deletePrisma(string $id): void
    {
        P5PrismaZahlen::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelPrisma(): void
    {
        $this->showPrismaForm = false;
        $this->editingPrismaId = null;
        $this->reset(['prismaIdentGesamt', 'prismaDatenbankTreffer', 'prismaGraueLit', 'prismaNachDedup', 'prismaAusgeschlossenL1', 'prismaVolltextGeprueft', 'prismaAusgeschlossenL2', 'prismaEingeschlossen']);
    }

    // ─── Screening-Kriterien CRUD ────────────────────────────

    public function newSk(): void { $this->cancelSk(); $this->showSkForm = true; }

    public function saveSk(): void
    {
        $this->validate(['skBeschreibung' => 'required|string']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'level' => $this->skLevel,
            'kriterium_typ' => $this->skTyp,
            'beschreibung' => $this->skBeschreibung,
            'beispiel' => $this->skBeispiel ?: null,
        ];
        if ($this->editingSkId) {
            P5ScreeningKriterium::where('projekt_id', $this->projekt->id)->findOrFail($this->editingSkId)->update($data);
        } else {
            P5ScreeningKriterium::create($data);
        }
        $this->cancelSk();
    }

    public function editSk(string $id): void
    {
        $r = P5ScreeningKriterium::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingSkId = $id;
        $this->skLevel = $r->level ?? 'L1_titel_abstract';
        $this->skTyp = $r->kriterium_typ ?? 'einschluss';
        $this->skBeschreibung = $r->beschreibung ?? '';
        $this->skBeispiel = $r->beispiel ?? '';
        $this->showSkForm = true;
    }

    public function deleteSk(string $id): void
    {
        P5ScreeningKriterium::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelSk(): void
    {
        $this->showSkForm = false;
        $this->editingSkId = null;
        $this->reset(['skLevel', 'skTyp', 'skBeschreibung', 'skBeispiel']);
        $this->skLevel = 'L1_titel_abstract';
        $this->skTyp = 'einschluss';
    }

    // ─── Tool CRUD ───────────────────────────────────────────

    public function newTool(): void { $this->cancelTool(); $this->showToolForm = true; }

    public function saveTool(): void
    {
        $data = [
            'projekt_id' => $this->projekt->id,
            'tool' => $this->toolName,
            'gewaehlt' => $this->toolGewaehlt,
            'begruendung' => $this->toolBegruendung ?: null,
        ];
        if ($this->editingToolId) {
            P5ToolEntscheidung::where('projekt_id', $this->projekt->id)->findOrFail($this->editingToolId)->update($data);
        } else {
            P5ToolEntscheidung::create($data);
        }
        $this->cancelTool();
    }

    public function editTool(string $id): void
    {
        $r = P5ToolEntscheidung::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingToolId = $id;
        $this->toolName = $r->tool ?? 'Rayyan';
        $this->toolGewaehlt = (bool) $r->gewaehlt;
        $this->toolBegruendung = $r->begruendung ?? '';
        $this->showToolForm = true;
    }

    public function deleteTool(string $id): void
    {
        P5ToolEntscheidung::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelTool(): void
    {
        $this->showToolForm = false;
        $this->editingToolId = null;
        $this->reset(['toolName', 'toolGewaehlt', 'toolBegruendung']);
        $this->toolName = 'Rayyan';
    }

    // ─── Screening-Entscheidung ──────────────────────────────

    public function openScreen(string $trefferId): void
    {
        $this->cancelScreen();
        $this->screenTrefferId = $trefferId;
        $this->showScreenForm = true;
    }

    public function saveScreen(): void
    {
        $this->validate([
            'screenTrefferId' => 'required|string',
            'screenEntscheidung' => 'required|string',
        ]);
        P5Treffer::where('projekt_id', $this->projekt->id)->findOrFail($this->screenTrefferId);
        P5ScreeningEntscheidung::create([
            'treffer_id' => $this->screenTrefferId,
            'level' => $this->screenLevel,
            'entscheidung' => $this->screenEntscheidung,
            'ausschlussgrund' => $this->screenAusschlussgrund ?: null,
            'reviewer' => $this->screenReviewer ?: null,
            'datum' => now()->toDateString(),
            'anmerkung' => $this->screenAnmerkung ?: null,
        ]);
        $this->cancelScreen();
    }

    public function cancelScreen(): void
    {
        $this->showScreenForm = false;
        $this->reset(['screenTrefferId', 'screenLevel', 'screenEntscheidung', 'screenAusschlussgrund', 'screenReviewer', 'screenAnmerkung']);
        $this->screenLevel = 'L1_titel_abstract';
        $this->screenEntscheidung = 'eingeschlossen';
    }

    public function deleteScreen(string $id): void
    {
        $e = P5ScreeningEntscheidung::findOrFail($id);
        P5Treffer::where('projekt_id', $this->projekt->id)->findOrFail($e->treffer_id);
        $e->delete();
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        $trefferQuery = P5Treffer::where('projekt_id', $pid)->with('screeningEntscheidungen');
        if ($this->trefferFilter === 'duplikate') {
            $trefferQuery->where('ist_duplikat', true);
        } elseif ($this->trefferFilter === 'unique') {
            $trefferQuery->where('ist_duplikat', false);
        }
        return [
            'prismaZahlen' => P5PrismaZahlen::where('projekt_id', $pid)->first(),
            'screeningKriterien' => P5ScreeningKriterium::where('projekt_id', $pid)->get(),
            'treffer' => $trefferQuery->orderBy('record_id')->get(),
            'trefferGesamt' => P5Treffer::where('projekt_id', $pid)->count(),
            'trefferDuplikate' => P5Treffer::where('projekt_id', $pid)->where('ist_duplikat', true)->count(),
            'tools' => P5ToolEntscheidung::where('projekt_id', $pid)->get(),
        ];
    }
}; ?>

<div class="space-y-6">

    {{-- ═══ PRISMA Flowchart Zahlen ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                PRISMA Flowchart
                {{-- 📊 Visualisierung: PRISMA-Flussdiagramm als interaktives Sankey/Funnel-Diagramm --}}
            </h3>
            @if (!$prismaZahlen)
                <button wire:click="newPrisma" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Anlegen</button>
            @endif
        </div>

        @if ($showPrismaForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Identifiziert gesamt</label>
                        <input wire:model="prismaIdentGesamt" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Datenbank-Treffer</label>
                        <input wire:model="prismaDatenbankTreffer" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Graue Literatur</label>
                        <input wire:model="prismaGraueLit" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Nach Deduplizierung</label>
                        <input wire:model="prismaNachDedup" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Ausgeschlossen L1</label>
                        <input wire:model="prismaAusgeschlossenL1" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Volltext geprüft</label>
                        <input wire:model="prismaVolltextGeprueft" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Ausgeschlossen L2</label>
                        <input wire:model="prismaAusgeschlossenL2" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Eingeschlossen final</label>
                        <input wire:model="prismaEingeschlossen" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button wire:click="savePrisma" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelPrisma" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($prismaZahlen)
            <div class="p-4">
                {{-- PRISMA Funnel Visualization --}}
                <div class="flex flex-col items-center gap-1">
                    @php
                        $steps = [
                            ['label' => 'Identifiziert', 'value' => $prismaZahlen->identifiziert_gesamt, 'color' => 'bg-blue-500'],
                            ['label' => 'Datenbank-Treffer', 'value' => $prismaZahlen->davon_datenbank_treffer, 'color' => 'bg-blue-400'],
                            ['label' => 'Graue Literatur', 'value' => $prismaZahlen->davon_graue_literatur, 'color' => 'bg-blue-300'],
                            ['label' => 'Nach Deduplizierung', 'value' => $prismaZahlen->nach_deduplizierung, 'color' => 'bg-indigo-500'],
                            ['label' => 'Ausgeschlossen L1', 'value' => $prismaZahlen->ausgeschlossen_l1, 'color' => 'bg-red-400'],
                            ['label' => 'Volltext geprüft', 'value' => $prismaZahlen->volltext_geprueft, 'color' => 'bg-amber-500'],
                            ['label' => 'Ausgeschlossen L2', 'value' => $prismaZahlen->ausgeschlossen_l2, 'color' => 'bg-red-400'],
                            ['label' => 'Eingeschlossen', 'value' => $prismaZahlen->eingeschlossen_final, 'color' => 'bg-green-500'],
                        ];
                        $maxVal = max(array_filter(array_column($steps, 'value'), fn($v) => $v !== null) ?: [1]);
                    @endphp
                    @foreach ($steps as $step)
                        @if ($step['value'] !== null)
                            <div class="flex w-full items-center gap-3">
                                <span class="w-40 text-right text-xs text-neutral-600 dark:text-neutral-400">{{ $step['label'] }}</span>
                                <div class="relative h-6 flex-1 overflow-hidden rounded bg-neutral-100 dark:bg-neutral-800">
                                    <div class="{{ $step['color'] }} flex h-full items-center justify-end rounded pr-2 text-xs font-medium text-white transition-all duration-500" style="width: {{ $maxVal > 0 ? max(($step['value'] / $maxVal) * 100, 3) : 3 }}%">
                                        {{ number_format($step['value']) }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <button wire:click="editPrisma('{{ $prismaZahlen->id }}')" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Bearbeiten</button>
                    <button wire:click="deletePrisma('{{ $prismaZahlen->id }}')" wire:confirm="PRISMA-Zahlen löschen?" class="text-sm text-red-500 hover:underline dark:text-red-400">Löschen</button>
                </div>
            </div>
        @else
            @if (!$showPrismaForm)
                <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine PRISMA-Zahlen erfasst.</p>
            @endif
        @endif
    </div>

    {{-- ═══ Screening-Kriterien ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Screening-Kriterien
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $screeningKriterien->count() }})</span>
            </h3>
            <button wire:click="newSk" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showSkForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Level *</label>
                        <select wire:model="skLevel" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="L1_titel_abstract">L1 — Titel/Abstract</option>
                            <option value="L2_volltext">L2 — Volltext</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Typ *</label>
                        <select wire:model="skTyp" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="einschluss">Einschluss</option>
                            <option value="ausschluss">Ausschluss</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Beschreibung *</label>
                    <textarea wire:model="skBeschreibung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Beispiel</label>
                    <input wire:model="skBeispiel" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                @error('skBeschreibung') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveSk" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelSk" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($screeningKriterien->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($screeningKriterien as $sk)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'rounded px-1.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $sk->kriterium_typ === 'einschluss',
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $sk->kriterium_typ === 'ausschluss',
                                ])>{{ $sk->kriterium_typ === 'einschluss' ? 'Einschluss' : 'Ausschluss' }}</span>
                                <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ str_replace('_', ' ', $sk->level) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $sk->beschreibung }}</p>
                            @if ($sk->beispiel)
                                <p class="mt-0.5 text-xs text-neutral-500 italic">Bsp: {{ $sk->beispiel }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button wire:click="editSk('{{ $sk->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteSk('{{ $sk->id }}')" wire:confirm="Kriterium löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Screening-Kriterien definiert.</p>
        @endif
    </div>

    {{-- ═══ Tool-Entscheidung ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Screening-Tool
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $tools->count() }})</span>
            </h3>
            <button wire:click="newTool" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showToolForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Tool *</label>
                        <select wire:model="toolName" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="Rayyan">Rayyan</option>
                            <option value="Covidence">Covidence</option>
                            <option value="EPPI_Reviewer">EPPI-Reviewer</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2 pb-0.5">
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input wire:model="toolGewaehlt" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600">
                            Gewählt
                        </label>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung</label>
                    <textarea wire:model="toolBegruendung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveTool" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelTool" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($tools->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($tools as $t)
                    <div class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ str_replace('_', ' ', $t->tool) }}</span>
                            @if ($t->gewaehlt)
                                <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">Gewählt</span>
                            @endif
                            @if ($t->begruendung)
                                <span class="text-xs text-neutral-500">— {{ str()->limit($t->begruendung, 60) }}</span>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            <button wire:click="editTool('{{ $t->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteTool('{{ $t->id }}')" wire:confirm="Tool-Entscheidung löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch kein Screening-Tool bewertet.</p>
        @endif
    </div>

    {{-- ═══ Treffer-Übersicht mit Screening ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Treffer
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $trefferGesamt }} gesamt, {{ $trefferDuplikate }} Duplikate)</span>
                {{-- 📊 Visualisierung: Donut-Chart Einschluss/Ausschluss/Unklar --}}
            </h3>
            <div class="flex gap-1">
                <select wire:model.live="trefferFilter" class="rounded border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    <option value="alle">Alle</option>
                    <option value="unique">Ohne Duplikate</option>
                    <option value="duplikate">Nur Duplikate</option>
                </select>
            </div>
        </div>

        @if ($showScreenForm)
            <div class="border-b border-neutral-200 bg-amber-50/50 p-4 dark:border-neutral-700 dark:bg-amber-950/20">
                <h4 class="mb-2 text-sm font-medium text-neutral-900 dark:text-neutral-100">Screening-Entscheidung</h4>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Level</label>
                        <select wire:model="screenLevel" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="L1_titel_abstract">L1 — Titel/Abstract</option>
                            <option value="L2_volltext">L2 — Volltext</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Entscheidung *</label>
                        <select wire:model="screenEntscheidung" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="eingeschlossen">Eingeschlossen</option>
                            <option value="ausgeschlossen">Ausgeschlossen</option>
                            <option value="unklar">Unklar</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Reviewer</label>
                        <input wire:model="screenReviewer" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Ausschlussgrund</label>
                    <input wire:model="screenAusschlussgrund" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveScreen" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelScreen" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($treffer->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($treffer as $t)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ str()->limit($t->titel ?? $t->record_id, 80) }}</span>
                                    @if ($t->ist_duplikat)
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Duplikat</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ $t->autoren ?? 'Unbekannt' }}
                                    @if ($t->jahr) · {{ $t->jahr }} @endif
                                    @if ($t->journal) · {{ $t->journal }} @endif
                                    @if ($t->datenbank_quelle) · {{ $t->datenbank_quelle }} @endif
                                </p>
                                @if ($t->screeningEntscheidungen->isNotEmpty())
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @foreach ($t->screeningEntscheidungen as $se)
                                            <span @class([
                                                'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs',
                                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $se->entscheidung === 'eingeschlossen',
                                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $se->entscheidung === 'ausgeschlossen',
                                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $se->entscheidung === 'unklar',
                                            ])>
                                                {{ str_replace('_', ' ', $se->level) }}: {{ $se->entscheidung }}
                                                <button wire:click="deleteScreen('{{ $se->id }}')" wire:confirm="Entscheidung löschen?" class="ml-0.5 opacity-60 hover:opacity-100">&times;</button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <button wire:click="openScreen('{{ $t->id }}')" class="shrink-0 rounded bg-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-300 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600">Screenen</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Treffer importiert. Treffer werden vom KI-Agenten angelegt.</p>
        @endif
    </div>
</div>
