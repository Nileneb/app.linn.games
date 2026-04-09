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
        if (! $this->verifySignature($request)) {
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
            if (! $this->validateFilePath($file['path'])) {
                return response()->json(['error' => 'Invalid file path: '.$file['path']], 400);
            }
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

    private function validateFilePath(string $path): bool
    {
        // Reject parent directory traversal
        if (str_contains($path, '..')) {
            return false;
        }
        // Reject absolute paths
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }
        // Reject double slashes and backslashes
        if (str_contains($path, '//') || str_contains($path, '\\')) {
            return false;
        }
        // Require .md extension
        if (! str_ends_with($path, '.md')) {
            return false;
        }
        // Only allow safe filename characters
        if (! preg_match('/^[\w\-]+\.md$/', $path)) {
            return false;
        }

        return true;
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Langdock-Signature');
        if (! $signature) {
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

        // Validate timestamp to prevent replay attacks
        $timestamp = $request->header('X-Langdock-Timestamp');

        // Ensure header exists and is a digit-only string
        if (! is_string($timestamp) || ! ctype_digit($timestamp)) {
            return false;
        }

        // Cast once to integer for further validation
        $timestamp = (int) $timestamp;

        // Reject requests older than 5 minutes
        $maxAge = 300; // seconds
        $currentTime = time();
        if (abs($currentTime - $timestamp) > $maxAge) {
            return false;
        }

        $secret = config('services.langdock.webhook_secret');
        if (! $secret) {
            return false;
        }

        // Include timestamp in signed payload to prevent replay attacks
        // where an attacker reuses an old signature with a new timestamp
        $payload = $request->header('X-Langdock-Timestamp').'.'.$request->getContent();
        $expectedHash = hash_hmac('sha256', $payload, $secret);

        return hash_equals($hash, $expectedHash);
    }
}
