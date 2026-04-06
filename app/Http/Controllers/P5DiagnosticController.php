<?php

namespace App\Http\Controllers;

use App\Services\P5DiagnosticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only diagnostic endpoint for P5 schema debugging.
 *
 * Usage: POST /admin/diagnostics/p5-treffer
 * Requires admin authentication.
 */
class P5DiagnosticController extends Controller
{
    public function diagnose(Request $request, P5DiagnosticService $service)
    {
        // Admin-only
        if (! Auth::user()?->is_admin) {
            abort(403, 'Admin access required.');
        }

        $validated = $request->validate([
            'projekt_id' => 'required|uuid',
            'user_id' => 'sometimes|integer',
        ]);

        $results = $service->diagnose(
            $validated['projekt_id'],
            $validated['user_id'] ?? 0,
        );

        return response()->json($results);
    }

    public function getQueryScript(Request $request, P5DiagnosticService $service)
    {
        if (! Auth::user()?->is_admin) {
            abort(403, 'Admin access required.');
        }

        $validated = $request->validate([
            'projekt_id' => 'required|uuid',
        ]);

        $query = $service->getScreeningQuery($validated['projekt_id']);

        return response($query, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="p5_query.sql"',
        ]);
    }
}
