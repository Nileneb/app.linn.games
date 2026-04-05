<?php

namespace App\Services;

use Illuminate\Support\Str;

class ChatTriggerwordRouter
{
    /**
     * Triggerword → services.langdock.* config key mapping.
     *
     * Keep this as the single source of truth so routing and docs/tools stay in sync.
     *
     * @var array<string, string>
     */
    private const TRIGGER_TO_CONFIG_KEY = [
        'mapping' => 'scoping_mapping_agent',
        'map' => 'scoping_mapping_agent',

        'search' => 'search_agent',

        'review' => 'review_agent',

        'retrieval' => 'retrieval_agent',
        'retrieve' => 'retrieval_agent',
        'papers' => 'retrieval_agent',
        'paper' => 'retrieval_agent',

        'mayring' => 'mayring_agent',

        'evaluation' => 'evaluation_agent',
        'bewertung' => 'evaluation_agent',

        'synthesis' => 'synthesis_agent',
        'report' => 'synthesis_agent',

        'pico' => 'pico_agent',

        'db' => 'agent_id',
        'dully' => 'agent_id',
    ];

    /**
     * @return array<string, array<int, string>> config_key => triggerwords
     */
    public static function configKeyToTriggers(): array
    {
        $inverted = [];

        foreach (self::TRIGGER_TO_CONFIG_KEY as $trigger => $configKey) {
            $inverted[$configKey] ??= [];
            $inverted[$configKey][] = $trigger;
        }

        foreach ($inverted as $configKey => $triggers) {
            $unique = array_values(array_unique($triggers));
            sort($unique);
            $inverted[$configKey] = $unique;
        }

        ksort($inverted);

        return $inverted;
    }

    /**
     * Parses a dashboard chat message for an optional triggerword command.
     *
     * Supported syntax (start of message):
     *   @<trigger> [<projekt_uuid>] <rest>
     *   #<trigger> [<projekt_uuid>] <rest>
     *
     * Examples:
     *   @mapping 9d3b...-.... My research question
     *   @review  9d3b...-.... Screen results
     *
     * @return array{config_key: string, cleaned_message: string, projekt_id: string|null, triggerword: string|null, structured_output: bool}
     */
    public function route(string $message): array
    {
        $original = $message;
        $trimmed = ltrim($message);

        if ($trimmed === '' || (!str_starts_with($trimmed, '@') && !str_starts_with($trimmed, '#'))) {
            return $this->fallback($original);
        }

        if (!preg_match('/^[@#](?<trigger>[A-Za-z0-9_-]+)(?:\s+(?<maybe_uuid>[0-9a-fA-F-]{36}))?\s*(?<rest>.*)$/s', $trimmed, $m)) {
            return $this->fallback($original);
        }

        $trigger = strtolower((string) ($m['trigger'] ?? ''));
        $maybeUuid = (string) ($m['maybe_uuid'] ?? '');
        $rest = (string) ($m['rest'] ?? '');

        $projektId = null;
        if ($maybeUuid !== '' && Str::isUuid($maybeUuid)) {
            $projektId = $maybeUuid;
        } else {
            // If the second token isn't a UUID, treat it as part of the actual message.
            $rest = trim(($maybeUuid !== '' ? ($maybeUuid . ' ') : '') . $rest);
        }

        $configKey = $this->mapTriggerToConfigKey($trigger);
        if ($configKey === null) {
            return $this->fallback($original);
        }

        $cleaned = trim($rest);
        if ($cleaned === '') {
            $cleaned = $original;
        }

        return [
            'config_key' => $configKey,
            'cleaned_message' => $cleaned,
            'projekt_id' => $projektId,
            'triggerword' => $trigger,
            'structured_output' => true,
        ];
    }

    private function fallback(string $message): array
    {
        return [
            'config_key' => 'agent_id',
            'cleaned_message' => $message,
            'projekt_id' => null,
            'triggerword' => null,
            'structured_output' => false,
        ];
    }

    private function mapTriggerToConfigKey(string $trigger): ?string
    {
        return self::TRIGGER_TO_CONFIG_KEY[$trigger] ?? null;
    }
}
