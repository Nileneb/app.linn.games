<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Str::random(32);

        return $data;
    }

    protected function afterCreate(): void
    {
        $status = Password::broker()->sendResetLink(['email' => $this->record->email]);

        if ($status === Password::RESET_LINK_SENT) {
            Notification::make()
                ->title('Einladung verschickt')
                ->body('Eine E-Mail mit dem Einladungslink wurde an '.$this->record->email.' gesendet.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Nutzer angelegt – E-Mail fehlgeschlagen')
                ->body('Der Nutzer wurde erstellt, aber die Einladungs-E-Mail konnte nicht gesendet werden.')
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
