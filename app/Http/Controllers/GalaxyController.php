<?php

namespace App\Http\Controllers;

use App\Models\Recherche\Projekt;
use Illuminate\View\View;

class GalaxyController extends Controller
{
    public function show(Projekt $projekt): View
    {
        return view('galaxy.show', [
            'projektId' => $projekt->id,
        ]);
    }
}
