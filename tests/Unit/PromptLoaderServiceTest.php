<?php

use App\Services\PromptLoaderService;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->agentDir = resource_path('prompts/agents');
    $this->skillDir = resource_path('prompts/skills');
    File::ensureDirectoryExists($this->agentDir);
    File::ensureDirectoryExists($this->skillDir);
});

afterEach(function () {
    File::deleteDirectory(resource_path('prompts'));
});

test('buildSystemPrompt lädt agent ohne skills', function () {
    File::put(resource_path('prompts/agents/test-agent.md'), "---\nskills: []\n---\nDu bist ein Testagent.\n");

    $service = app(PromptLoaderService::class);
    $result = $service->buildSystemPrompt('test-agent');

    expect($result)->toContain('Du bist ein Testagent.');
});

test('buildSystemPrompt konkateniert skills aus frontmatter', function () {
    File::put(resource_path('prompts/agents/test-agent.md'), "---\nskills: [skill-a, skill-b]\n---\nAgent-Body.\n");
    File::put(resource_path('prompts/skills/skill-a.md'), "---\n---\n# Skill A\nSkill-A-Content.\n");
    File::put(resource_path('prompts/skills/skill-b.md'), "---\n---\n# Skill B\nSkill-B-Content.\n");

    $result = app(PromptLoaderService::class)->buildSystemPrompt('test-agent');

    expect($result)
        ->toContain('Agent-Body.')
        ->toContain('Skill-A-Content.')
        ->toContain('Skill-B-Content.');
});

test('buildSystemPrompt wirft exception bei fehlendem agent', function () {
    expect(fn () => app(PromptLoaderService::class)->buildSystemPrompt('nicht-vorhanden'))
        ->toThrow(\RuntimeException::class, 'nicht-vorhanden');
});

test('buildSystemPrompt ignoriert fehlende skill-datei mit warnung', function () {
    File::put(resource_path('prompts/agents/test-agent.md'), "---\nskills: [existiert-nicht]\n---\nBody.\n");

    $result = app(PromptLoaderService::class)->buildSystemPrompt('test-agent');

    expect($result)->toContain('Body.');
    // Kein Exception — fehlende Skills werden geloggt, nicht geworfen
});

test('frontmatter wird aus body entfernt', function () {
    File::put(resource_path('prompts/agents/test-agent.md'), "---\nskills: []\n---\nNur der Body.\n");

    $result = app(PromptLoaderService::class)->buildSystemPrompt('test-agent');

    expect($result)->not->toContain('skills:');
    expect($result)->toContain('Nur der Body.');
});
