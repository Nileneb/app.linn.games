<?php

namespace Database\Factories\Recherche;

use App\Models\Recherche\Phase;
use App\Models\Recherche\Projekt;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhaseFactory extends Factory
{
    protected $model = Phase::class;

    public function definition(): array
    {
        return [
            'projekt_id' => Projekt::factory(),
            'phase_nr' => fake()->numberBetween(1, 8),
            'titel' => fake()->sentence(),
            'status' => fake()->randomElement(['offen', 'in_bearbeitung', 'abgeschlossen']),
            'notizen' => fake()->paragraph(),
        ];
    }
}
