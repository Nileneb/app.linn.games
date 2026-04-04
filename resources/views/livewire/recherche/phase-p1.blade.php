<?php

use App\Models\Recherche\{Projekt, P1Strukturmodellwahl, P1Komponente, P1Kriterium, P1Warnsignal};
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    // --- Strukturmodellwahl ---
    public bool $showSmwForm = false;
    public ?string $editingSmwId = null;
    public string $smwModell = '';
    public bool $smwGewaehlt = false;
    public string $smwBegruendung = '';

    // --- Komponente ---
    public bool $showKompForm = false;
    public ?string $editingKompId = null;
    public string $kompModell = '';
    public string $kompKuerzel = '';
    public string $kompLabel = '';
    public string $kompSynonyme = '';
    public string $kompBegriffDe = '';
    public string $kompEnglisch = '';
    public string $kompMesh = '';
    public string $kompThesaurus = '';
    public string $kompAnmerkungen = '';

    // --- Kriterium ---
    public bool $showKritForm = false;
    public ?string $editingKritId = null;
    public string $kritTyp = 'einschluss';
    public string $kritBeschreibung = '';
    public string $kritBegruendung = '';
    public string $kritQuellbezug = '';

    // --- Warnsignal ---
    public bool $showWarnForm = false;
    public ?string $editingWarnId = null;
    public int $warnLfdNr = 1;
    public string $warnWarnsignal = '';
    public string $warnAuswirkung = '';
    public string $warnHandlungsempfehlung = '';

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt;
    }

    // ─── Strukturmodellwahl CRUD ─────────────────────────────

    public function newSmw(): void
    {
        $this->cancelSmw();
        $this->showSmwForm = true;
    }

    public function saveSmw(): void
    {
        $this->validate([
            'smwModell' => 'required|string|max:100',
        ]);

        $data = [
            'projekt_id' => $this->projekt->id,
            'modell' => $this->smwModell,
            'gewaehlt' => $this->smwGewaehlt,
            'begruendung' => $this->smwBegruendung ?: null,
        ];

        if ($this->editingSmwId) {
            P1Strukturmodellwahl::where('projekt_id', $this->projekt->id)
                ->findOrFail($this->editingSmwId)
                ->update($data);
        } else {
            P1Strukturmodellwahl::create($data);
        }

        $this->cancelSmw();
    }

    public function editSmw(string $id): void
    {
        $r = P1Strukturmodellwahl::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingSmwId = $id;
        $this->smwModell = $r->modell ?? '';
        $this->smwGewaehlt = (bool) $r->gewaehlt;
        $this->smwBegruendung = $r->begruendung ?? '';
        $this->showSmwForm = true;
    }

    public function deleteSmw(string $id): void
    {
        P1Strukturmodellwahl::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelSmw(): void
    {
        $this->showSmwForm = false;
        $this->editingSmwId = null;
        $this->reset(['smwModell', 'smwGewaehlt', 'smwBegruendung']);
    }

    // ─── Komponente CRUD ─────────────────────────────────────

    public function newKomp(): void
    {
        $this->cancelKomp();
        $this->showKompForm = true;
    }

    public function saveKomp(): void
    {
        $this->validate([
            'kompLabel' => 'required|string|max:255',
        ]);

        $data = [
            'projekt_id' => $this->projekt->id,
            'modell' => $this->kompModell ?: null,
            'komponente_kuerzel' => $this->kompKuerzel ?: null,
            'komponente_label' => $this->kompLabel,
            'synonyme' => $this->kompSynonyme ? array_map('trim', explode(',', $this->kompSynonyme)) : null,
            'inhaltlicher_begriff_de' => $this->kompBegriffDe ?: null,
            'englische_entsprechung' => $this->kompEnglisch ?: null,
            'mesh_term' => $this->kompMesh ?: null,
            'thesaurus_term' => $this->kompThesaurus ?: null,
            'anmerkungen' => $this->kompAnmerkungen ?: null,
        ];

        if ($this->editingKompId) {
            P1Komponente::where('projekt_id', $this->projekt->id)
                ->findOrFail($this->editingKompId)
                ->update($data);
        } else {
            P1Komponente::create($data);
        }

        $this->cancelKomp();
    }

    public function editKomp(string $id): void
    {
        $r = P1Komponente::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingKompId = $id;
        $this->kompModell = $r->modell ?? '';
        $this->kompKuerzel = $r->komponente_kuerzel ?? '';
        $this->kompLabel = $r->komponente_label ?? '';
        $this->kompSynonyme = is_array($r->synonyme) ? implode(', ', $r->synonyme) : '';
        $this->kompBegriffDe = $r->inhaltlicher_begriff_de ?? '';
        $this->kompEnglisch = $r->englische_entsprechung ?? '';
        $this->kompMesh = $r->mesh_term ?? '';
        $this->kompThesaurus = $r->thesaurus_term ?? '';
        $this->kompAnmerkungen = $r->anmerkungen ?? '';
        $this->showKompForm = true;
    }

    public function deleteKomp(string $id): void
    {
        P1Komponente::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelKomp(): void
    {
        $this->showKompForm = false;
        $this->editingKompId = null;
        $this->reset(['kompModell', 'kompKuerzel', 'kompLabel', 'kompSynonyme', 'kompBegriffDe', 'kompEnglisch', 'kompMesh', 'kompThesaurus', 'kompAnmerkungen']);
    }

    // ─── Kriterium CRUD ──────────────────────────────────────

    public function newKrit(): void
    {
        $this->cancelKrit();
        $this->showKritForm = true;
    }

    public function saveKrit(): void
    {
        $this->validate([
            'kritBeschreibung' => 'required|string',
        ]);

        $data = [
            'projekt_id' => $this->projekt->id,
            'kriterium_typ' => $this->kritTyp,
            'beschreibung' => $this->kritBeschreibung,
            'begruendung' => $this->kritBegruendung ?: null,
            'quellbezug' => $this->kritQuellbezug ?: null,
        ];

        if ($this->editingKritId) {
            P1Kriterium::where('projekt_id', $this->projekt->id)
                ->findOrFail($this->editingKritId)
                ->update($data);
        } else {
            P1Kriterium::create($data);
        }

        $this->cancelKrit();
    }

    public function editKrit(string $id): void
    {
        $r = P1Kriterium::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingKritId = $id;
        $this->kritTyp = $r->kriterium_typ ?? 'einschluss';
        $this->kritBeschreibung = $r->beschreibung ?? '';
        $this->kritBegruendung = $r->begruendung ?? '';
        $this->kritQuellbezug = $r->quellbezug ?? '';
        $this->showKritForm = true;
    }

    public function deleteKrit(string $id): void
    {
        P1Kriterium::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelKrit(): void
    {
        $this->showKritForm = false;
        $this->editingKritId = null;
        $this->reset(['kritTyp', 'kritBeschreibung', 'kritBegruendung', 'kritQuellbezug']);
        $this->kritTyp = 'einschluss';
    }

    // ─── Warnsignal CRUD ─────────────────────────────────────

    public function newWarn(): void
    {
        $this->cancelWarn();
        $nextNr = P1Warnsignal::where('projekt_id', $this->projekt->id)->max('lfd_nr') ?? 0;
        $this->warnLfdNr = $nextNr + 1;
        $this->showWarnForm = true;
    }

    public function saveWarn(): void
    {
        $this->validate([
            'warnWarnsignal' => 'required|string',
        ]);

        $data = [
            'projekt_id' => $this->projekt->id,
            'lfd_nr' => $this->warnLfdNr,
            'warnsignal' => $this->warnWarnsignal,
            'moegliche_auswirkung' => $this->warnAuswirkung ?: null,
            'handlungsempfehlung' => $this->warnHandlungsempfehlung ?: null,
        ];

        if ($this->editingWarnId) {
            P1Warnsignal::where('projekt_id', $this->projekt->id)
                ->findOrFail($this->editingWarnId)
                ->update($data);
        } else {
            P1Warnsignal::create($data);
        }

        $this->cancelWarn();
    }

    public function editWarn(string $id): void
    {
        $r = P1Warnsignal::where('projekt_id', $this->projekt->id)->findOrFail($id);
        $this->editingWarnId = $id;
        $this->warnLfdNr = $r->lfd_nr ?? 1;
        $this->warnWarnsignal = $r->warnsignal ?? '';
        $this->warnAuswirkung = $r->moegliche_auswirkung ?? '';
        $this->warnHandlungsempfehlung = $r->handlungsempfehlung ?? '';
        $this->showWarnForm = true;
    }

    public function deleteWarn(string $id): void
    {
        P1Warnsignal::where('projekt_id', $this->projekt->id)->findOrFail($id)->delete();
    }

    public function cancelWarn(): void
    {
        $this->showWarnForm = false;
        $this->editingWarnId = null;
        $this->reset(['warnLfdNr', 'warnWarnsignal', 'warnAuswirkung', 'warnHandlungsempfehlung']);
        $this->warnLfdNr = 1;
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        return [
            'strukturmodelle' => P1Strukturmodellwahl::where('projekt_id', $pid)->get(),
            'komponenten' => P1Komponente::where('projekt_id', $pid)->get(),
            'kriterien' => P1Kriterium::where('projekt_id', $pid)->get(),
            'warnsignale' => P1Warnsignal::where('projekt_id', $pid)->orderBy('lfd_nr')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- KI-Agent Button --}}
    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="scoping_mapping_agent"
        label="🎯 KI: Strukturierung starten"
        :phase-nr="1"
        :key="'agent-p1-'.$projekt->id"
    />

    {{-- ═══ Strukturmodellwahl ═══ --}}
    <x-crud.section title="Strukturmodellwahl" :count="$strukturmodelle->count()" new-action="newSmw">
        <x-crud.form :visible="$showSmwForm" save-action="saveSmw" cancel-action="cancelSmw">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-crud.field label="Modell" required>
                    <input wire:model="smwModell" type="text" placeholder="z.B. PICO" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Gewählt</label>
                    <label class="mt-1 flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <input wire:model="smwGewaehlt" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600">
                        Ja
                    </label>
                </div>
                <x-crud.field label="Begründung">
                    <input wire:model="smwBegruendung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            @error('smwModell') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </x-crud.form>

        @if ($strukturmodelle->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Modell</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Gewählt</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Begründung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($strukturmodelle as $s)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $s->modell }}</td>
                                <td class="px-4 py-2">
                                    @if ($s->gewaehlt)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-400">Ja</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($s->begruendung, 60) }}</td>
                                <x-crud.actions edit-action="editSmw" delete-action="deleteSmw" :item-id="$s->id" />
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Strukturmodelle bewertet.</p>
        @endif
    </x-crud.section>

    {{-- ═══ Komponenten ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Komponenten
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $komponenten->count() }})</span>
            </h3>
            <button wire:click="newKomp" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showKompForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Modell</label>
                        <input wire:model="kompModell" type="text" placeholder="z.B. PICO" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Kürzel</label>
                        <input wire:model="kompKuerzel" type="text" placeholder="z.B. P" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Label *</label>
                        <input wire:model="kompLabel" type="text" placeholder="z.B. Population" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Inhaltlicher Begriff (DE)</label>
                        <input wire:model="kompBegriffDe" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Englische Entsprechung</label>
                        <input wire:model="kompEnglisch" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">MeSH-Term</label>
                        <input wire:model="kompMesh" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Thesaurus-Term</label>
                        <input wire:model="kompThesaurus" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Synonyme <span class="font-normal text-neutral-400">(kommagetrennt)</span></label>
                    <input wire:model="kompSynonyme" type="text" placeholder="Synonym 1, Synonym 2, ..." class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkungen</label>
                    <textarea wire:model="kompAnmerkungen" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                @error('kompLabel') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveKomp" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelKomp" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($komponenten->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Kürzel</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Label</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">DE</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">EN</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Synonyme</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($komponenten as $k)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-neutral-900 dark:text-neutral-100">{{ $k->komponente_kuerzel }}</td>
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $k->komponente_label }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($k->inhaltlicher_begriff_de, 40) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($k->englische_entsprechung, 40) }}</td>
                                <td class="px-4 py-2 text-neutral-500">
                                    @if (is_array($k->synonyme))
                                        <span class="text-xs">{{ count($k->synonyme) }} Synonyme</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editKomp('{{ $k->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                    </button>
                                    <button wire:click="deleteKomp('{{ $k->id }}')" wire:confirm="Komponente wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400">
                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Komponenten definiert.</p>
        @endif
    </div>

    {{-- ═══ Ein-/Ausschlusskriterien ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Ein-/Ausschlusskriterien
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $kriterien->count() }})</span>
            </h3>
            <button wire:click="newKrit" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showKritForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Typ *</label>
                        <select wire:model="kritTyp" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="einschluss">Einschluss</option>
                            <option value="ausschluss">Ausschluss</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Quellbezug</label>
                        <input wire:model="kritQuellbezug" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Beschreibung *</label>
                    <textarea wire:model="kritBeschreibung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung</label>
                    <textarea wire:model="kritBegruendung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                @error('kritBeschreibung') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveKrit" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelKrit" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($kriterien->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Typ</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Beschreibung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Begründung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($kriterien as $kr)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400' => $kr->kriterium_typ === 'einschluss',
                                        'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400' => $kr->kriterium_typ === 'ausschluss',
                                    ])>{{ $kr->kriterium_typ === 'einschluss' ? 'Einschluss' : 'Ausschluss' }}</span>
                                </td>
                                <td class="px-4 py-2 text-neutral-900 dark:text-neutral-100">{{ str()->limit($kr->beschreibung, 80) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($kr->begruendung, 60) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editKrit('{{ $kr->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                    </button>
                                    <button wire:click="deleteKrit('{{ $kr->id }}')" wire:confirm="Kriterium wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400">
                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Kriterien definiert.</p>
        @endif
    </div>

    {{-- ═══ Warnsignale ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Warnsignale
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $warnsignale->count() }})</span>
            </h3>
            <button wire:click="newWarn" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showWarnForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Lfd. Nr.</label>
                        <input wire:model="warnLfdNr" type="number" min="1" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Warnsignal *</label>
                        <input wire:model="warnWarnsignal" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Mögliche Auswirkung</label>
                        <textarea wire:model="warnAuswirkung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Handlungsempfehlung</label>
                        <textarea wire:model="warnHandlungsempfehlung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                </div>
                @error('warnWarnsignal') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveWarn" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelWarn" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($warnsignale->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">#</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Warnsignal</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Auswirkung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Empfehlung</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($warnsignale as $w)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="whitespace-nowrap px-4 py-2 text-neutral-500">{{ $w->lfd_nr }}</td>
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ str()->limit($w->warnsignal, 60) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($w->moegliche_auswirkung, 50) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($w->handlungsempfehlung, 50) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editWarn('{{ $w->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                    </button>
                                    <button wire:click="deleteWarn('{{ $w->id }}')" wire:confirm="Warnsignal wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400">
                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Warnsignale vorhanden.</p>
        @endif
    </div>
</div>
