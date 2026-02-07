<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceUsageEvent>
 */
class WorkspaceUsageEventFactory extends Factory
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
            'quantity' => fake()->numberBetween(1, 10),
            'occurred_at' => now(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'context' => [
                'source' => fake()->randomElement(['invitation', 'api']),
                'reference' => fake()->uuid(),
            ],
        ];
    }
}
