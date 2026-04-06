<?php

namespace App\Http\Controllers;

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AgentResultWebhookController extends Controller
{
    public function handleAgentResult(Request $request)
    {
        // Verify HMAC signature
        if (!$this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Validate payload structure
        $validated = Validator::make($request->all(), [
            'meta.projekt_id' => 'required|uuid',
            'meta.phase' => 'required|string|in:recherche,screening,auswertung',
            'result.type' => 'required|string',
            'result.data.md_files' => 'required|array',
            'result.data.md_files.*.path' => 'required|string',
            'result.data.md_files.*.content' => 'required|string',
        ])->validate();

        $projektId = $validated['meta']['projekt_id'];
        $phase = $validated['meta']['phase'];
        $mdFiles = $validated['result']['data']['md_files'];

        // Verify Projekt exists
        $projekt = Projekt::findOrFail($projektId);

        // Persist markdown files to storage
        $basePath = "recherche/{$projektId}/{$phase}";
        foreach ($mdFiles as $file) {
            Storage::disk('local')->put("{$basePath}/{$file['path']}", $file['content']);
        }

        // Map phase name to phase_nr for legacy schema
        $phaseMap = [
            'recherche' => 1,
            'screening' => 2,
            'auswertung' => 3,
        ];
        $phaseNr = $phaseMap[$phase] ?? 1;

        // Create/update PhaseAgentResult record
        PhaseAgentResult::updateOrCreate(
            ['projekt_id' => $projektId, 'phase_nr' => $phaseNr, 'agent_config_key' => 'webhook_result'],
            [
                'user_id' => $projekt->user_id,
                'phase' => $phase,
                'status' => 'completed',
                'result_data' => [
                    'summary' => $validated['result']['summary'] ?? null,
                    'type' => $validated['result']['type'],
                    'md_file_count' => count($mdFiles),
                    'workspace_id' => $validated['meta']['workspace_id'] ?? null,
                ],
            ]
        );

        return response()->json(['status' => 'success', 'phase' => $phase], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Langdock-Signature');
        if (!$signature) {
            return false;
        }

        // Parse signature header: "sha256=hash"
        if (! str_contains($signature, '=')) {
            return false;
        }

        [$algorithm, $hash] = explode('=', $signature, 2);

        if ($algorithm !== 'sha256') {
            return false;
        }

        $secret = config('services.langdock.webhook_secret');
        if (!$secret) {
            return false;
        }

        $payload = $request->getContent();
        $expectedHash = hash_hmac('sha256', $payload, $secret);

        return hash_equals($hash, $expectedHash);
    }
}
