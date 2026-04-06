<?php

namespace App\Filament\Widgets;

use App\Models\CreditTransaction;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreditOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalBalance = Workspace::sum('credits_balance_cents');
        $usageLast30d = CreditTransaction::where('type', 'usage')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('amount_cents');

        // Aktive Nutzer (letzte 7 Tage)
        $activeUsers = User::where('last_login_at', '>=', now()->subDays(7))->count();

        // Laufende Recherchen (nicht abgeschlossen)
        $runningResearch = Projekt::where('status', '!=', 'completed')->count();

        return [
            Stat::make('Gesamtguthaben', number_format($totalBalance / 100, 2, ',', '.') . ' €')
                ->description('Verfügbares Guthaben')
                ->color($totalBalance <= 0 ? 'danger' : 'success')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Verbrauch (30 Tage)', number_format(abs($usageLast30d) / 100, 2, ',', '.') . ' €')
                ->description('Gesamter Kostenaufwand')
                ->color('warning')
                ->icon('heroicon-o-arrow-trending-up'),
            Stat::make('Aktive Nutzer', $activeUsers)
                ->description('Letzte 7 Tage aktiv')
                ->color('info')
                ->icon('heroicon-o-users'),
            Stat::make('Laufende Recherchen', $runningResearch)
                ->description('Nicht abgeschlossene Projekte')
                ->color('primary')
                ->icon('heroicon-o-document-magnifying-glass'),
        ];
    }
}
