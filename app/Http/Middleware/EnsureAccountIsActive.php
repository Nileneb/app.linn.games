<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Auth::check() && ! in_array(Auth::user()->status, ['active'])) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('pending-approval');
            }
        } catch (QueryException|\PDOException) {
            // DB not yet available (e.g. PostgreSQL still starting up after deploy).
            // Fail-open: let the request through rather than showing a crash page.
            return $next($request);
        }

        return $next($request);
    }
}
