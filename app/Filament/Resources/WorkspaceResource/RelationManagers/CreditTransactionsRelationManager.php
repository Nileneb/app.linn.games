<?php

namespace App\Filament\Resources\WorkspaceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreditTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'creditTransactions';

    protected static ?string $title = 'Transaktionen';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Datum')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'topup' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'topup' ? 'Aufladung' : 'Verbrauch'),
                TextColumn::make('amount_cents')
                    ->label('Betrag')
                    ->formatStateUsing(fn (int $state): string => ($state >= 0 ? '+' : '').number_format($state / 100, 2, ',', '.').' €')
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('tokens_used')->label('Tokens')->placeholder('—'),
                TextColumn::make('agent_config_key')->label('Agent')->placeholder('—'),
                TextColumn::make('description')->label('Notiz')->placeholder('—')->limit(50),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
