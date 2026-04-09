<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

class PromptLoaderService
{
    /**
     * Lädt den System-Prompt für einen Agent:
     * 1. Liest resources/prompts/agents/{agentKey}.md
     * 2. Parst YAML-Frontmatter für 'skills' Liste
     * 3. Hängt jeden Skill an
     * 4. Gibt fertigen System-Prompt zurück
     */
    public function buildSystemPrompt(string $agentKey): string
    {
        $agentPath = resource_path("prompts/agents/{$agentKey}.md");

        if (! File::exists($agentPath)) {
            throw new \RuntimeException("Agent-Prompt nicht gefunden: {$agentKey}");
        }

        [$frontmatter, $body] = $this->parseFrontmatter(File::get($agentPath));

        $skills = $frontmatter['skills'] ?? [];
        $skillContent = '';

        foreach ($skills as $skill) {
            $skillPath = resource_path("prompts/skills/{$skill}.md");

            if (! File::exists($skillPath)) {
                Log::warning("Skill-Prompt nicht gefunden: {$skill}", ['agent' => $agentKey]);

                continue;
            }

            [, $skillBody] = $this->parseFrontmatter(File::get($skillPath));
            $skillContent .= "\n\n---\n\n".$skillBody;
        }

        return trim($body).$skillContent;
    }

    /**
     * Parst YAML-Frontmatter aus .md-Datei.
     *
     * @return array{0: array, 1: string} [frontmatter, body]
     */
    private function parseFrontmatter(string $content): array
    {
        if (! str_starts_with($content, '---')) {
            return [[], $content];
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);

        if (count($parts) < 3) {
            return [[], $content];
        }

        $frontmatter = Yaml::parse(trim($parts[1])) ?? [];

        return [$frontmatter, $parts[2]];
    }
}
