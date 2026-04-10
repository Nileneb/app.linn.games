<?php

use App\Services\ClaudeCliService;
use Illuminate\Support\Facades\Process;

uses(Tests\TestCase::class);

test('call() gibt content aus Claude CLI JSON-Output zurück', function () {
    Process::fake([
        'claude*' => Process::result(
            output: json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'Hallo vom Mock-Claude',
            ]),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $result = $service->call('Test-Frage', ['projekt_id' => 'abc123']);

    expect($result['content'])->toBe('Hallo vom Mock-Claude');
});

test('call() wirft ClaudeCliException bei Fehler-Exit-Code', function () {
    Process::fake([
        'claude*' => Process::result(
            output: '',
            errorOutput: 'Claude CLI not found',
            exitCode: 1,
        ),
    ]);

    $service = app(ClaudeCliService::class);

    expect(fn () => $service->call('Test', []))
        ->toThrow(\App\Services\ClaudeCliException::class);
});

test('call() sendet --output-format json und --print Flags', function () {
    Process::fake([
        'claude*' => Process::result(
            output: json_encode(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'result' => 'OK']),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $service->call('Frage', ['projekt_id' => 'xyz']);

    Process::assertRan(fn ($process) => str_contains($process->command, '--output-format') &&
        str_contains($process->command, 'json') &&
        str_contains($process->command, '--print')
    );
});
