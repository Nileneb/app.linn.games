<?php

namespace App\Filament\Resources\WorkspaceResource\Pages;

use App\Filament\Resources\WorkspaceResource;
use App\Models\WorkspaceUser;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkspace extends CreateRecord
{
    protected static string $resource = WorkspaceResource::class;

    protected function afterCreate(): void
    {
        $workspace = $this->record;

        if (
            $workspace->owner_id
            && ! WorkspaceUser::where('workspace_id', $workspace->id)
                ->where('user_id', $workspace->owner_id)
                ->exists()
        ) {
            WorkspaceUser::create([
                'workspace_id' => $workspace->id,
                'user_id' => $workspace->owner_id,
                'role' => 'owner',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
