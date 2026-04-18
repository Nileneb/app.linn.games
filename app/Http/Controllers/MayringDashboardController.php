<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MayringDashboardController extends Controller
{
    public function redirect(Request $request)
    {
        $user = $request->user();

        $token = $user->createToken(
            name: 'mayring-ui-session',
            abilities: ['mcp:memory'],
            expiresAt: now()->addHours(8),
        );

        $url = rtrim(config('services.mayring.ui_url', 'https://mcp.linn.games/ui'), '/')
            . '/?__token=' . urlencode($token->plainTextToken);

        return redirect()->away($url);
    }
}
