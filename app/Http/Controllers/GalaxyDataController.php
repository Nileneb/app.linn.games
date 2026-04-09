<?php

namespace App\Http\Controllers;

use App\Models\Recherche\Projekt;
use Illuminate\Http\JsonResponse;

class GalaxyDataController extends Controller
{
    public function show(Projekt $projekt): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $projekt->id)) {
            abort(400);
        }

        $path = public_path("galaxy-data/{$projekt->id}.json");

        if (! file_exists($path)) {
            return response()->json([
                'error' => "No galaxy data available. Run: php artisan galaxy:generate {$projekt->id}",
            ], 404);
        }

        $contents = file_get_contents($path);
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'Galaxy-Datei ist beschädigt oder unvollständig.',
            ], 500);
        }

        return response()->json($data);
    }
}
