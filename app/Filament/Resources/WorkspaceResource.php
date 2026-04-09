<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkspaceResource\Pages;
use App\Filament\Resources\WorkspaceResource\RelationManagers;
use App\Models\Workspace;
use App\Services\CreditService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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
            TextInput::make('name')
                ->required()
                ->maxLength(160),
            Select::make('owner_id')
                ->label('Inhaber')
                ->relationship('owner', 'name')
                ->searchable()
                ->preload()
                ->required(),
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
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', '.').' €')
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
                        ToggleButtons::make('preset')
                            ->label('Schnellauswahl')
                            ->inline()
                            ->options([
                                '0.50' => '0,50 €',
                                '1.00' => '1,00 €',
                                '5.00' => '5,00 €',
                                '10.00' => '10,00 €',
                                '50.00' => '50,00 €',
                            ])
                            ->default('5.00')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('amount_eur', $state)),
                        TextInput::make('amount_eur')
                            ->label('Oder individueller Betrag (EUR)')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(500.00)
                            ->step(0.01)
                            ->required()
                            ->default('5.00')
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
                            ->body(number_format($data['amount_eur'], 2, ',', '.').' € wurden dem Workspace "'.$record->name.'" gutgeschrieben.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading('Workspace löschen?')
                    ->modalDescription(fn (Workspace $record): string => static::deleteModalDescription($record))
                    ->requiresConfirmation(),
            ]);
    }

    public static function deleteModalDescription(Workspace $record): string
    {
        $lines = ["Der Workspace \"{$record->name}\" wird unwiderruflich gelöscht."];

        if (($count = $record->projekte()->count()) > 0) {
            $lines[] = "Achtung: {$count} Projekt(e) werden ebenfalls gelöscht.";
        }

        if ($record->credits_balance_cents > 0) {
            $lines[] = 'Achtung: Guthaben von '.number_format($record->credits_balance_cents / 100, 2, ',', '.').' € geht verloren.';
        }

        return implode(' ', $lines);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\CreditTransactionsRelationManager::class,
            RelationManagers\WorkspaceUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkspaces::route('/'),
            'create' => Pages\CreateWorkspace::route('/create'),
            'edit' => Pages\EditWorkspace::route('/{record}/edit'),
            'view' => Pages\ViewWorkspace::route('/{record}'),
        ];
    }
}
