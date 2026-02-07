<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceUsageCounter>
 */
class WorkspaceUsageCounterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'metric_key' => fake()->randomElement([
                'team_invitations_sent',
                'api_requests_sent',
            ]),
            'period_start' => now()->startOfMonth()->toDateString(),
            'used' => fake()->numberBetween(0, 200),
            'quota' => fake()->optional()->numberBetween(10, 500),
        ];
    }
}
