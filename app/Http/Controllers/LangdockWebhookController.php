<?php

namespace App\Http\Controllers;

use App\Jobs\TriggerLangdockAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LangdockWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'projekt_id' => ['required', 'uuid', 'exists:projekte,id'],
            'eingabe' => ['required', 'string', 'max:10000'],
        ]);

        TriggerLangdockAgent::dispatch(
            $validated['user_id'],
            $validated['projekt_id'],
            $validated['eingabe'],
        );

        return response()->json(['status' => 'queued']);
    }
}
