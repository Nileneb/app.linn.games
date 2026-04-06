<?php

namespace App\Filament\Widgets;

use App\Models\Recherche\Projekt;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('Aktive Nutzer', User::where('status', 'active')->count())
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Recherche-Projekte', Projekt::count())
                ->icon('heroicon-o-magnifying-glass')
                ->color('success'),
            Stat::make('Trial-Accounts', User::where('status', 'trial')->count())
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }
}
