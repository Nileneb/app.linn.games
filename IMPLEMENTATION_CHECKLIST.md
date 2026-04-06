# Implementierungs-Checkliste: Phase Thresholds + Templates

**Status**: Commit `e49ef05` — Basis implementiert

## Feature 1: Phase Transition Thresholds ✓ (80%)

### Fertig
- [x] `config/phase_chain.php`: Thresholds-Array hinzugefügt (P1-P7)
- [x] `PhaseCountService`: Zählt Phase-spezifische Daten
- [x] `TransitionValidator` + `TransitionStatus`: Gibt Threshold-Status zurück
- [x] `phase-transition-status.blade.php`: Badge-Komponente (grün/gelb/rot)
- [x] `phase-p1.blade.php`: Threshold UI + Override-Mechanik
- [x] `projekt-detail.blade.php`: Override-Listener

### Noch zu tun
Für jede Phase P2–P7 in `resources/views/livewire/recherche/phase-px.blade.php`:

**In der PHP-Klasse (`new class extends Component`):**
```php
use App\Services\TransitionValidator;
use Illuminate\Support\Facades\Log;

// Properties hinzufügen:
public bool $showOverrideForm = false;
public string $overrideBegruendung = '';

// Methoden hinzufügen:
public function requestOverride(): void { $this->showOverrideForm = true; }
public function confirmOverride(): void {
    $this->validate(['overrideBegruendung' => 'required|string|min:10']);
    Log::info('Phase transition override', [
        'projekt_id' => $this->projekt->id,
        'phase_nr'   => X,  // Replace X with phase number
        'begruendung' => $this->overrideBegruendung,
        'user_id'    => auth()->id(),
    ]);
    $this->dispatch('phase-override-confirmed', phaseNr: X);
    $this->showOverrideForm = false;
}

// In with() method:
$validator = app(TransitionValidator::class);
return [
    // ... existing data ...
    'transitionStatus' => $validator->validate($this->projekt, X),
];
```

**In der Blade-Sektion:**
```blade
{{-- At the end of the component, before closing </div> --}}
<div class="mt-6 flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
    <x-phase-transition-status
        :status="$transitionStatus"
        :phase-nr="X"
        override-action="requestOverride"
    />
    @if ($showOverrideForm)
        <div class="mt-3 w-full">
            <x-crud.field label="Begründung für Ausnahme" required :error="$errors->first('overrideBegruendung')">
                <textarea wire:model="overrideBegruendung" rows="2"
                    class="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-700 dark:text-neutral-100"
                    placeholder="Bitte begründe, warum du trotz fehlender Kriterien weitergehen möchtest…"></textarea>
            </x-crud.field>
            <div class="mt-2 flex gap-2">
                <button wire:click="confirmOverride" class="rounded bg-amber-600 px-3 py-1 text-xs font-medium text-white hover:bg-amber-700">Bestätigen & fortfahren</button>
                <button wire:click="$set('showOverrideForm', false)" class="rounded border border-neutral-300 px-3 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300">Abbrechen</button>
            </div>
        </div>
    @endif
</div>
```

**Phases to update:** P2, P3, P4, P5, P6, P7

---

## Feature 2: Markdown Templates ✓ (70%)

### Fertig
- [x] `PhaseTemplateService`: File-Template Rendering + P8 Auto-Generation
- [x] Template-Dateien: `p3-datenbankmatrix.md`, `p5-screening.md`, `p7-synthese.md`
- [x] P8 Suchprotokoll wird algorithmisch generiert

### Noch zu tun
Für die Phase-Komponenten P3, P5, P7, P8 in `resources/views/livewire/recherche/phase-px.blade.php`:

**In der PHP-Klasse:**
```php
use App\Services\PhaseTemplateService;

// Properties hinzufügen:
public string $templateContent = '';
public bool $showTemplate = false;

// Methode hinzufügen:
public function loadTemplate(): void {
    try {
        $this->templateContent = app(PhaseTemplateService::class)
            ->getTemplate(X, $this->projekt);  // Replace X with phase number
        $this->showTemplate = true;
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Template load failed', [
            'phase_nr'   => X,
            'projekt_id' => $this->projekt->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
```

**In der Blade-Sektion (vor dem ersten crud.section):**
```blade
{{-- Template-Vorlage --}}
<div class="flex items-center justify-between">
    <button wire:click="loadTemplate"
            class="inline-flex items-center gap-1.5 rounded border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800">
        📋 Template laden
    </button>
    @if ($showTemplate)
        <button wire:click="$set('showTemplate', false)" class="text-xs text-neutral-400 hover:text-neutral-600">✕ Schließen</button>
    @endif
</div>

@if ($showTemplate)
    <div class="rounded-lg border border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/20">
        <div class="flex items-center justify-between border-b border-amber-200 px-4 py-2 dark:border-amber-800">
            <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">Vorlage – zum Bearbeiten kopieren</span>
        </div>
        <div class="p-4">
            <textarea wire:model="templateContent" rows="20"
                class="w-full rounded border border-neutral-300 px-3 py-2 font-mono text-xs dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100"></textarea>
            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                Inhalt bearbeiten, dann in deinen Bericht einfügen oder als KI-Kontext nutzen.
            </p>
        </div>
    </div>
@endif
```

**Phases to update:** P3, P5, P7, P8

---

## Nächste Schritte

1. **Aktualisiere phase-p2.blade.php bis phase-p7.blade.php** mit obigen Patterns
2. **Füge Template-Button zu P3, P5, P7, P8** hinzu
3. **Test lokal:**
   - Öffne ein Projekt in Phase P1 mit < 3 Komponenten → sollte "Warnung" Badge zeigen
   - Klicke "Trotzdem fortfahren" → Override-Form sollte erscheinen
   - Fülle Begründung ein und bestätige → sollte zu P2 wechseln
   - In Phase P3: Klicke "📋 Template laden" → Markdown-Vorlage sollte in Textarea laden
4. **Commit Feature 1 & 2 vollständig**

---

## Hilfreiche Befehle

```bash
# Zeige aktuelle Status
git status

# Diff anzeigen
git diff config/phase_chain.php

# Test lokal
docker compose up -d
npm run dev
```

---

**Branch:** `main`  
**Letzte Änderung:** 2026-04-06 23:30 UTC
