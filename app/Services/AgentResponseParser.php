<?php

namespace App\Services;

class AgentResponseParser
{
    /**
     * Parst einen Agent-Response-String und extrahiert die JSON-Envelope-Struktur.
     *
     * Unterstützte Formate:
     * - {meta, result, db_payload} — Standard-Envelope
     * - {data, db_payload} — Flat-Struktur
     * - Freitext mit eingebettetem JSON-Block (```json ... ```)
     *
     * @return array{content: string, db_payload: ?array, md_files: array, meta: array}
     */
    public function parse(string $rawContent): array
    {
        $trimmed = trim($rawContent);

        $result = [
            'content' => $trimmed,
            'db_payload' => null,
            'md_files' => [],
            'meta' => [],
        ];

        $json = $this->extractJson($trimmed);

        if ($json === null) {
            return $result;
        }

        // Standard-Envelope: {meta, result}
        if (isset($json['meta'], $json['result'])) {
            $result['meta'] = $json['meta'];
            $result['content'] = $json['result']['summary'] ?? $trimmed;
            $result['md_files'] = $json['result']['data']['md_files'] ?? [];
        }

        // Flat-Struktur: {data}
        if (isset($json['data']) && ! isset($json['result'])) {
            $result['content'] = $json['data']['summary'] ?? $trimmed;
            $result['md_files'] = $json['data']['md_files'] ?? [];
        }

        // db_payload extrahieren (in beiden Formaten möglich)
        if (isset($json['db_payload'])) {
            $result['db_payload'] = $json['db_payload'];
        }

        return $result;
    }

    /**
     * Versucht JSON aus dem Response zu extrahieren.
     * Unterstützt: reines JSON, oder JSON in ```json ... ``` Codeblock.
     */
    private function extractJson(string $content): ?array
    {
        // Direktes JSON
        if (str_starts_with($content, '{')) {
            return $this->tryDecode($content);
        }

        // JSON in Markdown-Codeblock
        if (preg_match('/```json\s*\n(.*?)\n```/s', $content, $matches)) {
            return $this->tryDecode($matches[1]);
        }

        return null;
    }

    private function tryDecode(string $json): ?array
    {
        try {
            $decoded = json_decode(trim($json), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
