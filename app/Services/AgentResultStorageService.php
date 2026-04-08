<?php

namespace App\Services;

use App\Jobs\IngestAgentResultJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores agent responses as Markdown files for data separation & compliance.
 *
 * Storage structure:
 *   storage/app/agent-results/{workspace_id}/{user_id}/{project_slug}/P{phase}__{agent_name}__{timestamp}.md
 *
 * This separates user data from research data and provides better auditability.
 */
class AgentResultStorageService
{
    protected string $disk = 'local';

    /**
     * Save agent result as Markdown file
     *
     * @param  string  $workspaceId  UUID of workspace
     * @param  int  $userId  User ID
     * @param  string  $projectId  UUID or slug of project
     * @param  int  $phaseNumber  Phase number (1-8)
     * @param  array  $response  Agent response with 'content' and 'raw' keys
     * @param  ?string  $agentName  Name/ID of agent (optional, for filename)
     * @return string  Relative file path
     */
    public function saveResult(
        string $workspaceId,
        int $userId,
        string $projectId,
        int $phaseNumber,
        array $response,
        ?string $agentName = null,
    ): string {
        $path = $this->buildPath($workspaceId, $userId, $projectId, $phaseNumber, $agentName);
        $content = $this->formatAsMarkdown($response, $agentName);

        Storage::disk($this->disk)->put($path, $content);

        IngestAgentResultJob::dispatch($path, $workspaceId, (string) $userId, $projectId);

        return $path;
    }

    /**
     * Save a chat protocol as a Markdown file and dispatch embedding ingest.
     *
     * @param  string  $content     Raw chat content (Markdown-formatted conversation)
     * @param  string  $workspaceId UUID of workspace
     * @param  string  $userId      User ID (string)
     * @param  string  $projektId   UUID or slug of project
     * @return string  Relative file path within storage/app/
     */
    public function storeChat(
        string $content,
        string $workspaceId,
        string $userId,
        string $projektId,
    ): string {
        $path = $this->buildChatPath($workspaceId, $userId, $projektId);

        Storage::disk($this->disk)->put($path, $content);

        IngestAgentResultJob::dispatch($path, $workspaceId, $userId, $projektId);

        return $path;
    }

    /**
     * Read agent result from file
     *
     * @return string|null  File content or null if not found
     */
    public function readResult(
        string $workspaceId,
        int $userId,
        string $projectId,
        int $phaseNumber,
        ?string $agentName = null,
    ): ?string {
        $path = $this->buildPath($workspaceId, $userId, $projectId, $phaseNumber, $agentName);

        if (! Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->disk)->get($path);
    }

    /**
     * List all result files for a project
     *
     * @return array  List of file paths
     */
    public function listResults(
        string $workspaceId,
        int $userId,
        string $projectId,
    ): array {
        $basePath = "agent-results/$workspaceId/$userId/{$projectId}";

        if (! Storage::disk($this->disk)->exists($basePath)) {
            return [];
        }

        return Storage::disk($this->disk)->files($basePath);
    }

    /**
     * Delete result file(s)
     */
    public function deleteResult(
        string $workspaceId,
        int $userId,
        string $projectId,
        int $phaseNumber,
        ?string $agentName = null,
    ): bool {
        $path = $this->buildPath($workspaceId, $userId, $projectId, $phaseNumber, $agentName);

        if (! Storage::disk($this->disk)->exists($path)) {
            return false;
        }

        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Delete all results for a project (e.g., when deleting project)
     */
    public function deleteProjectResults(
        string $workspaceId,
        int $userId,
        string $projectId,
    ): bool {
        $basePath = "agent-results/$workspaceId/$userId/{$projectId}";

        if (! Storage::disk($this->disk)->exists($basePath)) {
            return true;
        }

        return Storage::disk($this->disk)->deleteDirectory($basePath);
    }

    /**
     * Build file path for result
     */
    protected function buildPath(
        string $workspaceId,
        int $userId,
        string $projectId,
        int $phaseNumber,
        ?string $agentName = null,
    ): string {
        $timestamp = now()->format('YmdHis');
        $agentSuffix = $agentName ? "_{$agentName}" : '';
        $filename = sprintf(
            'P%d%s__%s.md',
            $phaseNumber,
            $agentSuffix,
            $timestamp,
        );

        return "agent-results/$workspaceId/$userId/{$projectId}/{$filename}";
    }

    /**
     * Build file path for a chat protocol.
     * Pattern: agent-results/{workspace_id}/{user_id}/{projekt_id}/chat__{timestamp}.md
     */
    protected function buildChatPath(
        string $workspaceId,
        string $userId,
        string $projektId,
    ): string {
        $timestamp = now()->format('YmdHis');
        $filename = "chat__{$timestamp}.md";

        return "agent-results/{$workspaceId}/{$userId}/{$projektId}/{$filename}";
    }

    /**
     * Format response as Markdown document
     */
    protected function formatAsMarkdown(array $response, ?string $agentName = null): string {
        $content = $response['content'] ?? '';
        $raw = $response['raw'] ?? [];

        $timestamp = now()->format('Y-m-d H:i:s');
        $agentLabel = $agentName ? "Agent: **$agentName**" : 'Agent Response';

        $md = <<<MARKDOWN
# Agent Response

| Field | Value |
|-------|-------|
| Generated | $timestamp |
| $agentLabel | - |

## Response

$content

---

## Metadata

```json
METADATA_JSON
```
MARKDOWN;

        // Append raw response metadata as JSON
        if ($raw !== []) {
            $json = json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $md = str_replace('METADATA_JSON', $json, $md);
        } else {
            $md = str_replace('```json\nMETADATA_JSON\n```', '(No metadata available)', $md);
        }

        return $md;
    }
}
