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
     * Unterstützt: reines JSON, JSON mit Trailing-Text, JSON in ```json...``` Codeblock,
     * und JSON irgendwo im Fließtext (Pi-Agent gibt manchmal Präambel aus).
     */
    private function extractJson(string $content): ?array
    {
        // 1. Direktes JSON — versuche zuerst full decode, dann balanced extraction
        if (str_starts_with($content, '{')) {
            $result = $this->tryDecode($content);
            if ($result !== null) {
                return $result;
            }
            // Trailing text nach dem JSON-Objekt? Balanced-Brace-Extraktion
            $result = $this->extractJsonObject($content);
            if ($result !== null) {
                return $result;
            }
        }

        // 2. JSON in Markdown-Codeblock (```json oder ```)
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $content, $matches)) {
            $result = $this->tryDecode($matches[1]);
            if ($result !== null) {
                return $result;
            }
        }

        // 3. JSON irgendwo im Text (Pi-Agent schreibt Präambel vor das JSON)
        $pos = strpos($content, '{');
        if ($pos !== false && $pos > 0) {
            $result = $this->extractJsonObject(substr($content, $pos));
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Extrahiert ein vollständiges JSON-Objekt via Balanced-Brace-Counting.
     * Ignoriert Text nach dem schließenden `}` — robust gegen Pi-Agent Trailing-Text.
     */
    private function extractJsonObject(string $content): ?array
    {
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($content);

        for ($i = 0; $i < $len; $i++) {
            $char = $content[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $this->tryDecode(substr($content, 0, $i + 1));
                }
            }
        }

        return null;
    }

    private function tryDecode(string $json): ?array
    {
        $decoded = json_decode(trim($json), true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }
}
