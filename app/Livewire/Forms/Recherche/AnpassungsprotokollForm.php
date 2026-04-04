<?php

namespace App\Livewire\Forms\Recherche;

use App\Models\Recherche\P4Anpassungsprotokoll;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AnpassungsprotokollForm extends Form
{
    #[Validate('required|string')]
    public string $suchstringId = '';

    #[Validate('required|string')]
    public string $aenderung = '';

    public string $version = '';
    public ?string $datum = null;
    public string $grund = '';
    public ?int $trefferVorher = null;
    public ?int $trefferNachher = null;
    public string $entscheidung = '';

    public function fillFromModel(P4Anpassungsprotokoll $r): void
    {
        $this->suchstringId   = $r->suchstring_id;
        $this->version        = $r->version ?? '';
        $this->datum          = $r->datum?->format('Y-m-d');
        $this->aenderung      = $r->aenderung ?? '';
        $this->grund          = $r->grund ?? '';
        $this->trefferVorher  = $r->treffer_vorher;
        $this->trefferNachher = $r->treffer_nachher;
        $this->entscheidung   = $r->entscheidung ?? '';
    }

    public function toArray(): array
    {
        return [
            'suchstring_id'    => $this->suchstringId,
            'version'          => $this->version ?: null,
            'datum'            => $this->datum ?: null,
            'aenderung'        => $this->aenderung,
            'grund'            => $this->grund ?: null,
            'treffer_vorher'   => $this->trefferVorher,
            'treffer_nachher'  => $this->trefferNachher,
            'entscheidung'     => $this->entscheidung ?: null,
        ];
    }
}
