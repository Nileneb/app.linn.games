<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait für Standard-CRUD-Operationen in Phase-Komponenten.
 *
 * Reduziert Boilerplate für wiederholte CRUD-Patterns über 32+ Entitäten in P1–P8.
 *
 * Convention-based naming:
 * - Properties: $show{Entity}Form, $editing{Entity}Id, ${entity}Field1, ...
 * - Methods: new{Entity}(), save{Entity}(), edit{Entity}(), delete{Entity}(), cancel{Entity}()
 *
 * @example
 * ```php
 * use HasCrudEntity;
 *
 * // Properties definieren (Convention-based)
 * public bool $showSmwForm = false;
 * public ?string $editingSmwId = null;
 * public string $smwModell = '';
 *
 * // CRUD-Methoden nutzen
 * public function newSmw(): void { $this->newEntity('Smw'); }
 * public function saveSmw(): void { $this->saveEntity('Smw', P1Strukturmodellwahl::class, ['smwModell' => 'required'], fn() => [...data...]); }
 * public function editSmw(string $id): void { $this->editEntity('Smw', P1Strukturmodellwahl::class, $id, fn($r) => ['smwModell' => $r->modell]); }
 * public function deleteSmw(string $id): void { $this->deleteEntity(P1Strukturmodellwahl::class, $id); }
 * public function cancelSmw(): void { $this->cancelEntity('Smw', ['smwModell']); }
 * ```
 */
trait HasCrudEntity
{
    /**
     * Öffnet ein leeres Formular für eine neue Entität.
     *
     * @param string $entity Entity-Kürzel (z.B. 'Smw', 'Komp', 'Krit')
     */
    protected function newEntity(string $entity): void
    {
        $cancelMethod = "cancel{$entity}";
        $this->$cancelMethod();
        $this->{"show{$entity}Form"} = true;
    }

    /**
     * Speichert (erstellt oder aktualisiert) eine Entität.
     *
     * @param string $entity Entity-Kürzel
     * @param class-string<Model> $modelClass Eloquent Model-Klasse
     * @param array<string, string> $validationRules Validierungsregeln
     * @param callable(): array<string, mixed> $dataBuilder Closure, die das $data-Array zurückgibt
     */
    protected function saveEntity(
        string $entity,
        string $modelClass,
        array $validationRules,
        callable $dataBuilder
    ): void {
        if ($validationRules) {
            $this->validate($validationRules);
        }

        $data = $dataBuilder();
        $editingIdProp = "editing{$entity}Id";

        if ($this->$editingIdProp) {
            /** @var Model $model */
            $model = $modelClass::where('projekt_id', $this->projekt->id)
                ->whereKey($this->$editingIdProp)
                ->firstOrFail();
            $model->update($data);
        } else {
            $modelClass::create($data);
        }

        $cancelMethod = "cancel{$entity}";
        $this->$cancelMethod();
    }

    /**
     * Lädt eine bestehende Entität ins Bearbeitungsformular.
     *
     * @param string $entity Entity-Kürzel
     * @param class-string<Model> $modelClass Eloquent Model-Klasse
     * @param string $id UUID der Entität
     * @param callable(Model): array<string, mixed> $propertyMapper Closure, die Model → Component-Properties mappt
     */
    protected function editEntity(
        string $entity,
        string $modelClass,
        string $id,
        callable $propertyMapper
    ): void {
        /** @var Model $record */
        $record = $modelClass::where('projekt_id', $this->projekt->id)
            ->whereKey($id)
            ->firstOrFail();

        $editingIdProp = "editing{$entity}Id";
        $this->$editingIdProp = $id;

        $properties = $propertyMapper($record);
        foreach ($properties as $key => $value) {
            $this->$key = $value;
        }

        $this->{"show{$entity}Form"} = true;
    }

    /**
     * Löscht eine Entität.
     *
     * @param class-string<Model> $modelClass Eloquent Model-Klasse
     * @param string $id UUID der Entität
     */
    protected function deleteEntity(string $modelClass, string $id): void
    {
        /** @var Model $model */
        $model = $modelClass::where('projekt_id', $this->projekt->id)
            ->whereKey($id)
            ->firstOrFail();
        $model->delete();
    }

    /**
     * Bricht Formular-Bearbeitung ab und setzt alle Entity-Properties zurück.
     *
     * @param string $entity Entity-Kürzel
     * @param array<string> $propertyNames Liste der zu resettenden Property-Namen
     * @param array<string, mixed> $defaultValues Optional: Default-Werte nach Reset
     */
    protected function cancelEntity(string $entity, array $propertyNames, array $defaultValues = []): void
    {
        $this->{"show{$entity}Form"} = false;
        $this->{"editing{$entity}Id"} = null;

        if ($propertyNames) {
            $this->reset($propertyNames);
        }

        foreach ($defaultValues as $key => $value) {
            $this->$key = $value;
        }
    }
}
