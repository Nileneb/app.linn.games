<?php

namespace App\Filament\Widgets;

use App\Models\PhaseAgentResult;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AgentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $runsToday = PhaseAgentResult::where('created_at', '>=', $today)->count();
        $pending = PhaseAgentResult::where('status', 'pending')->count();
        $failedToday = PhaseAgentResult::where('status', 'failed')
            ->where('created_at', '>=', $today)
            ->count();

        return [
            Stat::make('Agent-Runs heute', $runsToday)
                ->icon('heroicon-o-cpu-chip')
                ->color('info'),
            Stat::make('Laufende Jobs', $pending)
                ->icon('heroicon-o-arrow-path')
                ->color($pending > 0 ? 'warning' : 'success'),
            Stat::make('Fehler heute', $failedToday)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($failedToday > 0 ? 'danger' : 'success'),
        ];
    }
}
