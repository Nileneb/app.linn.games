<?php

namespace App\Filament\Widgets;

use App\Models\CreditTransaction;
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

        return [
            Stat::make('Gesamtguthaben', number_format($totalBalance / 100, 2, ',', '.') . ' €')
                ->color($totalBalance <= 0 ? 'danger' : 'success')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Verbrauch (30 Tage)', number_format(abs($usageLast30d) / 100, 2, ',', '.') . ' €')
                ->color('warning')
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }
}
