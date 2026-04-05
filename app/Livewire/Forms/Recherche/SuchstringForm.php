<?php

namespace App\Livewire\Forms\Recherche;

use App\Models\Recherche\P4Suchstring;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SuchstringForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $datenbank = '';

    public string $suchstring = '';
    public string $feldeinschraenkung = '';
    public string $filter = '';
    public ?int $trefferAnzahl = null;
    public string $einschaetzung = '';
    public string $anpassung = '';
    public string $version = '';
    public ?string $suchdatum = null;

    public function fillFromModel(P4Suchstring $r): void
    {
        $this->datenbank        = $r->datenbank ?? '';
        $this->suchstring       = $r->suchstring ?? '';
        $this->feldeinschraenkung = $r->feldeinschraenkung ?? '';
        $this->filter           = is_array($r->gesetzte_filter) ? implode(', ', $r->gesetzte_filter) : '';
        $this->trefferAnzahl    = $r->treffer_anzahl;
        $this->einschaetzung    = $r->einschaetzung ?? '';
        $this->anpassung        = $r->anpassung ?? '';
        $this->version          = $r->version ?? '';
        $this->suchdatum        = $r->suchdatum?->format('Y-m-d');
    }

    public function toPersistArray(string $projektId): array
    {
        return [
            'projekt_id'        => $projektId,
            'datenbank'         => $this->datenbank,
            'suchstring'        => $this->suchstring ?: null,
            'feldeinschraenkung' => $this->feldeinschraenkung ?: null,
            'gesetzte_filter'   => $this->filter ? array_map('trim', explode(',', $this->filter)) : null,
            'treffer_anzahl'    => $this->trefferAnzahl,
            'einschaetzung'     => $this->einschaetzung ?: null,
            'anpassung'         => $this->anpassung ?: null,
            'version'           => $this->version ?: 'v1.0',
            'suchdatum'         => $this->suchdatum ?: null,
        ];
    }
}
