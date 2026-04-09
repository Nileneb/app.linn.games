<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;

class LangdockAgentsPage extends Page
{
    protected string $view = 'filament.pages.langdock-agents';

    protected static ?string $navigationLabel = 'Claude Agenten';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Claude Agenten';

    /** @var array<string, string> Konfigurierte Agent-Keys aus services.anthropic.agents */
    public array $configuredAgents = [];

    public ?string $error = null;

    public function mount(): void
    {
        $agents = config('services.anthropic.agents', []);

        if (! is_array($agents)) {
            $this->error = 'services.anthropic.agents ist nicht als Array konfiguriert.';

            return;
        }

        $this->configuredAgents = array_map(
            fn ($value) => is_string($value) ? $value : (string) $value,
            $agents,
        );
    }
}
