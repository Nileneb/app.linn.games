<?php

namespace App\Filament\Pages;

use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Filament\Pages\Page;
use UnitEnum;

class LangdockAgentsPage extends Page
{
    protected string $view = 'filament.pages.langdock-agents';

    protected static ?string $navigationLabel = 'Langdock Agenten';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string | UnitEnum | null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Langdock Agenten';

    /** @var array<int, array<string, mixed>> */
    public array $agents = [];

    /** @var array<string, string> */
    public array $configuredAgents = [];

    public ?string $error = null;

    public function mount(): void
    {
        /** @var LangdockAgentService $service */
        $service = app(LangdockAgentService::class);

        $this->configuredAgents = $service->configuredAgents();

        try {
            $this->agents = $service->listAgents();
        } catch (LangdockAgentException $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Gibt zu einer Agent-UUID den lokalen config-Key zurück (oder null).
     */
    public function configKeyForId(string $agentId): ?string
    {
        foreach ($this->configuredAgents as $key => $uuid) {
            if ($uuid === $agentId) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Lokal konfigurierte Agent-UUIDs, die NICHT in der API-Antwort auftauchen.
     *
     * @return array<string, string>  Key => UUID
     */
    public function orphanedConfigKeys(): array
    {
        $apiIds = array_column($this->agents, 'id');

        return array_filter(
            $this->configuredAgents,
            fn (string $uuid) => ! in_array($uuid, $apiIds, true),
        );
    }
}
