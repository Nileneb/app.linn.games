<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreditCheckoutController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->validate(['package' => 'required|integer|min:0|max:3']);

        $workspace = Auth::user()->workspaces()->firstOrFail();
        $url = app(StripeService::class)->createCheckoutSession($workspace, (int) $request->input('package'));

        return redirect()->away($url);
    }
}
