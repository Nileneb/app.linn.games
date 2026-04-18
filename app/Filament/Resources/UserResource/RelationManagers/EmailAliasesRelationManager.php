<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailAliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'emailAliases';

    protected static ?string $title = 'E-Mail-Aliases';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->label('E-Mail-Adresse')
                ->email()
                ->required()
                ->unique('user_email_aliases', 'email', ignoreRecord: true),
            DateTimePicker::make('verified_at')
                ->label('Verifiziert am')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable(),
                IconColumn::make('verified_at')
                    ->label('Verifiziert')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->verified_at !== null)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('verified_at')
                    ->label('Verifiziert am')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->label('Alias hinzufügen'),
            ])
            ->actions([
                DeleteAction::make()->label('Entfernen'),
            ]);
    }
}
