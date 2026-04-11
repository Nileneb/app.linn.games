<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;

class AppLogViewerPage extends Page
{
    protected string $view = 'filament.pages.app-log-viewer';

    protected static ?string $slug = 'app-logs';

    protected static ?string $navigationLabel = 'App Logs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'App Logs';

    public string $filterLevel = 'all';

    /** @var array<int, array{level: string, raw: string}> */
    public array $logLines = [];

    public function mount(): void
    {
        $this->loadLogs();
    }

    public function updatedFilterLevel(): void
    {
        $this->loadLogs();
    }

    private function loadLogs(): void
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            $this->logLines = [];

            return;
        }

        $lines = $this->tailFile($logPath, 300);

        $parsed = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $level = $this->detectLevel($line);

            if ($this->filterLevel !== 'all' && strtolower($level) !== strtolower($this->filterLevel)) {
                continue;
            }

            $parsed[] = [
                'level' => $level,
                'raw' => mb_substr($line, 0, 300),
            ];
        }

        $this->logLines = array_slice(array_reverse($parsed), 0, 150);
    }

    /**
     * @return string[]
     */
    private function tailFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $startLine = max(0, $totalLines - $lines);

        $result = [];
        $file->seek($startLine);
        while (! $file->eof()) {
            $result[] = rtrim((string) $file->current());
            $file->next();
        }

        return $result;
    }

    private function detectLevel(string $line): string
    {
        foreach (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'] as $level) {
            if (str_contains(strtoupper($line), '.'.$level) || str_contains(strtoupper($line), $level.':')) {
                return $level;
            }
        }

        return 'INFO';
    }

    public function getLevelOptions(): array
    {
        return [
            'all' => 'Alle Level',
            'ERROR' => 'ERROR',
            'WARNING' => 'WARNING',
            'INFO' => 'INFO',
            'DEBUG' => 'DEBUG',
        ];
    }
}
