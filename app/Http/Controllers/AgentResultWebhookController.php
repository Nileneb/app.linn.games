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

        // Persist markdown files to storage with path traversal protection
        $basePath = "recherche/{$projektId}/{$phase}";
        try {
            foreach ($mdFiles as $file) {
                $safePath = $this->validateFilePath($file['path']);
                Storage::disk('local')->put("{$basePath}/{$safePath}", $file['content']);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid file path: ' . $e->getMessage()], 400);
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

        // Validate timestamp to prevent replay attacks
        $timestamp = $request->header('X-Langdock-Timestamp');
        if (!$timestamp) {
            return false;
        }

        // Verify timestamp is a valid Unix timestamp
        if (!is_numeric($timestamp) || $timestamp != (int) $timestamp) {
            return false;
        }

        // Reject requests older than 5 minutes
        $maxAge = 300; // seconds
        $currentTime = time();
        if (abs($currentTime - (int) $timestamp) > $maxAge) {
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

    /**
     * Validate file path to prevent directory traversal attacks.
     *
     * Rejects paths with:
     * - Parent directory references (..)
     * - Leading slashes or backslashes
     * - Double slashes
     * - Unsafe characters
     *
     * @param string $path The file path to validate
     * @return string The validated, normalized path
     * @throws \InvalidArgumentException If path is unsafe
     */
    private function validateFilePath(string $path): string
    {
        // Reject paths with parent directory references
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException('Path traversal detected: ".." not allowed in file path');
        }

        // Reject leading slashes or backslashes
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new \InvalidArgumentException('Absolute paths not allowed');
        }

        // Reject double slashes
        if (str_contains($path, '//') || str_contains($path, '\\\\')) {
            throw new \InvalidArgumentException('Double slashes not allowed in file path');
        }

        // Only allow safe characters: alphanumeric, hyphen, underscore, dot, forward slash
        if (!preg_match('/^[a-zA-Z0-9._\/-]+$/', $path)) {
            throw new \InvalidArgumentException('File path contains unsafe characters');
        }

        // Ensure path ends with .md (for markdown files)
        if (!str_ends_with($path, '.md')) {
            throw new \InvalidArgumentException('File path must end with .md extension');
        }

        return $path;
    }
}
