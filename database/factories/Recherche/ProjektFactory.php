<?php

namespace Database\Factories\Recherche;

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
                return User::findOrFail($attributes['user_id'])->ensureDefaultWorkspace()->id;
            },
            'titel' => fake()->sentence(),
            'forschungsfrage' => fake()->paragraph(),
        ];
    }
}
