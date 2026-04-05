<?php

namespace App\Filament\Resources\WorkspaceResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkspaceUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Mitglieder';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->label('Rolle')
                ->options([
                    'owner'  => 'Inhaber',
                    'editor' => 'Editor',
                    'viewer' => 'Betrachter',
                ])
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('E-Mail')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rolle')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner'  => 'warning',
                        'editor' => 'primary',
                        'viewer' => 'gray',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner'  => 'Inhaber',
                        'editor' => 'Editor',
                        'viewer' => 'Betrachter',
                        default  => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('Hinzugefügt')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->label('Mitglied hinzufügen')
                    ->form([
                        Select::make('user_id')
                            ->label('Nutzer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('role')
                            ->label('Rolle')
                            ->options([
                                'owner'  => 'Inhaber',
                                'editor' => 'Editor',
                                'viewer' => 'Betrachter',
                            ])
                            ->default('editor')
                            ->required(),
                    ]),
            ])
            ->actions([
                EditAction::make()->label('Rolle ändern'),
                DeleteAction::make()->label('Entfernen'),
            ]);
    }
}
