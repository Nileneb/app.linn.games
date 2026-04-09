<?php

use App\Livewire\Concerns\{HasProjektContext, LoadsPhaseAgentResult, TriggersPhaseAgent};
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{P5Treffer, P7SyntheseMethode, P7Datenextraktion, P7MusterKonsistenz, P7GradeEinschaetzung};
use App\Services\TransitionValidator;
use App\Services\PhaseTemplateService;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;

new class extends Component {
    use HasProjektContext, LoadsPhaseAgentResult, TriggersPhaseAgent;

    // --- Phase Transition ---
    public bool $showOverrideForm = false;
    public string $overrideBegruendung = '';

    // --- Template ---
    public string $templateContent = '';
    public bool $showTemplate = false;

    // --- Synthese-Methode ---
    public bool $showSmForm = false;
    public ?string $editingSmId = null;
    public string $smMethode = 'narrative_synthese';
    public bool $smGewaehlt = false;
    public string $smBegruendung = '';

    // --- Datenextraktion ---
    public bool $showDeForm = false;
    public ?string $editingDeId = null;
    public string $deTrefferId = '';
    public string $deLand = '';
    public string $deStichprobe = '';
    public string $dePhaenomen = '';
    public string $deOutcome = '';
    public string $deHauptbefund = '';
    public string $deQualitaetsurteil = '';
    public string $deAnmerkung = '';

    // --- Muster & Konsistenz ---
    public bool $showMkForm = false;
    public ?string $editingMkId = null;
    public string $mkBefund = '';
    public string $mkUnterstuetzend = '';
    public string $mkWidersprechend = '';
    public string $mkErklaerung = '';

    // --- GRADE ---
    public bool $showGrForm = false;
    public ?string $editingGrId = null;
    public string $grOutcome = '';
    public ?int $grStudienanzahl = null;
    public string $grRobGesamt = '';
    public string $grInkonsistenz = '';
    public string $grIndirektheit = '';
    public string $grImpraezision = '';
    public string $grUrteil = 'moderat';
    public string $grBegruendung = '';

    // ─── Synthese-Methode CRUD ───────────────────────────────

    public function newSm(): void { $this->cancelSm(); $this->showSmForm = true; }

    public function saveSm(): void
    {
        $data = [
            'projekt_id' => $this->projekt->id,
            'methode' => $this->smMethode,
            'gewaehlt' => $this->smGewaehlt,
            'begruendung' => $this->smBegruendung ?: null,
        ];
        if ($this->editingSmId) {
            P7SyntheseMethode::where('projekt_id', $this->projekt->id)->find($this->editingSmId)?->update($data);
        } else {
            P7SyntheseMethode::create($data);
        }
        $this->cancelSm();
    }

    public function editSm(string $id): void
    {
        $r = P7SyntheseMethode::where('projekt_id', $this->projekt->id)->find($id);
        if ($r === null) { return; }
        $this->editingSmId = $id;
        $this->smMethode = $r->methode ?? 'narrative_synthese';
        $this->smGewaehlt = (bool) $r->gewaehlt;
        $this->smBegruendung = $r->begruendung ?? '';
        $this->showSmForm = true;
    }

    public function deleteSm(string $id): void
    {
        P7SyntheseMethode::where('projekt_id', $this->projekt->id)->find($id)?->delete();
    }

    public function cancelSm(): void
    {
        $this->showSmForm = false;
        $this->editingSmId = null;
        $this->reset(['smMethode', 'smGewaehlt', 'smBegruendung']);
        $this->smMethode = 'narrative_synthese';
    }

    // ─── Datenextraktion CRUD ────────────────────────────────

    public function newDe(): void { $this->cancelDe(); $this->showDeForm = true; }

    public function saveDe(): void
    {
        $this->validate(['deTrefferId' => 'required|string', 'deHauptbefund' => 'required|string']);
        if (! P5Treffer::where('projekt_id', $this->projekt->id)->where('id', $this->deTrefferId)->exists()) {
            return;
        }
        $data = [
            'treffer_id' => $this->deTrefferId,
            'land' => $this->deLand ?: null,
            'stichprobe_kontext' => $this->deStichprobe ?: null,
            'phaenomen_intervention' => $this->dePhaenomen ?: null,
            'outcome_ergebnis' => $this->deOutcome ?: null,
            'hauptbefund' => $this->deHauptbefund,
            'qualitaetsurteil' => $this->deQualitaetsurteil ?: null,
            'anmerkung' => $this->deAnmerkung ?: null,
        ];
        if ($this->editingDeId) {
            P7Datenextraktion::find($this->editingDeId)?->update($data);
        } else {
            P7Datenextraktion::create($data);
        }
        $this->cancelDe();
    }

    public function editDe(string $id): void
    {
        $r = P7Datenextraktion::find($id);
        if ($r === null || ! P5Treffer::where('projekt_id', $this->projekt->id)->where('id', $r->treffer_id)->exists()) {
            return;
        }
        $this->editingDeId = $id;
        $this->deTrefferId = $r->treffer_id;
        $this->deLand = $r->land ?? '';
        $this->deStichprobe = $r->stichprobe_kontext ?? '';
        $this->dePhaenomen = $r->phaenomen_intervention ?? '';
        $this->deOutcome = $r->outcome_ergebnis ?? '';
        $this->deHauptbefund = $r->hauptbefund ?? '';
        $this->deQualitaetsurteil = $r->qualitaetsurteil ?? '';
        $this->deAnmerkung = $r->anmerkung ?? '';
        $this->showDeForm = true;
    }

    public function deleteDe(string $id): void
    {
        $r = P7Datenextraktion::find($id);
        if ($r === null) {
            return;
        }
        if (! P5Treffer::where('projekt_id', $this->projekt->id)->where('id', $r->treffer_id)->exists()) {
            return;
        }
        $r->delete();
    }

    public function cancelDe(): void
    {
        $this->showDeForm = false;
        $this->editingDeId = null;
        $this->reset(['deTrefferId', 'deLand', 'deStichprobe', 'dePhaenomen', 'deOutcome', 'deHauptbefund', 'deQualitaetsurteil', 'deAnmerkung']);
    }

    // ─── Muster & Konsistenz CRUD ────────────────────────────

    public function newMk(): void { $this->cancelMk(); $this->showMkForm = true; }

    public function saveMk(): void
    {
        $this->validate(['mkBefund' => 'required|string']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'muster_befund' => $this->mkBefund,
            'unterstuetzende_quellen' => $this->mkUnterstuetzend ? array_map('trim', explode(',', $this->mkUnterstuetzend)) : null,
            'widersprechende_quellen' => $this->mkWidersprechend ? array_map('trim', explode(',', $this->mkWidersprechend)) : null,
            'moegliche_erklaerung' => $this->mkErklaerung ?: null,
        ];
        if ($this->editingMkId) {
            P7MusterKonsistenz::where('projekt_id', $this->projekt->id)->find($this->editingMkId)?->update($data);
        } else {
            P7MusterKonsistenz::create($data);
        }
        $this->cancelMk();
    }

    public function editMk(string $id): void
    {
        $r = P7MusterKonsistenz::where('projekt_id', $this->projekt->id)->find($id);
        if ($r === null) { return; }
        $this->editingMkId = $id;
        $this->mkBefund = $r->muster_befund ?? '';
        $this->mkUnterstuetzend = is_array($r->unterstuetzende_quellen) ? implode(', ', $r->unterstuetzende_quellen) : '';
        $this->mkWidersprechend = is_array($r->widersprechende_quellen) ? implode(', ', $r->widersprechende_quellen) : '';
        $this->mkErklaerung = $r->moegliche_erklaerung ?? '';
        $this->showMkForm = true;
    }

    public function deleteMk(string $id): void
    {
        P7MusterKonsistenz::where('projekt_id', $this->projekt->id)->find($id)?->delete();
    }

    public function cancelMk(): void
    {
        $this->showMkForm = false;
        $this->editingMkId = null;
        $this->reset(['mkBefund', 'mkUnterstuetzend', 'mkWidersprechend', 'mkErklaerung']);
    }

    // ─── GRADE CRUD ──────────────────────────────────────────

    public function newGr(): void { $this->cancelGr(); $this->showGrForm = true; }

    public function saveGr(): void
    {
        $this->validate(['grOutcome' => 'required|string', 'grUrteil' => 'required|string']);
        $data = [
            'projekt_id' => $this->projekt->id,
            'outcome' => $this->grOutcome,
            'studienanzahl' => $this->grStudienanzahl,
            'rob_gesamt' => $this->grRobGesamt ?: null,
            'inkonsistenz' => $this->grInkonsistenz ?: null,
            'indirektheit' => $this->grIndirektheit ?: null,
            'impraezision' => $this->grImpraezision ?: null,
            'grade_urteil' => $this->grUrteil,
            'begruendung' => $this->grBegruendung ?: null,
        ];
        if ($this->editingGrId) {
            P7GradeEinschaetzung::where('projekt_id', $this->projekt->id)->find($this->editingGrId)?->update($data);
        } else {
            P7GradeEinschaetzung::create($data);
        }
        $this->cancelGr();
    }

    public function editGr(string $id): void
    {
        $r = P7GradeEinschaetzung::where('projekt_id', $this->projekt->id)->find($id);
        if ($r === null) { return; }
        $this->editingGrId = $id;
        $this->grOutcome = $r->outcome ?? '';
        $this->grStudienanzahl = $r->studienanzahl;
        $this->grRobGesamt = $r->rob_gesamt ?? '';
        $this->grInkonsistenz = $r->inkonsistenz ?? '';
        $this->grIndirektheit = $r->indirektheit ?? '';
        $this->grImpraezision = $r->impraezision ?? '';
        $this->grUrteil = $r->grade_urteil ?? 'moderat';
        $this->grBegruendung = $r->begruendung ?? '';
        $this->showGrForm = true;
    }

    public function deleteGr(string $id): void
    {
        P7GradeEinschaetzung::where('projekt_id', $this->projekt->id)->find($id)?->delete();
    }

    public function cancelGr(): void
    {
        $this->showGrForm = false;
        $this->editingGrId = null;
        $this->reset(['grOutcome', 'grStudienanzahl', 'grRobGesamt', 'grInkonsistenz', 'grIndirektheit', 'grImpraezision', 'grUrteil', 'grBegruendung']);
        $this->grUrteil = 'moderat';
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
            'phase_nr'    => 7,
            'begruendung' => $this->overrideBegruendung,
            'user_id'     => auth()->id(),
        ]);
        $this->dispatch('phase-override-confirmed', phaseNr: 7);
        $this->showOverrideForm = false;
    }

    // ─── Template Methods ────────────────────────────────────

    public function loadTemplate(): void
    {
        try {
            $this->templateContent = app(PhaseTemplateService::class)->getTemplate(7, $this->projekt);
            $this->showTemplate = true;
        } catch (\Throwable $e) {
            Log::error('Template laden fehlgeschlagen', ['phase' => 7, 'error' => $e->getMessage()]);
        }
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        $validator = app(TransitionValidator::class);
        $treffer = rescue(
            fn () => P5Treffer::where('projekt_id', $pid)->where('ist_duplikat', false)->get(),
            collect(),
            report: true,
        );
        return [
            'syntheseMethoden' => rescue(
                fn () => P7SyntheseMethode::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'datenextraktionen' => $treffer->isNotEmpty()
                ? rescue(
                    fn () => P7Datenextraktion::whereIn('treffer_id', $treffer->pluck('id'))->with('treffer')->get(),
                    collect(),
                    report: true,
                )
                : collect(),
            'muster' => rescue(
                fn () => P7MusterKonsistenz::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'gradeEinschaetzungen' => rescue(
                fn () => P7GradeEinschaetzung::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'treffer' => $treffer,
            'transitionStatus' => $validator->getTransitionStatus($this->projekt, 7, 8),
        ];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>
    {{-- KI-Agent Trigger --}}
    <x-phase-agent-trigger :phase-nr="7" :dispatched="$agentDispatched" />

    {{-- ═══ Template ═══ --}}
    <div class="overflow-hidden rounded-lg border border-indigo-200 dark:border-indigo-800">
        <div class="flex items-center justify-between border-b border-indigo-200 bg-indigo-50 px-4 py-3 dark:border-indigo-800 dark:bg-indigo-950">
            <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Synthese-Template</h3>
            <button wire:click="loadTemplate" class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700">Template laden</button>
        </div>
        @if ($showTemplate)
            <div class="p-4">
                <textarea wire:model="templateContent" rows="12"
                    class="w-full rounded border border-neutral-300 px-3 py-2 font-mono text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </div>
        @endif
    </div>

    {{-- ═══ Synthese-Methode ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Synthese-Methode
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $syntheseMethoden->count() }})</span>
            </h3>
            <button wire:click="newSm" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showSmForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelSm"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Synthese-Methode {{ $editingSmId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelSm" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Methode *</label>
                        <select wire:model="smMethode" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="narrative_synthese">Narrative Synthese</option>
                            <option value="meta_analyse">Meta-Analyse</option>
                            <option value="thematische_synthese">Thematische Synthese</option>
                            <option value="framework_synthesis">Framework Synthesis</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung</label>
                        <textarea wire:model="smBegruendung" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input wire:model="smGewaehlt" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600"> Gewählt
                        </label>
                    </div>
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelSm" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveSm" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($syntheseMethoden->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($syntheseMethoden as $sm)
                    <div class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ ucfirst(str_replace('_', '-', $sm->methode)) }}</span>
                            @if ($sm->gewaehlt)
                                <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">Gewählt</span>
                            @endif
                            @if ($sm->begruendung)
                                <span class="text-xs text-neutral-500">— {{ str()->limit($sm->begruendung, 60) }}</span>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            <button wire:click="editSm('{{ $sm->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                            <button wire:click="deleteSm('{{ $sm->id }}')" wire:confirm="Methode löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Synthese-Methode bewertet.</p>
        @endif
    </div>

    {{-- ═══ Datenextraktion ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Datenextraktion
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $datenextraktionen->count() }})</span>
                {{-- 📊 Visualisierung: Evidence-Map — Länder × Outcomes Heatmap --}}
            </h3>
            <button wire:click="newDe" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showDeForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelDe"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Datenextraktion {{ $editingDeId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelDe" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Treffer *</label>
                        <select wire:model="deTrefferId" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                            <option value="">— Treffer wählen —</option>
                            @foreach ($treffer as $t)
                                <option value="{{ $t->id }}">{{ str()->limit($t->titel ?? $t->record_id, 60) }} ({{ $t->jahr }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Land</label>
                            <input wire:model="deLand" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Stichprobe / Kontext</label>
                            <input wire:model="deStichprobe" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Phänomen / Intervention</label>
                            <input wire:model="dePhaenomen" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Outcome / Ergebnis</label>
                            <input wire:model="deOutcome" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Qualitätsurteil</label>
                            <select wire:model="deQualitaetsurteil" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                                <option value="">— keine Angabe —</option>
                                <option value="niedrig">Niedrig</option>
                                <option value="unklar">Unklar</option>
                                <option value="hoch">Hoch</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Anmerkung</label>
                            <input wire:model="deAnmerkung" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Hauptbefund *</label>
                        <textarea wire:model="deHauptbefund" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                    @error('deTrefferId') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('deHauptbefund') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelDe" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveDe" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Evidence Map Heatmap (Länder × Outcomes) --}}
        @if ($datenextraktionen->isNotEmpty())
            @php
                $laender = $datenextraktionen->pluck('land')->filter()->unique()->sort()->values();
                $outcomes = $datenextraktionen->pluck('outcome_ergebnis')->filter()->unique()->sort()->values();
                $pivot = [];
                foreach ($datenextraktionen as $de) {
                    if ($de->land && $de->outcome_ergebnis) {
                        $pivot[$de->land][$de->outcome_ergebnis] = ($pivot[$de->land][$de->outcome_ergebnis] ?? 0) + 1;
                    }
                }
                $showHeatmap = $laender->count() >= 2 || $outcomes->count() >= 2;
                $hmColors = [1 => 'bg-blue-100 dark:bg-blue-900/30', 2 => 'bg-blue-200 dark:bg-blue-900/50', 3 => 'bg-blue-300 dark:bg-blue-800/60', 4 => 'bg-blue-400 dark:bg-blue-700/70'];
            @endphp
            @if ($showHeatmap && count($pivot) > 0)
                <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <p class="mb-2 text-xs font-semibold text-neutral-600 dark:text-neutral-300">Evidence Map — Länder × Outcomes</p>
                    <div class="overflow-x-auto">
                        <table class="text-xs">
                            <thead>
                                <tr>
                                    <th class="px-2 py-1 text-left font-medium text-neutral-500 dark:text-neutral-400"></th>
                                    @foreach ($outcomes as $o)
                                        <th class="max-w-[100px] truncate px-2 py-1 text-center font-medium text-neutral-500 dark:text-neutral-400" title="{{ $o }}">{{ str()->limit($o, 18) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($laender as $land)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                                        <td class="whitespace-nowrap px-2 py-1 font-medium text-neutral-700 dark:text-neutral-300">{{ $land }}</td>
                                        @foreach ($outcomes as $o)
                                            @php $val = $pivot[$land][$o] ?? 0; @endphp
                                            <td class="px-1 py-1 text-center">
                                                @if ($val > 0)
                                                    <span class="{{ $hmColors[min($val, 4)] }} inline-flex h-7 w-7 items-center justify-center rounded text-xs font-semibold text-blue-800 dark:text-blue-200" title="{{ $land }} × {{ $o }}: {{ $val }}">{{ $val }}</span>
                                                @else
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded bg-neutral-50 text-neutral-300 dark:bg-neutral-800 dark:text-neutral-600">·</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{-- Farbskala --}}
                    <div class="mt-1.5 flex items-center gap-1 text-[10px] text-neutral-400 dark:text-neutral-500">
                        <span>0</span>
                        <span class="h-2.5 w-4 rounded bg-neutral-100 dark:bg-neutral-800"></span>
                        <span class="h-2.5 w-4 rounded bg-blue-100 dark:bg-blue-900/30"></span>
                        <span class="h-2.5 w-4 rounded bg-blue-200 dark:bg-blue-900/50"></span>
                        <span class="h-2.5 w-4 rounded bg-blue-300 dark:bg-blue-800/60"></span>
                        <span class="h-2.5 w-4 rounded bg-blue-400 dark:bg-blue-700/70"></span>
                        <span>4+</span>
                    </div>
                </div>
            @endif
        @endif

        @if ($datenextraktionen->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Studie</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Land</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Hauptbefund</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Qualität</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Akt.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($datenextraktionen as $de)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/20">
                                <td class="px-4 py-2 text-neutral-900 dark:text-neutral-100">{{ str()->limit($de->treffer?->titel ?? $de->treffer_id, 40) }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ $de->land ?? '—' }}</td>
                                <td class="px-4 py-2 text-neutral-600 dark:text-neutral-300">{{ str()->limit($de->hauptbefund, 50) }}</td>
                                <td class="px-4 py-2">
                                    @if ($de->qualitaetsurteil)
                                        <span @class([
                                            'rounded px-1.5 py-0.5 text-xs font-medium',
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $de->qualitaetsurteil === 'niedrig',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $de->qualitaetsurteil === 'unklar',
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $de->qualitaetsurteil === 'hoch',
                                        ])>{{ ucfirst($de->qualitaetsurteil) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editDe('{{ $de->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteDe('{{ $de->id }}')" wire:confirm="Extraktion löschen?" class="ml-1 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Daten extrahiert. Treffer aus P5 können hier analysiert werden.</p>
        @endif
    </div>

    {{-- ═══ Muster & Konsistenz ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                Muster & Konsistenz
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $muster->count() }})</span>
                {{-- 📊 Visualisierung: Stacked Bar — Pro Befund unterstützend vs. widersprechend --}}
            </h3>
            <button wire:click="newMk" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showMkForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelMk"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Muster / Konsistenz {{ $editingMkId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelMk" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Muster / Befund *</label>
                        <textarea wire:model="mkBefund" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-1">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Unterstützende Quellen <span class="font-normal text-neutral-400">(kommagetrennt)</span></label>
                            <input wire:model="mkUnterstuetzend" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Widersprechende Quellen <span class="font-normal text-neutral-400">(kommagetrennt)</span></label>
                            <input wire:model="mkWidersprechend" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Mögliche Erklärung</label>
                            <textarea wire:model="mkErklaerung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                        </div>
                    </div>
                    @error('mkBefund') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelMk" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveMk" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($muster->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($muster as $m)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $m->muster_befund }}</p>
                                <div class="mt-1 flex flex-wrap gap-3 text-xs">
                                    @if (is_array($m->unterstuetzende_quellen) && count($m->unterstuetzende_quellen))
                                        <span class="text-green-600 dark:text-green-400">
                                            ✓ {{ count($m->unterstuetzende_quellen) }} unterstützend
                                        </span>
                                    @endif
                                    @if (is_array($m->widersprechende_quellen) && count($m->widersprechende_quellen))
                                        <span class="text-red-600 dark:text-red-400">
                                            ✗ {{ count($m->widersprechende_quellen) }} widersprechend
                                        </span>
                                    @endif
                                </div>
                                @if ($m->moegliche_erklaerung)
                                    <p class="mt-1 text-xs text-neutral-500 italic">{{ str()->limit($m->moegliche_erklaerung, 100) }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button wire:click="editMk('{{ $m->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                <button wire:click="deleteMk('{{ $m->id }}')" wire:confirm="Muster löschen?" class="text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Muster/Konsistenz-Befunde dokumentiert.</p>
        @endif
    </div>

    {{-- ═══ GRADE Einschätzung ═══ --}}
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                GRADE Einschätzung
                <span class="ml-1 text-xs font-normal text-neutral-500">({{ $gradeEinschaetzungen->count() }})</span>
                {{-- 📊 Visualisierung: GRADE Summary-of-Findings Tabelle mit Farbkodierung --}}
            </h3>
            <button wire:click="newGr" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
        </div>

        @if ($showGrForm)
            <div class="fixed inset-0 z-30 bg-black/30" wire:click="cancelGr"></div>
            <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">GRADE Einschätzung {{ $editingGrId ? 'bearbeiten' : 'hinzufügen' }}</h3>
                    <button wire:click="cancelGr" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Outcome *</label>
                            <input wire:model="grOutcome" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Studienanzahl</label>
                            <input wire:model="grStudienanzahl" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">GRADE-Urteil *</label>
                            <select wire:model="grUrteil" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                                <option value="hoch">⊕⊕⊕⊕ Hoch</option>
                                <option value="moderat">⊕⊕⊕○ Moderat</option>
                                <option value="niedrig">⊕⊕○○ Niedrig</option>
                                <option value="sehr_niedrig">⊕○○○ Sehr niedrig</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">RoB gesamt</label>
                            <select wire:model="grRobGesamt" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                                <option value="">—</option>
                                <option value="niedrig">Niedrig</option>
                                <option value="unklar">Unklar</option>
                                <option value="hoch">Hoch</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Inkonsistenz</label>
                            <input wire:model="grInkonsistenz" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Indirektheit</label>
                            <input wire:model="grIndirektheit" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Impräzision</label>
                            <input wire:model="grImpraezision" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Begründung</label>
                        <textarea wire:model="grBegruendung" rows="3" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
                    </div>
                    @error('grOutcome') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div class="flex justify-end gap-2">
                        <button wire:click="cancelGr" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">Abbrechen</button>
                        <button wire:click="saveGr" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Speichern</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($gradeEinschaetzungen->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 z-10 bg-neutral-50/95 dark:bg-neutral-800/95 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Outcome</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-neutral-500">Studien</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-neutral-500">RoB</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-neutral-500">GRADE</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500">Akt.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                        @foreach ($gradeEinschaetzungen as $gr)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/20">
                                <td class="px-4 py-2 font-medium text-neutral-900 dark:text-neutral-100">{{ $gr->outcome }}</td>
                                <td class="px-4 py-2 text-center text-neutral-600 dark:text-neutral-300">{{ $gr->studienanzahl ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if ($gr->rob_gesamt)
                                        <span @class([
                                            'rounded px-1.5 py-0.5 text-xs font-medium',
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $gr->rob_gesamt === 'niedrig',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $gr->rob_gesamt === 'unklar',
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $gr->rob_gesamt === 'hoch',
                                        ])>{{ ucfirst($gr->rob_gesamt) }}</span>
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span @class([
                                        'rounded px-1.5 py-0.5 text-xs font-bold',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $gr->grade_urteil === 'hoch',
                                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => $gr->grade_urteil === 'moderat',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $gr->grade_urteil === 'niedrig',
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $gr->grade_urteil === 'sehr_niedrig',
                                    ])>{{ ucfirst(str_replace('_', ' ', $gr->grade_urteil)) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button wire:click="editGr('{{ $gr->id }}')" class="text-blue-600 hover:text-blue-800 dark:text-blue-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg></button>
                                    <button wire:click="deleteGr('{{ $gr->id }}')" wire:confirm="GRADE-Einschätzung löschen?" class="ml-1 text-red-500 hover:text-red-700 dark:text-red-400"><svg class="inline h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine GRADE-Einschätzungen.</p>
        @endif
    </div>

    <!-- Mayring Snippet Extractor -->
    <div class="mt-8 border-t border-neutral-200 pt-8 dark:border-neutral-700">
        @if ($treffer->isNotEmpty())
            @foreach ($treffer->take(1) as $paper)
                @livewire('recherche.mayring-extractor-v2', [
                    'paperId' => $paper->id,
                    'projektId' => $projekt->id,
                ])
            @endforeach
        @else
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/30 dark:bg-amber-900/10 dark:text-amber-200">
                ℹ️ Mayring-Analyse benötigt importierte Papers aus Phase 5. Bitte importieren Sie zunächst Treffer.
            </p>
        @endif
    </div>

    {{-- ═══ Phase Transition Status ═══ --}}
    <div class="mt-4">
        <x-phase-transition-status
            :status="$transitionStatus"
            :phase-nr="7"
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
