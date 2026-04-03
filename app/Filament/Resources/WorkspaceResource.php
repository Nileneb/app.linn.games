<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkspaceResource\Pages;
use App\Filament\Resources\WorkspaceResource\RelationManagers;
use App\Models\Workspace;
use App\Services\CreditService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Workspaces';
    protected static ?string $modelLabel = 'Workspace';
    protected static ?string $pluralModelLabel = 'Workspaces';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(160),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('owner.name')->label('Inhaber')->searchable(),
                TextColumn::make('credits_balance_cents')
                    ->label('Guthaben')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', '.') . ' €')
                    ->sortable()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : ($state < 500 ? 'warning' : 'success')),
                TextColumn::make('credit_transactions_count')
                    ->label('Transaktionen')
                    ->counts('creditTransactions'),
                TextColumn::make('created_at')->label('Erstellt')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('topup')
                    ->label('Aufladen')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        TextInput::make('amount_eur')
                            ->label('Betrag (EUR)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->suffix('€'),
                        Textarea::make('description')
                            ->label('Notiz')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Workspace $record, array $data): void {
                        $cents = (int) round((float) $data['amount_eur'] * 100);
                        app(CreditService::class)->topUp($record, $cents, $data['description'] ?? '');

                        Notification::make()
                            ->title('Guthaben aufgeladen')
                            ->body(number_format($data['amount_eur'], 2, ',', '.') . ' € wurden dem Workspace "' . $record->name . '" gutgeschrieben.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\CreditTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkspaces::route('/'),
            'view'  => Pages\ViewWorkspace::route('/{record}'),
        ];
    }
}
