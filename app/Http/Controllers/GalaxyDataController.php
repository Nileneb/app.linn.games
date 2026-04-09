<?php

namespace App\Http\Controllers;

use App\Models\Recherche\Projekt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalaxyDataController extends Controller
{
    public function show(Request $request, Projekt $projekt): JsonResponse
    {
        $path = public_path("galaxy-data/{$projekt->id}.json");

        if (! file_exists($path)) {
            return response()->json([
                'error' => "No galaxy data available. Run: php artisan galaxy:generate {$projekt->id}",
            ], 404);
        }

        $data = json_decode(file_get_contents($path), true);

        return response()->json($data);
    }
}
