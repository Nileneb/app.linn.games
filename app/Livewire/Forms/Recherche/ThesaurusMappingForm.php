<?php

namespace App\Livewire\Forms\Recherche;

use App\Models\Recherche\P4ThesaurusMapping;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ThesaurusMappingForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $freitextDe = '';

    public string $freitextEn = '';
    public string $mesh = '';
    public string $emtree = '';
    public string $psycinfo = '';
    public string $anmerkung = '';

    public function fillFromModel(P4ThesaurusMapping $r): void
    {
        $this->freitextDe = $r->freitext_de ?? '';
        $this->freitextEn = $r->freitext_en ?? '';
        $this->mesh       = $r->mesh_term ?? '';
        $this->emtree     = $r->emtree_term ?? '';
        $this->psycinfo   = $r->psycinfo_term ?? '';
        $this->anmerkung  = $r->anmerkung ?? '';
    }

    public function toArray(string $projektId): array
    {
        return [
            'projekt_id'   => $projektId,
            'freitext_de'  => $this->freitextDe,
            'freitext_en'  => $this->freitextEn ?: null,
            'mesh_term'    => $this->mesh ?: null,
            'emtree_term'  => $this->emtree ?: null,
            'psycinfo_term' => $this->psycinfo ?: null,
            'anmerkung'    => $this->anmerkung ?: null,
        ];
    }
}
