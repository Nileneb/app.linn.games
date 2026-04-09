<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Notifications\InvitationNotification;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Temporäres Passwort – wird beim Einladen überschrieben
        $data['password'] = Str::random(32);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record->status === 'invited') {
            $token = Str::random(64);
            $record->update([
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addDays(28),
            ]);
            $inviteUrl = route('invitation.accept', ['token' => $token]);
            $record->notify(new InvitationNotification($inviteUrl));

            Notification::make()
                ->title('Einladung verschickt')
                ->body('Eine E-Mail mit dem Einladungslink wurde an '.$record->email.' gesendet.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Nutzer angelegt')
                ->body('Der Nutzer wurde erfolgreich erstellt.')
                ->success()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
