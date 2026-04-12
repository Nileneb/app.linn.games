<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresMayringSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user()?->currentWorkspace();

        if (! $workspace || ! $workspace->hasMayringAccess()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Mayring-Abo erforderlich'], 403);
            }

            return redirect()->route('mayring.subscribe')
                ->with('info', 'Mayring Memory erfordert ein aktives Abo (€5/Monat).');
        }

        return $next($request);
    }
}
