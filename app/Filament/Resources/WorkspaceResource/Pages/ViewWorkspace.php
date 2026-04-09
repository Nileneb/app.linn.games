<?php

namespace App\Filament\Resources\WorkspaceResource\Pages;

use App\Filament\Resources\WorkspaceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkspace extends ViewRecord
{
    protected static string $resource = WorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make()
                ->modalDescription(fn (): string => WorkspaceResource::deleteModalDescription($this->record))
                ->requiresConfirmation(),
        ];
    }
}
