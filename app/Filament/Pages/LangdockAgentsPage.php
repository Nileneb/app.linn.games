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

    /** @var array<string, string>  Key => UUID für lokal konfigurierte Agenten die in der API nicht gefunden wurden */
    public array $orphaned = [];

    /** @var array<string, string>  agentId => configKey (umgekehrte Lookup-Map) */
    public array $configKeyMap = [];

    public ?string $error = null;

    public function mount(): void
    {
        /** @var LangdockAgentService $service */
        $service = app(LangdockAgentService::class);

        $this->configuredAgents = $service->configuredAgents();

        try {
            $this->agents = $service->listAgents();
        } catch (\Throwable $e) {
            $this->error = $e instanceof LangdockAgentException
                ? $e->getMessage()
                : 'Unerwarteter Fehler: ' . $e->getMessage();
        }

        $apiIds = array_column($this->agents, 'id');

        $this->orphaned = array_filter(
            $this->configuredAgents,
            fn (string $uuid) => ! in_array($uuid, $apiIds, true),
        );

        $this->configKeyMap = array_flip($this->configuredAgents);
    }

}
