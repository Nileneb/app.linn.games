<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PendingRegistrationResource\Pages\ListPendingRegistrations;
use App\Models\PendingRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class PendingRegistrationResource extends Resource
{
    protected static ?string $model = PendingRegistration::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Pending Registrierungen';

    protected static \UnitEnum|string|null $navigationGroup = 'Sicherheit';

    protected static ?int $navigationSort = 11;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('pending_registrations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('confidence_score')
                    ->label('Score')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 80 => 'danger',
                        $state >= 40 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\IconColumn::make('needs_review')
                    ->label('Review nötig')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_email' => 'warning',
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('registration_ip')
                    ->label('IP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration_country_name')
                    ->label('Land'),
                Tables\Columns\TextColumn::make('token_expires_at')
                    ->label('Link läuft ab')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('needs_review')
                    ->label('Review nötig'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_email' => 'Pending',
                        'verified' => 'Verifiziert',
                        'rejected' => 'Abgelehnt',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendingRegistrations::route('/'),
        ];
    }
}
