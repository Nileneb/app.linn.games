<?php

namespace App\Livewire\Forms\Concerns;

trait HasModelMapping
{
    /**
     * Maps form property names to model column names.
     * Override in the form class to define the mapping.
     *
     * @return array<string, string>
     */
    abstract protected function fieldMap(): array;

    public function fillFromModel(object $model): void
    {
        foreach ($this->fieldMap() as $prop => $column) {
            $this->{$prop} = $model->{$column} ?? '';
        }
    }

    protected function mappedArray(): array
    {
        $result = [];
        foreach ($this->fieldMap() as $prop => $column) {
            $value = $this->{$prop};
            $result[$column] = $value !== '' ? $value : null;
        }
        return $result;
    }
}
