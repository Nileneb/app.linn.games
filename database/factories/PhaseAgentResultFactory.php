<?php

namespace Database\Factories;

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhaseAgentResultFactory extends Factory
{
    protected $model = PhaseAgentResult::class;

    public function definition(): array
    {
        return [
            'projekt_id' => Projekt::factory(),
            'user_id' => User::factory(),
            'phase_nr' => fake()->numberBetween(1, 8),
            'phase' => fake()->word(),
            'agent_config_key' => fake()->randomElement(['scoping_mapping_agent', 'search_agent', 'review_agent']),
            'status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'content' => fake()->paragraph(),
            'error_message' => null,
            'result_data' => [],
        ];
    }
}
