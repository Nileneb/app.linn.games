<?php

namespace App\Livewire\Forms\Recherche;

use App\Livewire\Forms\Concerns\HasModelMapping;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ThesaurusMappingForm extends Form
{
    use HasModelMapping;

    #[Validate('required|string|max:255')]
    public string $freitextDe = '';

    public string $freitextEn = '';

    public string $mesh = '';

    public string $emtree = '';

    public string $psycinfo = '';

    public string $anmerkung = '';

    protected function fieldMap(): array
    {
        return [
            'freitextDe' => 'freitext_de',
            'freitextEn' => 'freitext_en',
            'mesh' => 'mesh_term',
            'emtree' => 'emtree_term',
            'psycinfo' => 'psycinfo_term',
            'anmerkung' => 'anmerkung',
        ];
    }

    public function toPersistArray(string $projektId): array
    {
        return array_merge(['projekt_id' => $projektId], $this->mappedArray());
    }
}
