<?php

namespace Tests\Feature\Services;

use App\Services\AgentResultStorageService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentResultStorageServiceTest extends TestCase
{
    protected AgentResultStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // IngestAgentResultJob requires Ollama — test only covers file operations
        $this->service = app(AgentResultStorageService::class);
        Storage::fake('local');
    }

    public function test_saves_result_with_markdown_formatting(): void
    {
        $workspaceId = 'workspace-123';
        $userId = 1;
        $projectId = 'project-slug';
        $phaseNumber = 1;
        $response = [
            'content' => '# Test Response\n\nThis is a test.',
            'raw' => ['tokens' => 42],
        ];

        $path = $this->service->saveResult(
            $workspaceId,
            $userId,
            $projectId,
            $phaseNumber,
            $response,
            'test-agent',
        );

        expect($path)->toContain('agent-results')
            ->toContain($workspaceId)
            ->toContain($userId)
            ->toContain($projectId)
            ->toContain('P1')
            ->toContain('test-agent');

        Storage::disk('local')->assertExists($path);
    }

    public function test_reads_saved_result(): void
    {
        $workspaceId = 'workspace-123';
        $userId = 1;
        $projectId = 'project-slug';
        $phaseNumber = 2;
        $response = [
            'content' => 'Agent response content',
            'raw' => [],
        ];

        $savedPath = $this->service->saveResult(
            $workspaceId,
            $userId,
            $projectId,
            $phaseNumber,
            $response,
        );

        $content = $this->service->readResult(
            $workspaceId,
            $userId,
            $projectId,
            $phaseNumber,
        );

        expect($content)->toContain('Agent response content');
    }

    public function test_lists_all_results_for_project(): void
    {
        $workspaceId = 'workspace-123';
        $userId = 1;
        $projectId = 'project-slug';

        $this->service->saveResult($workspaceId, $userId, $projectId, 1, ['content' => 'P1']);
        $this->service->saveResult($workspaceId, $userId, $projectId, 2, ['content' => 'P2']);
        $this->service->saveResult($workspaceId, $userId, $projectId, 3, ['content' => 'P3']);

        $results = $this->service->listResults($workspaceId, $userId, $projectId);

        expect($results)->toHaveCount(3);
        $joined = implode('|', $results);
        expect($joined)->toContain('P1__')
            ->toContain('P2__')
            ->toContain('P3__');
    }

    public function test_deletes_single_result(): void
    {
        $workspaceId = 'workspace-123';
        $userId = 1;
        $projectId = 'project-slug';

        $this->service->saveResult($workspaceId, $userId, $projectId, 1, ['content' => 'Test']);

        $deleted = $this->service->deleteResult($workspaceId, $userId, $projectId, 1);

        expect($deleted)->toBeTrue();

        $content = $this->service->readResult($workspaceId, $userId, $projectId, 1);
        expect($content)->toBeNull();
    }

    public function test_deletes_all_project_results(): void
    {
        $workspaceId = 'workspace-123';
        $userId = 1;
        $projectId = 'project-slug';

        $this->service->saveResult($workspaceId, $userId, $projectId, 1, ['content' => 'P1']);
        $this->service->saveResult($workspaceId, $userId, $projectId, 2, ['content' => 'P2']);

        $deleted = $this->service->deleteProjectResults($workspaceId, $userId, $projectId);

        expect($deleted)->toBeTrue();

        $results = $this->service->listResults($workspaceId, $userId, $projectId);
        expect($results)->toBeEmpty();
    }

    public function test_reads_nonexistent_result_returns_null(): void
    {
        $content = $this->service->readResult('workspace', 1, 'project', 99);

        expect($content)->toBeNull();
    }

    public function test_markdown_includes_metadata(): void
    {
        $response = [
            'content' => 'Test response',
            'raw' => ['usage' => ['tokens' => 100]],
        ];

        $path = $this->service->saveResult('ws', 1, 'proj', 1, $response, 'agent');
        $content = Storage::disk('local')->get($path);

        expect($content)
            ->toContain('# Agent Response')
            ->toContain('Test response')
            ->toContain('usage')
            ->toContain('tokens');
    }
}
