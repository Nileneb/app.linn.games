<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistrationAttemptResource\Pages\ListRegistrationAttempts;
use App\Models\RegistrationAttempt;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegistrationAttemptResource extends Resource
{
    protected static ?string $model = RegistrationAttempt::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Angriffe';

    protected static \UnitEnum|string|null $navigationGroup = 'Sicherheit';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Grund')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'honeypot' => 'danger',
                        'rate_limit' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'honeypot' => 'Honeypot',
                        'rate_limit' => 'Rate-Limit',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP-Adresse')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Land')
                    ->formatStateUsing(fn (?string $state, RegistrationAttempt $record): string => $state
                        ? "{$state} — {$record->country_name}"
                        : '–'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Stadt')
                    ->default('–'),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->default('–')
                    ->limit(40),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User-Agent')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('reason')
                    ->label('Grund')
                    ->options([
                        'honeypot' => 'Honeypot',
                        'rate_limit' => 'Rate-Limit',
                    ]),
                Tables\Filters\SelectFilter::make('country_code')
                    ->label('Land')
                    ->options(fn () => RegistrationAttempt::query()
                        ->whereNotNull('country_code')
                        ->distinct()
                        ->pluck('country_name', 'country_code')
                        ->toArray()
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('report')
                    ->label('Strafanzeige-Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function (RegistrationAttempt $record) {
                        $filename = 'strafanzeige_'.str_replace('.', '_', $record->ip).'_'.now()->format('Ymd').'.txt';
                        $content = self::buildCrimeReportText($record);

                        return response()->streamDownload(
                            fn () => print ($content),
                            $filename,
                            ['Content-Type' => 'text/plain; charset=utf-8']
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_report')
                    ->label('Sammel-Export für Behörden')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function ($records) {
                        $content = "SAMMEL-STRAFANZEIGE-EXPORT\n";
                        $content .= 'Erstellt am: '.now()->format('d.m.Y H:i:s')."\n";
                        $content .= 'Anzahl Vorfälle: '.$records->count()."\n";
                        $content .= str_repeat('=', 60)."\n\n";

                        foreach ($records as $record) {
                            $content .= self::buildCrimeReportText($record);
                            $content .= "\n".str_repeat('-', 60)."\n\n";
                        }

                        $filename = 'strafanzeige_sammel_'.now()->format('Ymd_His').'.txt';

                        return response()->streamDownload(
                            fn () => print ($content),
                            $filename,
                            ['Content-Type' => 'text/plain; charset=utf-8']
                        );
                    }),
                Tables\Actions\DeleteBulkAction::make()->label('Löschen'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrationAttempts::route('/'),
        ];
    }

    private static function buildCrimeReportText(RegistrationAttempt $record): string
    {
        $lines = [];
        $lines[] = 'VORFALLSPROTOKOLL — Unerlaubter Zugriffsversuch';
        $lines[] = str_repeat('=', 60);
        $lines[] = '';
        $lines[] = 'ANGABEN ZUM VORFALL';
        $lines[] = '  Zeitpunkt (UTC):    '.$record->created_at->format('d.m.Y H:i:s').' UTC';
        $lines[] = '  Art des Versuchs:   '.match ($record->reason) {
            'honeypot' => 'Automatisierter Bot-Angriff (Honeypot-Falle ausgelöst)',
            'rate_limit' => 'Brute-Force / Massenregistrierung (Rate-Limit überschritten)',
            default => $record->reason,
        };
        $lines[] = '';
        $lines[] = 'TECHNISCHE IDENTIFIKATION';
        $lines[] = '  IP-Adresse:         '.$record->ip;
        $lines[] = '  Land (IP-Zuordnung):'.' '.($record->country_name ?? 'unbekannt').' ('.($record->country_code ?? '–').')';
        $lines[] = '  Stadt:              '.($record->city ?? 'unbekannt');
        $lines[] = '  User-Agent:         '.($record->user_agent ?? 'nicht übermittelt');
        if ($record->email) {
            $lines[] = '  Verwendete E-Mail:  '.$record->email;
        }
        $lines[] = '';
        $lines[] = 'PLATTFORM';
        $lines[] = '  Betroffene Plattform: app.linn.games';
        $lines[] = '  Betroffener Dienst:   Nutzer-Registrierung';
        $lines[] = '';
        $lines[] = 'RECHTLICHER HINWEIS';
        $lines[] = '  Diese Daten wurden zum Zweck der Strafverfolgung automatisch';
        $lines[] = '  protokolliert (§ 202a StGB — Ausspähen von Daten,';
        $lines[] = '  § 303b StGB — Computersabotage, soweit anwendbar).';
        $lines[] = '  Die IP-Zuordnung basiert auf öffentlichen Geo-IP-Daten und';
        $lines[] = '  dient als Ermittlungshinweis. Eine gerichtsverwertbare';
        $lines[] = '  Zuordnung erfolgt durch den Netzbetreiber auf Behördenantrag.';

        return implode("\n", $lines);
    }
}
