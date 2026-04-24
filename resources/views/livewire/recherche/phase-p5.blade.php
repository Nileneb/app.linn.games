<?php

use App\Livewire\Concerns\{HasProjektContext, LoadsPhaseAgentResult};
use App\Models\PhaseAgentResult;
use App\Models\Recherche\{P5Treffer, P5ScreeningKriterium, P5ScreeningEntscheidung, P5ToolEntscheidung, P5PrismaZahlen};
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use App\Services\TransitionValidator;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use HasProjektContext, LoadsPhaseAgentResult;

    // --- Phase Transition ---
    public bool $showOverrideForm = false;
    public string $overrideBegruendung = '';

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

    // --- Heatmap ---
    public string $heatmapTheme = '';

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
            P5PrismaZahlen::where('projekt_id', $this->projekt->id)->whereKey($this->editingPrismaId)->firstOrFail()->update($data);
        } else {
            P5PrismaZahlen::create($data);
        }
        $this->cancelPrisma();
    }

    public function editPrisma(string $id): void
    {
        $r = P5PrismaZahlen::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
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
        P5PrismaZahlen::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
            P5ScreeningKriterium::where('projekt_id', $this->projekt->id)->whereKey($this->editingSkId)->firstOrFail()->update($data);
        } else {
            P5ScreeningKriterium::create($data);
        }
        $this->cancelSk();
    }

    public function editSk(string $id): void
    {
        $r = P5ScreeningKriterium::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
        $this->editingSkId = $id;
        $this->skLevel = $r->level ?? 'L1_titel_abstract';
        $this->skTyp = $r->kriterium_typ ?? 'einschluss';
        $this->skBeschreibung = $r->beschreibung ?? '';
        $this->skBeispiel = $r->beispiel ?? '';
        $this->showSkForm = true;
    }

    public function deleteSk(string $id): void
    {
        P5ScreeningKriterium::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
            P5ToolEntscheidung::where('projekt_id', $this->projekt->id)->whereKey($this->editingToolId)->firstOrFail()->update($data);
        } else {
            P5ToolEntscheidung::create($data);
        }
        $this->cancelTool();
    }

    public function editTool(string $id): void
    {
        $r = P5ToolEntscheidung::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail();
        $this->editingToolId = $id;
        $this->toolName = $r->tool ?? 'Rayyan';
        $this->toolGewaehlt = (bool) $r->gewaehlt;
        $this->toolBegruendung = $r->begruendung ?? '';
        $this->showToolForm = true;
    }

    public function deleteTool(string $id): void
    {
        P5ToolEntscheidung::where('projekt_id', $this->projekt->id)->whereKey($id)->firstOrFail()->delete();
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
        P5Treffer::where('projekt_id', $this->projekt->id)->whereKey($this->screenTrefferId)->firstOrFail();
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
        P5Treffer::where('projekt_id', $this->projekt->id)->whereKey($e->treffer_id)->firstOrFail();
        $e->delete();
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
            'phase_nr'    => 5,
            'begruendung' => $this->overrideBegruendung,
            'user_id'     => auth()->id(),
        ]);
        $this->dispatch('phase-override-confirmed', phaseNr: 5);
        $this->showOverrideForm = false;
    }

    // ─── Data ────────────────────────────────────────────────

    public function with(): array
    {
        $pid = $this->projekt->id;
        $validator = app(TransitionValidator::class);
        $trefferQuery = rescue(
            fn () => P5Treffer::where('projekt_id', $pid)->with('screeningEntscheidungen'),
            null,
            report: true,
        );
        if ($trefferQuery) {
            if ($this->trefferFilter === 'duplikate') {
                $trefferQuery->where('ist_duplikat', true);
            } elseif ($this->trefferFilter === 'unique') {
                $trefferQuery->where('ist_duplikat', false);
            }
        }
        return [
            'prismaZahlen' => rescue(
                fn () => P5PrismaZahlen::where('projekt_id', $pid)->first(),
                null,
                report: true,
            ),
            'screeningKriterien' => rescue(
                fn () => P5ScreeningKriterium::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'treffer' => $trefferQuery
                ? rescue(
                    fn () => $trefferQuery->orderBy('record_id')->get(),
                    collect(),
                    report: true,
                )
                : collect(),
            'trefferGesamt' => rescue(
                fn () => P5Treffer::where('projekt_id', $pid)->count(),
                0,
                report: true,
            ),
            'trefferDuplikate' => rescue(
                fn () => P5Treffer::where('projekt_id', $pid)->where('ist_duplikat', true)->count(),
                0,
                report: true,
            ),
            'tools' => rescue(
                fn () => P5ToolEntscheidung::where('projekt_id', $pid)->get(),
                collect(),
                report: true,
            ),
            'heatmapData' => $this->getHeatmapData(),
            'transitionStatus' => $validator->getTransitionStatus($this->projekt, 5, 6),
        ];
    }

    private function getHeatmapData(): array
    {
        $treffers = P5Treffer::where('projekt_id', $this->projekt->id)->get();
        $themes = ['Medizin', 'Psychologie', 'Epidemiologie', 'Immunologie', 'Public Health'];

        if ($treffers->isEmpty()) {
            return ['heatmap' => [], 'themes' => $themes, 'keywords' => []];
        }

        // Keywords aus Titel+Abstract extrahieren (Top 8 häufigste Worte)
        $allText = $treffers->map(fn($t) => strtolower($t->titel . ' ' . ($t->abstract ?? '')))->implode(' ');
        $words = str_word_count($allText, 1);
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'is', 'was', 'are', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'with', 'from', 'by', 'as', 'that', 'this', 'which', 'who', 'what', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'so', 'than', 'too', 'very', 'just', 'de', 'ein', 'eine', 'der', 'die', 'das', 'und', 'oder', 'in', 'zu', 'mit', 'von', 'bei', 'auf', 'aus', 'nach', 'über', 'unter'];
        $words = array_filter($words, fn($w) => strlen($w) > 3 && !in_array(strtolower($w), $stopwords));
        $wordCounts = array_count_values($words);
        arsort($wordCounts);
        $keywords = array_slice(array_keys($wordCounts), 0, 8);

        // Heatmap-Matrix: Keywords vs Themes
        $heatmap = [];
        foreach ($keywords as $keyword) {
            $heatmap[$keyword] = [];
            foreach ($themes as $theme) {
                $count = $treffers->filter(function($t) use ($keyword, $theme) {
                    $text = strtolower(($t->titel ?? '') . ' ' . ($t->abstract ?? ''));
                    return str_contains($text, strtolower($keyword));
                })->count();
                $heatmap[$keyword][$theme] = $count;
            }
        }

        return ['heatmap' => $heatmap, 'themes' => $themes, 'keywords' => $keywords];
    }
}; ?>

<div class="space-y-6" wire:poll.10s>
    {{-- ═══ Treffer-Heatmap nach Keywords & Themenbereichen ═══ --}}
    @if (!empty($heatmapData['keywords']))
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Treffer-Heatmap: Keywords × Themenbereiche</h3>

            {{-- Heatmap Table --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-left font-semibold text-zinc-700 dark:text-zinc-300">Keyword</th>
                            @foreach ($heatmapData['themes'] as $theme)
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-center font-semibold text-zinc-700 dark:text-zinc-300 text-xs">{{ $theme }}</th>
                            @endforeach
                            <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-center font-semibold text-zinc-700 dark:text-zinc-300 text-xs">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $maxValue = 1;
                            foreach ($heatmapData['heatmap'] as $keyword => $themeCounts) {
                                $maxValue = max($maxValue, max(array_values($themeCounts)));
                            }
                        @endphp
                        @foreach ($heatmapData['keywords'] as $keyword)
                            @php
                                $themeCounts = $heatmapData['heatmap'][$keyword] ?? [];
                                $total = array_sum($themeCounts);
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($keyword) }}</td>
                                @foreach ($heatmapData['themes'] as $theme)
                                    @php
                                        $count = $themeCounts[$theme] ?? 0;
                                        $intensity = $maxValue > 0 ? $count / $maxValue : 0;
                                        $bgColor = match(true) {
                                            $intensity >= 0.7 => 'bg-red-600 text-white',
                                            $intensity >= 0.4 => 'bg-red-400 text-white',
                                            $intensity >= 0.1 => 'bg-red-200 text-zinc-900',
                                            default => 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500',
                                        };
                                    @endphp
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-center {{ $bgColor }} transition-colors">
                                        <span class="font-semibold">{{ $count }}</span>
                                    </td>
                                @endforeach
                                <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-center font-bold text-zinc-900 dark:text-zinc-100 bg-zinc-50 dark:bg-zinc-800">{{ $total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <div class="size-4 rounded bg-red-600"></div>
                    <span class="text-zinc-600 dark:text-zinc-400">Viele Treffer (70%+)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="size-4 rounded bg-red-400"></div>
                    <span class="text-zinc-600 dark:text-zinc-400">Moderate Treffer (40-69%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="size-4 rounded bg-red-200"></div>
                    <span class="text-zinc-600 dark:text-zinc-400">Wenige Treffer (10-39%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="size-4 rounded bg-zinc-100 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600"></div>
                    <span class="text-zinc-600 dark:text-zinc-400">Keine Treffer</span>
                </div>
            </div>
        </div>
    @endif

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

        <x-crud.form :visible="$showPrismaForm" save-action="savePrisma" cancel-action="cancelPrisma" title="PRISMA-Zahlen {{ $editingPrismaId ? 'bearbeiten' : 'erfassen' }}">
            <div class="grid gap-3 sm:grid-cols-4">
                <x-crud.field label="Identifiziert gesamt">
                    <input wire:model="prismaIdentGesamt" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Datenbank-Treffer">
                    <input wire:model="prismaDatenbankTreffer" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Graue Literatur">
                    <input wire:model="prismaGraueLit" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Nach Deduplizierung">
                    <input wire:model="prismaNachDedup" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                <x-crud.field label="Ausgeschlossen L1">
                    <input wire:model="prismaAusgeschlossenL1" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Volltext geprüft">
                    <input wire:model="prismaVolltextGeprueft" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Ausgeschlossen L2">
                    <input wire:model="prismaAusgeschlossenL2" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Eingeschlossen final">
                    <input wire:model="prismaEingeschlossen" type="number" min="0" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
        </x-crud.form>

        @if ($prismaZahlen)
            <div class="p-4">
                {{-- PRISMA 2020 Flow Diagram (SVG) --}}
                @php
                    $pz = $prismaZahlen;
                    $fmt = fn($v) => $v !== null ? number_format($v) : '–';
                @endphp
                <svg viewBox="0 0 720 520" class="mx-auto w-full max-w-2xl" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <marker id="arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" class="fill-neutral-400 dark:fill-neutral-500" />
                        </marker>
                    </defs>

                    {{-- ── Stufe 1: Identifikation ── --}}
                    <rect x="10" y="10" width="280" height="56" rx="6" class="fill-blue-50 stroke-blue-400 dark:fill-blue-950/40 dark:stroke-blue-600" stroke-width="1.5" />
                    <text x="150" y="32" text-anchor="middle" class="fill-neutral-700 dark:fill-neutral-200" style="font-size:11px;font-weight:600">Datenbank-Treffer</text>
                    <text x="150" y="52" text-anchor="middle" class="fill-blue-600 dark:fill-blue-400" style="font-size:13px;font-weight:700">{{ $fmt($pz->davon_datenbank_treffer) }}</text>

                    <rect x="430" y="10" width="280" height="56" rx="6" class="fill-blue-50 stroke-blue-400 dark:fill-blue-950/40 dark:stroke-blue-600" stroke-width="1.5" />
                    <text x="570" y="32" text-anchor="middle" class="fill-neutral-700 dark:fill-neutral-200" style="font-size:11px;font-weight:600">Andere Quellen (graue Lit.)</text>
                    <text x="570" y="52" text-anchor="middle" class="fill-blue-600 dark:fill-blue-400" style="font-size:13px;font-weight:700">{{ $fmt($pz->davon_graue_literatur) }}</text>

                    {{-- Pfeile nach unten zur Zusammenführung --}}
                    <line x1="150" y1="66" x2="150" y2="100" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />
                    <line x1="570" y1="66" x2="570" y2="100" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />

                    {{-- Zusammenführungsbox --}}
                    <rect x="130" y="102" width="460" height="56" rx="6" class="fill-blue-50 stroke-blue-500 dark:fill-blue-950/40 dark:stroke-blue-500" stroke-width="1.5" />
                    <text x="360" y="124" text-anchor="middle" class="fill-neutral-700 dark:fill-neutral-200" style="font-size:11px;font-weight:600">Identifiziert gesamt</text>
                    <text x="360" y="146" text-anchor="middle" class="fill-blue-600 dark:fill-blue-400" style="font-size:15px;font-weight:700">{{ $fmt($pz->identifiziert_gesamt) }}</text>

                    {{-- Pfeil nach unten --}}
                    <line x1="360" y1="158" x2="360" y2="192" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />

                    {{-- ── Stufe 2: Screening ── --}}
                    <rect x="130" y="194" width="460" height="56" rx="6" class="fill-indigo-50 stroke-indigo-400 dark:fill-indigo-950/40 dark:stroke-indigo-500" stroke-width="1.5" />
                    <text x="360" y="216" text-anchor="middle" class="fill-neutral-700 dark:fill-neutral-200" style="font-size:11px;font-weight:600">Nach Deduplizierung (Screening-Pool)</text>
                    <text x="360" y="236" text-anchor="middle" class="fill-indigo-600 dark:fill-indigo-400" style="font-size:15px;font-weight:700">{{ $fmt($pz->nach_deduplizierung) }}</text>

                    {{-- Pfeil nach unten + Pfeil nach rechts (Ausschluss L1) --}}
                    <line x1="360" y1="250" x2="360" y2="310" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />
                    <line x1="590" y1="222" x2="660" y2="222" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />

                    {{-- Ausschluss L1 Box (rechts) --}}
                    <rect x="662" y="194" width="50" height="56" rx="6" class="fill-red-50 stroke-red-400 dark:fill-red-950/30 dark:stroke-red-500" stroke-width="1.5" />
                    <text x="687" y="214" text-anchor="middle" class="fill-neutral-600 dark:fill-neutral-300" style="font-size:9px">Aus L1</text>
                    <text x="687" y="234" text-anchor="middle" class="fill-red-600 dark:fill-red-400" style="font-size:12px;font-weight:700">{{ $fmt($pz->ausgeschlossen_l1) }}</text>

                    {{-- ── Stufe 3: Eligibility ── --}}
                    <rect x="130" y="312" width="460" height="56" rx="6" class="fill-amber-50 stroke-amber-400 dark:fill-amber-950/30 dark:stroke-amber-500" stroke-width="1.5" />
                    <text x="360" y="334" text-anchor="middle" class="fill-neutral-700 dark:fill-neutral-200" style="font-size:11px;font-weight:600">Volltext geprüft (Eligibility)</text>
                    <text x="360" y="354" text-anchor="middle" class="fill-amber-600 dark:fill-amber-400" style="font-size:15px;font-weight:700">{{ $fmt($pz->volltext_geprueft) }}</text>

                    {{-- Pfeil nach unten + Pfeil nach rechts (Ausschluss L2) --}}
                    <line x1="360" y1="368" x2="360" y2="428" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />
                    <line x1="590" y1="340" x2="660" y2="340" class="stroke-neutral-400 dark:stroke-neutral-500" stroke-width="1.5" marker-end="url(#arrow)" />

                    {{-- Ausschluss L2 Box (rechts) --}}
                    <rect x="662" y="312" width="50" height="56" rx="6" class="fill-red-50 stroke-red-400 dark:fill-red-950/30 dark:stroke-red-500" stroke-width="1.5" />
                    <text x="687" y="332" text-anchor="middle" class="fill-neutral-600 dark:fill-neutral-300" style="font-size:9px">Aus L2</text>
                    <text x="687" y="352" text-anchor="middle" class="fill-red-600 dark:fill-red-400" style="font-size:12px;font-weight:700">{{ $fmt($pz->ausgeschlossen_l2) }}</text>

                    {{-- ── Stufe 4: Einschluss ── --}}
                    <rect x="180" y="430" width="360" height="60" rx="6" class="fill-green-50 stroke-green-500 dark:fill-green-950/30 dark:stroke-green-500" stroke-width="2" />
                    <text x="360" y="454" text-anchor="middle" class="fill-neutral-700 dark:fill-neutral-200" style="font-size:12px;font-weight:600">Eingeschlossen (final)</text>
                    <text x="360" y="478" text-anchor="middle" class="fill-green-600 dark:fill-green-400" style="font-size:17px;font-weight:700">{{ $fmt($pz->eingeschlossen_final) }}</text>

                    {{-- Stufen-Labels links --}}
                    <text x="5" y="38" class="fill-neutral-400 dark:fill-neutral-500" style="font-size:9px;font-style:italic" transform="rotate(-90 5 38)">Identifikation</text>
                    <text x="5" y="222" class="fill-neutral-400 dark:fill-neutral-500" style="font-size:9px;font-style:italic" transform="rotate(-90 5 222)">Screening</text>
                    <text x="5" y="340" class="fill-neutral-400 dark:fill-neutral-500" style="font-size:9px;font-style:italic" transform="rotate(-90 5 340)">Eligibility</text>
                    <text x="5" y="460" class="fill-neutral-400 dark:fill-neutral-500" style="font-size:9px;font-style:italic" transform="rotate(-90 5 460)">Einschluss</text>
                </svg>
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
    <x-crud.section title="Screening-Kriterien" :count="$screeningKriterien->count()" new-action="newSk">

        <x-crud.form :visible="$showSkForm" save-action="saveSk" cancel-action="cancelSk" title="Screening-Kriterium {{ $editingSkId ? 'bearbeiten' : 'hinzufügen' }}">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-crud.field label="Level" required>
                    <select wire:model="skLevel" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="L1_titel_abstract">L1 — Titel/Abstract</option>
                        <option value="L2_volltext">L2 — Volltext</option>
                    </select>
                </x-crud.field>
                <x-crud.field label="Typ" required>
                    <select wire:model="skTyp" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="einschluss">Einschluss</option>
                        <option value="ausschluss">Ausschluss</option>
                    </select>
                </x-crud.field>
            </div>
            <x-crud.field label="Beschreibung" required class="mt-3" :error="$errors->first('skBeschreibung')">
                <textarea wire:model="skBeschreibung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </x-crud.field>
            <x-crud.field label="Beispiel" class="mt-3">
                <input wire:model="skBeispiel" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
            </x-crud.field>
        </x-crud.form>

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
    </x-crud.section>

    {{-- ═══ Tool-Entscheidung ═══ --}}
    <x-crud.section title="Screening-Tool" :count="$tools->count()" new-action="newTool">

        <x-crud.form :visible="$showToolForm" save-action="saveTool" cancel-action="cancelTool" title="Screening-Tool {{ $editingToolId ? 'bearbeiten' : 'hinzufügen' }}">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-crud.field label="Tool" required>
                    <select wire:model="toolName" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="Rayyan">Rayyan</option>
                        <option value="Covidence">Covidence</option>
                        <option value="EPPI_Reviewer">EPPI-Reviewer</option>
                    </select>
                </x-crud.field>
                <div class="flex items-end gap-2 pb-0.5">
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <input wire:model="toolGewaehlt" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600">
                        Gewählt
                    </label>
                </div>
            </div>
            <x-crud.field label="Begründung" class="mt-3">
                <textarea wire:model="toolBegruendung" rows="2" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"></textarea>
            </x-crud.field>
        </x-crud.form>

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
    </x-crud.section>

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

        <x-crud.form :visible="$showScreenForm" save-action="saveScreen" cancel-action="cancelScreen"
            title="Screening-Entscheidung">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-crud.field label="Level">
                    <select wire:model="screenLevel" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="L1_titel_abstract">L1 — Titel/Abstract</option>
                        <option value="L2_volltext">L2 — Volltext</option>
                    </select>
                </x-crud.field>
                <x-crud.field label="Entscheidung" required>
                    <select wire:model="screenEntscheidung" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                        <option value="eingeschlossen">Eingeschlossen</option>
                        <option value="ausgeschlossen">Ausgeschlossen</option>
                        <option value="unklar">Unklar</option>
                    </select>
                </x-crud.field>
                <x-crud.field label="Reviewer">
                    <input wire:model="screenReviewer" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
                <x-crud.field label="Ausschlussgrund">
                    <input wire:model="screenAusschlussgrund" type="text" class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100">
                </x-crud.field>
            </div>
        </x-crud.form>

        @if ($treffer->isNotEmpty())
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($treffer as $t)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    @php $trefferUrl = $t->retrieval_source_url ?: ($t->doi ? "https://doi.org/{$t->doi}" : null); @endphp
                                    @if ($trefferUrl)
                                        <a href="{{ $trefferUrl }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-400">{{ str()->limit($t->titel ?? $t->record_id, 80) }}</a>
                                    @else
                                        <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ str()->limit($t->titel ?? $t->record_id, 80) }}</span>
                                    @endif
                                    @if ($t->ist_duplikat)
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Duplikat</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ $t->autoren ?? 'Unbekannt' }}
                                    @if ($t->jahr) · {{ $t->jahr }} @endif
                                    @if ($t->journal) · {{ $t->journal }} @endif
                                    @if ($t->datenbank_quelle) · {{ $t->datenbank_quelle }} @endif
                                    @if ($t->doi)
                                        · <a href="https://doi.org/{{ $t->doi }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">DOI</a>
                                    @endif
                                    @if ($t->retrieval_source_url && str_starts_with($t->retrieval_source_url, 'http'))
                                        · <a href="{{ $t->retrieval_source_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">Quelle</a>
                                    @endif
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
                            <div class="shrink-0 space-y-1 text-right">
                                @php
                                    $statusColor = match($t->retrieval_status) {
                                        'heruntergeladen'   => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                        'pending'           => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        'nicht_verfuegbar'  => 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400',
                                        'fehler'            => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        default             => 'bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500',
                                    };
                                    $statusLabel = match($t->retrieval_status) {
                                        'heruntergeladen'   => 'Volltext ✓',
                                        'pending'           => 'Wird geladen…',
                                        'nicht_verfuegbar'  => 'Kein OA',
                                        'fehler'            => 'Fehler',
                                        default             => 'Ausstehend',
                                    };
                                @endphp
                                <span class="inline-block rounded px-2 py-1 text-xs {{ $statusColor }}">{{ $statusLabel }}</span>
                                <button wire:click="openScreen('{{ $t->id }}')" class="rounded bg-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-300 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600">Screenen</button>
                            </div>
                        </div>

                        @if($t->retrieval_checked_at)
                            <div class="mt-2 rounded border border-neutral-200 bg-neutral-50 p-2 text-xs dark:border-neutral-700 dark:bg-neutral-800">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="font-medium text-neutral-700 dark:text-neutral-200">Download:</span>
                                    <span @class([
                                        'rounded px-1.5 py-0.5',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $t->retrieval_downloaded === true,
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $t->retrieval_downloaded === false,
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $t->retrieval_downloaded === null,
                                    ])>
                                        {{ $t->retrieval_downloaded === true ? 'Ja' : ($t->retrieval_downloaded === false ? 'Nein' : 'Unklar') }}
                                    </span>

                                    @if($t->retrieval_source_url && str_starts_with($t->retrieval_source_url, 'http'))
                                        <a href="{{ $t->retrieval_source_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">Quell-Link</a>
                                    @endif

                                    @if($t->retrieval_storage_path)
                                        <span class="text-neutral-500 dark:text-neutral-400">Speicherort: {{ $t->retrieval_storage_path }}</span>
                                    @endif

                                    <span class="text-neutral-400 dark:text-neutral-500">Stand: {{ $t->retrieval_checked_at?->format('d.m.Y H:i') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">Noch keine Treffer importiert. Treffer werden vom KI-Agenten angelegt.</p>
        @endif
    </div>

    {{-- ═══ Phase Transition Status ═══ --}}
    <div class="mt-4">
        <x-phase-transition-status
            :status="$transitionStatus"
            :phase-nr="5"
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
