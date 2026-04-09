<?php

namespace App\Http\Controllers;

use App\Models\Recherche\Projekt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GalaxyController extends Controller
{
    public function show(Request $request, Projekt $projekt): View
    {
        return view('galaxy.show', [
            'projektId' => $projekt->id,
        ]);
    }
}
