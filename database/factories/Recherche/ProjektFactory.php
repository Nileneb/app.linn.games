<?php

namespace Database\Factories\Recherche;

use App\Models\Workspace;
use App\Models\WorkspaceUser;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjektFactory extends Factory
{
    protected $model = Projekt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace_id' => function (array $attributes) {
                $user = User::find($attributes['user_id']);

                if (! $user) {
                    return null;
                }

                $workspaceId = $user->activeWorkspaceId();

                if ($workspaceId !== null) {
                    return $workspaceId;
                }

                $workspace = Workspace::create([
                    'owner_id' => $user->id,
                    'name' => trim($user->name ?: 'Workspace') . ' Workspace',
                ]);

                WorkspaceUser::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                ]);

                return $workspace->id;
            },
            'titel' => fake()->sentence(),
            'forschungsfrage' => fake()->paragraph(),
        ];
    }
}
