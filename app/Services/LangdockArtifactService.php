<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LangdockArtifactService
{
    /**
     * @return array{display_content: string, stored_paths: array<int, string>}
     */
    public function persistFromAgentResponse(string $rawContent, array $context = [], array $options = []): array
    {
        $options = array_merge([
            'always_write_md' => false,
            'scope' => 'generic', // chat|phase|generic
            'phase_nr' => null,
            'config_key' => null,
            'basename' => null,
        ], $options);

        $structured = (bool) ($context['structured_output'] ?? false);

        $stored = [];
        $display = $rawContent;

        if ($structured) {
            $envelope = $this->tryParseEnvelope($rawContent);

            if ($envelope !== null) {
                $summary = $this->extractSummary($envelope);
                if (is_string($summary) && $summary !== '') {
                    $display = $summary;
                }

                $dir = $this->baseDir($context, $options);

                $stored[] = $this->putJson($dir, $options['basename'] ?? 'structured-output', $envelope);

                $mdFiles = $this->extractMdFiles($envelope);

                if ($mdFiles === [] && is_string($summary) && $summary !== '') {
                    $mdFiles = [[
                        'path' => 'summary.md',
                        'content' => $summary,
                    ]];
                }

                foreach ($mdFiles as $file) {
                    $path = $this->putMarkdown($dir, (string) $file['path'], (string) $file['content']);
                    if ($path !== null) {
                        $stored[] = $path;
                    }
                }

                return ['display_content' => $display, 'stored_paths' => array_values(array_filter($stored))];
            }

            Log::warning('Structured output requested, but response was not valid JSON envelope', [
                'response_excerpt' => Str::limit($rawContent, 400),
                'context' => array_intersect_key($context, array_flip(['projekt_id', 'workspace_id', 'user_id', 'triggerword', 'config_key'])),
            ]);
        }

        if ((bool) $options['always_write_md']) {
            $dir = $this->baseDir($context, $options);
            $base = $options['basename'] ?? 'agent-response';
            $path = $this->putMarkdown($dir, $base.'.md', $rawContent);
            if ($path !== null) {
                $stored[] = $path;
            }
        }

        return ['display_content' => $display, 'stored_paths' => array_values(array_filter($stored))];
    }

    private function baseDir(array $context, array $options): string
    {
        $parts = ['langdock', 'artifacts'];

        if (! empty($context['projekt_id'])) {
            $parts[] = 'projekte';
            $parts[] = (string) $context['projekt_id'];
        } elseif (! empty($context['workspace_id'])) {
            $parts[] = 'workspaces';
            $parts[] = (string) $context['workspace_id'];
        }

        if (($options['scope'] ?? null) === 'phase' && isset($options['phase_nr'])) {
            $parts[] = 'phasen';
            $parts[] = 'p'.(int) $options['phase_nr'];
        } elseif (($options['scope'] ?? null) === 'chat') {
            $parts[] = 'chat';
        }

        if (! empty($options['config_key'])) {
            $parts[] = (string) $options['config_key'];
        }

        return implode('/', $parts);
    }

    private function tryParseEnvelope(string $rawContent): ?array
    {
        $trimmed = trim($rawContent);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (! isset($decoded['meta'], $decoded['result'], $decoded['warnings'])) {
            return null;
        }

        if (! is_array($decoded['meta']) || ! is_array($decoded['result']) || ! is_array($decoded['warnings'])) {
            return null;
        }

        return $decoded;
    }

    private function extractSummary(array $envelope): ?string
    {
        $summary = $envelope['result']['summary'] ?? null;

        return is_string($summary) ? $summary : null;
    }

    /**
     * @return array<int, array{path: string, content: string}>
     */
    private function extractMdFiles(array $envelope): array
    {
        $data = $envelope['result']['data'] ?? null;
        if (! is_array($data)) {
            return [];
        }

        $mdFiles = $data['md_files'] ?? null;
        if (! is_array($mdFiles)) {
            return [];
        }

        $out = [];
        foreach ($mdFiles as $file) {
            if (! is_array($file)) {
                continue;
            }

            $path = $file['path'] ?? $file['name'] ?? $file['filename'] ?? null;
            $content = $file['content'] ?? null;

            if (! is_string($path) || $path === '' || ! is_string($content)) {
                continue;
            }

            $out[] = [
                'path' => $path,
                'content' => $content,
            ];
        }

        return $out;
    }

    private function putJson(string $dir, string $baseName, array $payload): ?string
    {
        $ts = now()->format('Ymd-His');
        $name = $this->sanitizeFilename($baseName) ?: 'structured-output';
        $path = $dir.'/'.$ts.'-'.$name.'.json';

        try {
            Storage::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Failed to store structured output JSON artifact', [
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function putMarkdown(string $dir, string $relativePath, string $content): ?string
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return null;
        }

        $relativePath = $this->sanitizePath($relativePath);
        $path = $dir.'/'.$relativePath;

        try {
            Storage::put($path, rtrim($content, "\n")."\n");

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Failed to store markdown artifact', [
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '-');

        return mb_substr($name, 0, 80);
    }

    private function sanitizePath(string $path): string
    {
        $path = str_replace('..', '', $path);
        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));

        $out = [];
        foreach ($segments as $seg) {
            $out[] = $this->sanitizeFilename($seg);
        }

        $result = implode('/', array_filter($out, static fn ($s) => $s !== ''));

        return $result !== '' ? $result : 'artifact.md';
    }
}
