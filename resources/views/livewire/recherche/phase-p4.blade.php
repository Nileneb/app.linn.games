<?php

use App\Livewire\Forms\Recherche\{SuchstringForm, ThesaurusMappingForm, AnpassungsprotokollForm};
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{Projekt, P4Suchstring, P4ThesaurusMapping, P4Anpassungsprotokoll};
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    public SuchstringForm        $ss;
    public ThesaurusMappingForm  $th;
    public AnpassungsprotokollForm $ap;

    public bool $showSsForm = false;
    public ?string $editingSsId = null;

    public bool $showThForm = false;
    public ?string $editingThId = null;

    public bool $showApForm = false;
    public ?string $editingApId = null;

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt;
    }

    #[On('agent-result-accepted')]
    public function refreshPhaseData(): void
    {
        // Re-render triggers with() which re-queries all phase data from DB
    }

    // ─── Suchstring CRUD ─────────────────────────────────────

    public function newSs(): void { $this->cancelSs(); $this->showSsForm = true; }

    public function saveSs(): void
    {
        $this->ss->validate();
        if ($this->editingSsId) {
            P4Suchstring::where('projekt_id', $this->projekt->id)->find($this->editingSsId)?->update($this->ss->toPersistArray($this->projekt->id));
        } else {
            P4Suchstring::create($this->ss->toPersistArray($this->projekt->id));
        }
        $this->cancelSs();
    }

    public function editSs(string $id): void
    {
        $r = P4Suchstring::where('projekt_id', $this->projekt->id)->find($id);
        if ($r === null) { return; }
        $this->editingSsId = $id;
        $this->ss->fillFromModel($r);
        $this->showSsForm = true;
    }

    public function deleteSs(string $id): void
    {
        P4Suchstring::where('projekt_id', $this->projekt->id)->find($id)?->delete();
    }

    public function cancelSs(): void
    {
        $this->showSsForm = false;
        $this->editingSsId = null;
        $this->ss->reset();
    }

    // ─── ThesaurusMapping CRUD ───────────────────────────────

    public function newTh(): void { $this->cancelTh(); $this->showThForm = true; }

    public function saveTh(): void
    {
        $this->th->validate();
        if ($this->editingThId) {
            P4ThesaurusMapping::where('projekt_id', $this->projekt->id)->find($this->editingThId)?->update($this->th->toPersistArray($this->projekt->id));
        } else {
            P4ThesaurusMapping::create($this->th->toPersistArray($this->projekt->id));
        }
        $this->cancelTh();
    }

    public function editTh(string $id): void
    {
        $r = P4ThesaurusMapping::where('projekt_id', $this->projekt->id)->find($id);
        if ($r === null) { return; }
        $this->editingThId = $id;
        $this->th->fillFromModel($r);
        $this->showThForm = true;
    }

    public function deleteTh(string $id): void
    {
        P4ThesaurusMapping::where('projekt_id', $this->projekt->id)->find($id)?->delete();
    }

    public function cancelTh(): void
    {
        $this->showThForm = false;
        $this->editingThId = null;
        $this->th->reset();
    }

    // ─── Anpassungsprotokoll CRUD ────────────────────────────

    public function newAp(string $suchstringId = ''): void
    {
        $this->cancelAp();
        $this->ap->suchstringId = $suchstringId;
        $this->showApForm = true;
    }

    public function saveAp(): void
    {
        $this->ap->validate();
        P4Suchstring::where('projekt_id', $this->projekt->id)->where('id', $this->ap->suchstringId)->firstOrFail();
        if ($this->editingApId) {
            $record = P4Anpassungsprotokoll::findOrFail($this->editingApId);
            P4Suchstring::where('projekt_id', $this->projekt->id)->where('id', $record->suchstring_id)->firstOrFail();
            $record->update($this->ap->toPersistArray());
        } else {
            P4Anpassungsprotokoll::create($this->ap->toPersistArray());
        }
        $this->cancelAp();
    }

    public function editAp(string $id): void
    {
        $r = P4Anpassungsprotokoll::find($id);
        if ($r === null || P4Suchstring::where('projekt_id', $this->projekt->id)->where('id', $r->suchstring_id)->doesntExist()) {
            return;
        }
        $this->editingApId = $id;
        $this->ap->fillFromModel($r);
        $this->showApForm = true;
    }

    public function deleteAp(string $id): void
    {
        $r = P4Anpassungsprotokoll::find($id);
        if ($r === null || P4Suchstring::where('projekt_id', $this->projekt->id)->where('id', $r->suchstring_id)->doesntExist()) {
            return;
        }
        $r->delete();
    }

    public function cancelAp(): void
    {
        $this->showApForm = false;
        $this->editingApId = null;
        $this->ap->reset();
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        return [
            'suchstrings' => P4Suchstring::where('projekt_id', $pid)->with('anpassungsprotokoll')->get(),
            'thesaurusMappings' => P4ThesaurusMapping::where('projekt_id', $pid)->get(),
            'latestAgentResult' => PhaseAgentResult::where('projekt_id', $pid)->where('phase_nr', 4)->whereNotNull('content')->latest()->first(),
        ];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>
    {{-- KI-Agent Button --}}
    <livewire:recherche.agent-action-button
        :projekt="$projekt"
        agent-config-key="search_agent"
        label="🔍 KI: Suchstrings generieren"
        :phase-nr="4"
        :key="'agent-p4-'.$projekt->id"
    />
    {{-- KI-Vorschlag (letztes Agent-Ergebnis) --}}
    <x-agent-suggestion :result="$latestAgentResult" />


    {{-- ═══ Suchstrings ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Suchstrings
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $suchstrings->count() }})</span>
            </h3>
            <button wire:click="newSs" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showSsForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Datenbank *</label>
                        <input wire:model="ss.datenbank" type="text" placeholder="z.B. PubMed" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Version</label>
                        <input wire:model="ss.version" type="text" placeholder="z.B. v1.0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchdatum</label>
                        <input wire:model="ss.suchdatum" type="date" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchstring</label>
                    <textarea wire:model="ss.suchstring" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm font-mono dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100" placeholder="(Population OR Patients) AND (Intervention OR Treatment) ..."></textarea>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Feldeinschränkung</label>
                        <input wire:model="ss.feldeinschraenkung" type="text" placeholder="z.B. [tiab]" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer-Anzahl</label>
                        <input wire:model="ss.trefferAnzahl" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Filter <span class="font-normal text-neutral-400">(kommagetrennt)</span></label>
                        <input wire:model="ss.filter" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Einschätzung</label>
                        <input wire:model="ss.einschaetzung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anpassung</label>
                        <input wire:model="ss.anpassung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                @error('ss.datenbank') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveSs" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelSs" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($suchstrings->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($suchstrings as $ss)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $ss->datenbank }}</span>
                                    @if ($ss->version)
                                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $ss->version }}</span>
                                    @endif
                                    @if ($ss->treffer_anzahl !== null)
                                        <span class="text-sm text-neutral-500">{{ number_format($ss->treffer_anzahl) }} Treffer</span>
                                    @endif
                                    @if ($ss->suchdatum)
                                        <span class="text-xs text-neutral-400">{{ $ss->suchdatum->format('d.m.Y') }}</span>
                                    @endif
                                </div>
                                @if ($ss->suchstring)
                                    <pre class="mt-2 max-h-24 overflow-auto rounded bg-neutral-100 p-2 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $ss->suchstring }}</pre>
                                @endif
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button wire:click="newAp('{{ $ss->id }}')" class="rounded bg-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-300 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600" title="Anpassung hinzufügen">+ Anpassung</button>
                                <button wire:click="editSs('{{ $ss->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                <button wire:click="deleteSs('{{ $ss->id }}')" wire:confirm="Suchstring und alle Anpassungen wirklich löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                            </div>
                        </div>

                        {{-- Nested Anpassungsprotokolle --}}
                        @if ($ss->anpassungsprotokoll->isNotEmpty())
                            <div class="mt-3 ml-4 rounded border border-neutral-200 dark:border-neutral-700">
                                <div class="border-b border-neutral-100 bg-neutral-50/50 px-3 py-1.5 dark:border-neutral-700 dark:bg-neutral-800/30">
                                    <span class="text-xs font-medium text-neutral-600 dark:text-neutral-400">Anpassungsprotokoll ({{ $ss->anpassungsprotokoll->count() }})</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-neutral-100 text-xs dark:divide-neutral-700/50">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-1.5 text-left font-medium text-neutral-500">Version</th>
                                                <th class="px-3 py-1.5 text-left font-medium text-neutral-500">Datum</th>
                                                <th class="px-3 py-1.5 text-left font-medium text-neutral-500">Änderung</th>
                                                <th class="px-3 py-1.5 text-left font-medium text-neutral-500">Vorher→Nachher</th>
                                                <th class="px-3 py-1.5 text-right font-medium text-neutral-500">Akt.</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-neutral-50 dark:divide-neutral-700/30">
                                            @foreach ($ss->anpassungsprotokoll as $ap)
                                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/20">
                                                    <td class="px-3 py-1.5 text-neutral-600 dark:text-neutral-300">{{ $ap->version ?? '—' }}</td>
                                                    <td class="whitespace-nowrap px-3 py-1.5 text-neutral-500">{{ $ap->datum?->format('d.m.Y') ?? '—' }}</td>
                                                    <td class="px-3 py-1.5 text-neutral-900 dark:text-neutral-100">{{ str()->limit($ap->aenderung, 50) }}</td>
                                                    <td class="whitespace-nowrap px-3 py-1.5 text-neutral-500">
                                                        {{ $ap->treffer_vorher ?? '?' }} → {{ $ap->treffer_nachher ?? '?' }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1.5 text-right">
                                                        <button wire:click="editAp('{{ $ap->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                                        <button wire:click="deleteAp('{{ $ap->id }}')" wire:confirm="Anpassung wirklich löschen?" class="ml-1 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Suchstrings definiert.</p>
        @endif
    </div>

    {{-- Anpassungsprotokoll Form (global, references suchstring_id) --}}
    @if ($showApForm)
        <div class="overflow-hidden rounded-lg border border-amber-300 bg-amber-50/50 dark:border-amber-700 dark:bg-amber-950/20">
            <div class="border-b border-amber-200 bg-amber-100/50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/30">
                <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $editingApId ? 'Anpassung bearbeiten' : 'Neue Anpassung' }}
                </h3>
            </div>
            <div class="p-4">
                @if (!$editingApId && !$ap->suchstringId)
                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Suchstring auswählen *</label>
                        <select wire:model="ap.suchstringId" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="">— Suchstring wählen —</option>
                            @foreach ($suchstrings as $ss)
                                <option value="{{ $ss->id }}">{{ $ss->datenbank }} {{ $ss->version ? '('.$ss->version.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Version</label>
                        <input wire:model="ap.version" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Datum</label>
                        <input wire:model="ap.datum" type="date" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Entscheidung</label>
                        <input wire:model="ap.entscheidung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Änderung *</label>
                    <textarea wire:model="ap.aenderung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Grund</label>
                        <input wire:model="ap.grund" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer vorher</label>
                        <input wire:model="ap.trefferVorher" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer nachher</label>
                        <input wire:model="ap.trefferNachher" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                @error('ap.suchstringId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                @error('ap.aenderung') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveAp" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelAp" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Thesaurus-Mapping ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Thesaurus-Mapping
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $thesaurusMappings->count() }})</span>
            </h3>
            <button wire:click="newTh" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showThForm)
            <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Freitext (DE) *</label>
                        <input wire:model="th.freitextDe" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Freitext (EN)</label>
                        <input wire:model="th.freitextEn" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">MeSH-Term</label>
                        <input wire:model="th.mesh" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Emtree-Term</label>
                        <input wire:model="th.emtree" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">PsycINFO-Term</label>
                        <input wire:model="th.psycinfo" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkung</label>
                    <input wire:model="th.anmerkung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </div>
                @error('th.freitextDe') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="saveTh" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    <button wire:click="cancelTh" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                </div>
            </div>
        @endif

        @if ($thesaurusMappings->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50/50 dark:bg-neutral-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">DE</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">EN</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">MeSH</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Emtree</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">PsycINFO</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($thesaurusMappings as $th)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $th->freitext_de }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $th->freitext_en ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $th->mesh_term ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $th->emtree_term ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $th->psycinfo_term ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editTh('{{ $th->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteTh('{{ $th->id }}')" wire:confirm="Mapping wirklich löschen?" class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Thesaurus-Mappings vorhanden.</p>
        @endif
    </div>
</div>
