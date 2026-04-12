<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

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
                    'admin' => 'Admin – Voller Zugriff',
                    'editor' => 'Editor – Projekte erstellen & bearbeiten',
                    'mitglied' => 'Mitglied – Zugewiesene Projekte sehen & bearbeiten',
                    default => $record->name,
                })
                ->multiple()
                ->minItems(1)
                ->maxItems(1)
                ->preload(),
            Forms\Components\Select::make('status')
                ->required()
                ->options([
                    'invited' => 'Eingeladen',
                    'waitlisted' => 'Warteliste',
                    'trial' => 'Trial',
                    'active' => 'Aktiv',
                    'suspended' => 'Gesperrt',
                    'cancelled' => 'Gekündigt',
                ])
                ->default('invited'),
            Forms\Components\Textarea::make('forschungsfrage')
                ->label('Forschungsfrage')
                ->rows(3)
                ->nullable(),
            Forms\Components\Select::make('forschungsbereich')
                ->label('Forschungsbereich')
                ->options([
                    'Gesundheit & Medizin' => 'Gesundheit & Medizin',
                    'Psychologie & Sozialwissenschaften' => 'Psychologie & Sozialwissenschaften',
                    'Bildung & Pädagogik' => 'Bildung & Pädagogik',
                    'Informatik & Technologie' => 'Informatik & Technologie',
                    'Wirtschaft & Management' => 'Wirtschaft & Management',
                    'Umwelt & Nachhaltigkeit' => 'Umwelt & Nachhaltigkeit',
                    'Sonstiges' => 'Sonstiges',
                ])
                ->nullable(),
            Forms\Components\Select::make('erfahrung')
                ->label('Erfahrung mit Literaturrecherchen')
                ->options([
                    'Nein, das wäre mein erstes Mal' => 'Nein, das wäre mein erstes Mal',
                    'Ja, 1–2 Mal' => 'Ja, 1–2 Mal',
                    'Ja, regelmäßig' => 'Ja, regelmäßig',
                ])
                ->nullable(),
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
                        'invited' => 'secondary',
                        'waitlisted' => 'info',
                        'trial' => 'warning',
                        'active' => 'success',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('forschungsbereich')
                    ->label('Bereich')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('erfahrung')
                    ->label('Erfahrung')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rolle')
                    ->badge()
                    ->separator(',')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('registration_ip')
                    ->label('IP')
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('registration_country_code')
                    ->label('Herkunft')
                    ->formatStateUsing(fn (?string $state, User $record): string => $state
                        ? "{$state} — {$record->registration_country_name}"
                        : '–'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email_verified_at')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'invited' => 'Eingeladen',
                        'waitlisted' => 'Warteliste',
                        'trial' => 'Trial',
                        'active' => 'Aktiv',
                        'suspended' => 'Gesperrt',
                        'cancelled' => 'Gekündigt',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('freischalten')
                    ->label('Freischalten')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => $record->status === 'waitlisted')
                    ->requiresConfirmation()
                    ->modalHeading('Nutzer freischalten')
                    ->modalDescription('Nutzer freischalten und in Trial überführen?')
                    ->modalSubmitActionLabel('Freischalten')
                    ->action(function (User $record) {
                        $record->update(['status' => 'trial']);

                        Notification::make()
                            ->title('Nutzer freigeschaltet')
                            ->body($record->name.' wurde erfolgreich freigeschaltet.')
                            ->success()
                            ->send();
                    }),
                Action::make('resend_invitation')
                    ->label('Einladung erneut senden')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Einladung erneut senden')
                    ->modalDescription(fn (User $record) => 'Einen neuen Einladungslink an '.$record->email.' senden?')
                    ->modalSubmitActionLabel('Senden')
                    ->action(function (User $record) {
                        $token = Str::random(64);
                        $record->update([
                            'invitation_token' => $token,
                            'invitation_expires_at' => now()->addDays(28),
                            'status' => 'invited',
                        ]);
                        $inviteUrl = route('invitation.accept', ['token' => $token]);
                        $record->notify(new InvitationNotification($inviteUrl));

                        Notification::make()
                            ->title('Einladung gesendet')
                            ->body('Ein neuer Einladungslink wurde an '.$record->email.' verschickt.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->hidden(fn (User $record) => $record->id === auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => 'Nutzer "'.$record->name.'" und alle zugehörigen Daten unwiderruflich löschen?'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
