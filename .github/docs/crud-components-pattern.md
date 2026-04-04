# CRUD-Komponenten-Pattern — Phase-Dateien (P1–P8)

## Problem

Die 8 Phase-Blade-Dateien (`phase-p1.blade.php` bis `phase-p8.blade.php`) wiederholen ein identisches CRUD-UI-Pattern über ~32 Entitäten hinweg:

- **4–6 Entitäten pro Phase** mit jeweils denselben Methoden (`newX`, `saveX`, `editX`, `deleteX`, `cancelX`)
- **Identisches HTML** für: Sektions-Header, Formulare, Tabellen, Buttons, Icons
- **Jede Änderung** (z.B. neuer Button-Stil, Barrierefreiheit) muss in ~32 Stellen angepasst werden

## Lösung

### 1. Wiederverwendbare Blade-Komponenten

Vier neue Komponenten in `resources/views/components/crud/`:

#### `<x-crud.section>` — CRUD-Sektions-Wrapper
```blade
<x-crud.section title="Strukturmodellwahl" :count="$strukturmodelle->count()" new-action="newSmw">
    <!-- Formular + Tabelle hier -->
</x-crud.section>
```

**Props:**
- `title`: Sektions-Titel
- `count`: Anzahl der Einträge (für Badge)
- `new-action`: Livewire-Methode für "+ Neu"-Button

#### `<x-crud.form>` — Formular-Container
```blade
<x-crud.form :visible="$showSmwForm" save-action="saveSmw" cancel-action="cancelSmw">
    <!-- Formular-Felder hier -->
</x-crud.form>
```

**Props:**
- `visible`: Boolean (zeigt/versteckt Formular)
- `save-action`: Livewire-Methode für "Speichern"-Button
- `cancel-action`: Livewire-Methode für "Abbrechen"-Button

#### `<x-crud.field>` — Formular-Feld mit Label
```blade
<x-crud.field label="Modell" required>
    <input wire:model="smwModell" type="text" class="...">
</x-crud.field>
```

**Props:**
- `label`: Feld-Label
- `required`: Boolean (zeigt rotes Sternchen)
- `sublabel`: Optionaler Hinweistext

#### `<x-crud.actions>` — Edit/Delete-Buttons für Tabellen
```blade
<x-crud.actions
    edit-action="editSmw"
    delete-action="deleteSmw"
    :item-id="$s->id"
    confirm-delete="Eintrag wirklich löschen?"
/>
```

**Props:**
- `edit-action`: Livewire-Methode für Edit
- `delete-action`: Livewire-Methode für Delete
- `item-id`: UUID des Eintrags
- `confirm-delete`: Bestätigungstext

### 2. PHP-Trait (optional, noch nicht integriert)

`app/Livewire/Concerns/HasCrudEntity.php` bietet wiederverwendbare Methoden für CRUD-Logik.

**Status:** Erstellt, aber noch nicht in Verwendung. Die PHP-CRUD-Methoden variieren zu stark zwischen Entitäten (unterschiedliche Felder, Validierung), um sinnvoll abstrahiert zu werden. Der Trait bleibt für zukünftige Evaluation vorhanden.

## Migration bestehender Phase-Dateien

### Vorher (Boilerplate-Code):
```blade
<div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
    <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
            Strukturmodellwahl
            <span class="ml-1 text-xs font-normal text-neutral-500">({{ $strukturmodelle->count() }})</span>
        </h3>
        <button wire:click="newSmw" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">+ Neu</button>
    </div>

    @if ($showSmwForm)
        <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Modell *</label>
                    <input wire:model="smwModell" type="text" class="...">
                </div>
                <!-- 40+ Zeilen Formular-HTML -->
            </div>
            <div class="mt-3 flex gap-2">
                <button wire:click="saveSmw" class="...">Speichern</button>
                <button wire:click="cancelSmw" class="...">Abbrechen</button>
            </div>
        </div>
    @endif

    <!-- 60+ Zeilen Tabellen-HTML mit duplizierten SVG-Icons -->
</div>
```

### Nachher (mit Komponenten):
```blade
<x-crud.section title="Strukturmodellwahl" :count="$strukturmodelle->count()" new-action="newSmw">
    <x-crud.form :visible="$showSmwForm" save-action="saveSmw" cancel-action="cancelSmw">
        <div class="grid gap-3 sm:grid-cols-3">
            <x-crud.field label="Modell" required>
                <input wire:model="smwModell" type="text" class="...">
            </x-crud.field>
            <!-- Weitere Felder -->
        </div>
    </x-crud.form>

    @if ($strukturmodelle->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="...">
                <!-- Tabellen-Header -->
                <tbody class="...">
                    @foreach ($strukturmodelle as $s)
                        <tr class="...">
                            <!-- Tabellen-Zellen -->
                            <x-crud.actions edit-action="editSmw" delete-action="deleteSmw" :item-id="$s->id" />
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="...">Noch keine Strukturmodelle bewertet.</p>
    @endif
</x-crud.section>
```

**Ergebnis:**
- ~40 Zeilen Boilerplate → ~20 Zeilen fokussierter Code
- Buttons, Icons, Styling zentral wartbar
- Neue CRUD-Sektionen in ~50% weniger Zeilen

## Status

- [x] Komponenten erstellt (`x-crud.section`, `x-crud.form`, `x-crud.field`, `x-crud.actions`)
- [x] `phase-p1.blade.php` vollständig refaktoriert (4 Sektionen)
- [ ] `phase-p2.blade.php` bis `phase-p8.blade.php` (ausstehend)
- [ ] Tests angepasst (falls nötig)

## Migration-Anleitung für P2–P8

1. **Sektions-Header** ersetzen mit `<x-crud.section>`
2. **Formular-Wrapper** ersetzen mit `<x-crud.form>`
3. **Label + Input** gruppieren mit `<x-crud.field>` (optional, spart ~2-3 Zeilen pro Feld)
4. **Edit/Delete-Buttons** ersetzen mit `<x-crud.actions>` (spart ~15 Zeilen SVG-Code)
5. **Tests ausführen**, um Regressionen zu vermeiden

## Vorteile

- **Wartbarkeit:** Änderungen am CRUD-UI nur an 4 Stellen statt 32
- **Konsistenz:** Alle Phasen nutzen identisches UI-Pattern
- **Lesbarkeit:** Weniger Boilerplate → besserer Fokus auf Business-Logik
- **Barrierefreiheit:** ARIA-Labels zentral hinzufügbar
