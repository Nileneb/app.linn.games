<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Nutzer';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')->email()->required(),
            Forms\Components\Select::make('roles')
                ->label('Rolle')
                ->relationship('roles', 'name')
                ->multiple()
                ->minItems(1)
                ->maxItems(1)
                ->preload(),
            Forms\Components\Select::make('status')
                ->required()
                ->options([
                    'trial'     => 'Trial',
                    'active'    => 'Aktiv',
                    'suspended' => 'Gesperrt',
                    'cancelled' => 'Gekündigt',
                ])
                ->default('trial'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trial' => 'warning',
                        'active' => 'success',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rolle')
                    ->badge()
                    ->separator(',')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('email_verified_at')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'trial'     => 'Trial',
                        'active'    => 'Aktiv',
                        'suspended' => 'Gesperrt',
                        'cancelled' => 'Gekündigt',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\UserResource\Pages\ListUsers::route('/'),
            'create' => \App\Filament\Resources\UserResource\Pages\CreateUser::route('/create'),
            'edit'   => \App\Filament\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
