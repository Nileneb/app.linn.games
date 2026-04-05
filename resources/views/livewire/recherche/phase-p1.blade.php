<?php

use App\Livewire\Concerns\HasProjektContext;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{P1Strukturmodellwahl, P1Komponente, P1Kriterium, P1Warnsignal};
use Livewire\Volt\Component;

new class extends Component {
    use HasProjektContext;

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
                ->whereKey($this->editingSmwId)
                ->firstOrFail()
                ->update($data);
        } else {
            P1Strukturmodellwahl::create($data);
        }

        $this->cancelSmw();
    }

    public function editSmw(string $id): void
    {
        $r = P1Strukturmodellwahl::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
        $this->editingSmwId = $id;
        $this->smwModell = $r->modell ?? '';
        $this->smwGewaehlt = (bool) $r->gewaehlt;
        $this->smwBegruendung = $r->begruendung ?? '';
        $this->showSmwForm = true;
    }

    public function deleteSmw(string $id): void
    {
        P1Strukturmodellwahl::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
                ->whereKey($this->editingKompId)
                ->firstOrFail()
                ->update($data);
        } else {
            P1Komponente::create($data);
        }

        $this->cancelKomp();
    }

    public function editKomp(string $id): void
    {
        $r = P1Komponente::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
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
        P1Komponente::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
                ->whereKey($this->editingKritId)
                ->firstOrFail()
                ->update($data);
        } else {
            P1Kriterium::create($data);
        }

        $this->cancelKrit();
    }

    public function editKrit(string $id): void
    {
        $r = P1Kriterium::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
        $this->editingKritId = $id;
        $this->kritTyp = $r->kriterium_typ ?? 'einschluss';
        $this->kritBeschreibung = $r->beschreibung ?? '';
        $this->kritBegruendung = $r->begruendung ?? '';
        $this->kritQuellbezug = $r->quellbezug ?? '';
        $this->showKritForm = true;
    }

    public function deleteKrit(string $id): void
    {
        P1Kriterium::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
                ->whereKey($this->editingWarnId)
                ->firstOrFail()
                ->update($data);
        } else {
            P1Warnsignal::create($data);
        }

        $this->cancelWarn();
    }

    public function editWarn(string $id): void
    {
        $r = P1Warnsignal::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
        $this->editingWarnId = $id;
        $this->warnLfdNr = $r->lfd_nr ?? 1;
        $this->warnWarnsignal = $r->warnsignal ?? '';
        $this->warnAuswirkung = $r->moegliche_auswirkung ?? '';
        $this->warnHandlungsempfehlung = $r->handlungsempfehlung ?? '';
        $this->showWarnForm = true;
    }

    public function deleteWarn(string $id): void
    {
        P1Warnsignal::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
            'strukturmodelle' => rescue(fn () => P1Strukturmodellwahl::where('projekt_id', $pid)->get(), collect()),
            'komponenten' => rescue(fn () => P1Komponente::where('projekt_id', $pid)->get(), collect()),
            'kriterien' => rescue(fn () => P1Kriterium::where('projekt_id', $pid)->get(), collect()),
            'warnsignale' => rescue(fn () => P1Warnsignal::where('projekt_id', $pid)->orderBy('lfd_nr')->get(), collect()),
            'latestAgentResult' => rescue(fn () => PhaseAgentResult::where('projekt_id', $pid)->where('phase_nr', 1)->whereNotNull('content')->latest()->first()),
        ];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>
    {{-- KI-Agent Button --}}
    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="scoping_mapping_agent"
        label="🎯 KI: Strukturierung starten"
        :phase-nr="1"
        :key="'agent-p1-'.$projekt->id"
    />
    {{-- KI-Vorschlag (letztes Agent-Ergebnis) --}}
    <x-agent-suggestion :result="$latestAgentResult" />


    {{-- ═══ Strukturmodellwahl ═══ --}}
    <x-crud.section title="Strukturmodellwahl" :count="$strukturmodelle->count()" new-action="newSmw">
        <x-crud.form :visible="$showSmwForm" save-action="saveSmw" cancel-action="cancelSmw">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-crud.field label="Modell" required :error="$errors->first('smwModell')">
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
        </x-crud.form>

        @if ($strukturmodelle->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
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
    <x-crud.section title="Komponenten" :count="$komponenten->count()" new-action="newKomp">
        <x-crud.form :visible="$showKompForm" save-action="saveKomp" cancel-action="cancelKomp">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-crud.field label="Modell">
                    <input wire:model="kompModell" type="text" placeholder="z.B. PICO" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Kürzel">
                    <input wire:model="kompKuerzel" type="text" placeholder="z.B. P" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Label" required :error="$errors->first('kompLabel')">
                    <input wire:model="kompLabel" type="text" placeholder="z.B. Population" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <x-crud.field label="Inhaltlicher Begriff (DE)">
                    <input wire:model="kompBegriffDe" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Englische Entsprechung">
                    <input wire:model="kompEnglisch" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <x-crud.field label="MeSH-Term">
                    <input wire:model="kompMesh" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Thesaurus-Term">
                    <input wire:model="kompThesaurus" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            <x-crud.field label="Synonyme" sublabel="(kommagetrennt)" class="mt-3">
                <input wire:model="kompSynonyme" type="text" placeholder="Synonym 1, Synonym 2, ..." class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
            </x-crud.field>
            <x-crud.field label="Anmerkungen" class="mt-3">
                <textarea wire:model="kompAnmerkungen" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </x-crud.field>
        </x-crud.form>

        @if ($komponenten->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
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
                                <x-crud.actions edit-action="editKomp" delete-action="deleteKomp" :item-id="$k->id" confirm-delete="Komponente wirklich löschen?" />
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Komponenten definiert.</p>
        @endif
    </x-crud.section>

    {{-- ═══ Ein-/Ausschlusskriterien ═══ --}}
    <x-crud.section title="Ein-/Ausschlusskriterien" :count="$kriterien->count()" new-action="newKrit">
        <x-crud.form :visible="$showKritForm" save-action="saveKrit" cancel-action="cancelKrit">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-crud.field label="Typ" required>
                    <select wire:model="kritTyp" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="einschluss">Einschluss</option>
                        <option value="ausschluss">Ausschluss</option>
                    </select>
                </x-crud.field>
                <x-crud.field label="Quellbezug">
                    <input wire:model="kritQuellbezug" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            <x-crud.field label="Beschreibung" required class="mt-3" :error="$errors->first('kritBeschreibung')">
                <textarea wire:model="kritBeschreibung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </x-crud.field>
            <x-crud.field label="Begründung" class="mt-3">
                <textarea wire:model="kritBegruendung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </x-crud.field>
        </x-crud.form>

        @if ($kriterien->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
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
                                <x-crud.actions edit-action="editKrit" delete-action="deleteKrit" :item-id="$kr->id" confirm-delete="Kriterium wirklich löschen?" />
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Kriterien definiert.</p>
        @endif
    </x-crud.section>

    {{-- ═══ Warnsignale ═══ --}}
    <x-crud.section title="Warnsignale" :count="$warnsignale->count()" new-action="newWarn">
        <x-crud.form :visible="$showWarnForm" save-action="saveWarn" cancel-action="cancelWarn">
            <div class="grid gap-3 sm:grid-cols-4">
                <x-crud.field label="Lfd. Nr.">
                    <input wire:model="warnLfdNr" type="number" min="1" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Warnsignal" required class="sm:col-span-3" :error="$errors->first('warnWarnsignal')">
                    <input wire:model="warnWarnsignal" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <x-crud.field label="Mögliche Auswirkung">
                    <textarea wire:model="warnAuswirkung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </x-crud.field>
                <x-crud.field label="Handlungsempfehlung">
                    <textarea wire:model="warnHandlungsempfehlung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </x-crud.field>
            </div>
        </x-crud.form>

        @if ($warnsignale->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
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
                                <x-crud.actions edit-action="editWarn" delete-action="deleteWarn" :item-id="$w->id" confirm-delete="Warnsignal wirklich löschen?" />
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Warnsignale vorhanden.</p>
        @endif
    </x-crud.section>
</div>
