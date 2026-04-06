<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;

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
                ->getOptionLabelFromRecordUsing(fn ($record) => match ($record->name) {
                    'admin'    => 'Admin – Voller Zugriff',
                    'editor'   => 'Editor – Projekte erstellen & bearbeiten',
                    'mitglied' => 'Mitglied – Zugewiesene Projekte sehen & bearbeiten',
                    default    => $record->name,
                })
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
                \Filament\Actions\Action::make('resend_invitation')
                    ->label('Einladung erneut senden')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Einladung erneut senden')
                    ->modalDescription(fn (User $record) => 'Einen neuen Einladungslink an ' . $record->email . ' senden?')
                    ->modalSubmitActionLabel('Senden')
                    ->action(function (User $record) {
                        $status = Password::broker()->sendResetLink(['email' => $record->email]);

                        if ($status === Password::RESET_LINK_SENT) {
                            Notification::make()
                                ->title('Einladung gesendet')
                                ->body('Ein neuer Einladungslink wurde an ' . $record->email . ' verschickt.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Fehler')
                                ->body('Die E-Mail konnte nicht gesendet werden.')
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\DeleteAction::make()
                    ->hidden(fn (User $record) => $record->id === auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => 'Nutzer "' . $record->name . '" und alle zugehörigen Daten unwiderruflich löschen?'),
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
