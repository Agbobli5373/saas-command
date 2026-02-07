<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceWebhookEndpoint>
 */
class WorkspaceWebhookEndpointFactory extends Factory
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
            'name' => fake()->words(2, true),
            'url' => fake()->url(),
            'signing_secret' => 'whsec_'.fake()->regexify('[A-Za-z0-9]{40}'),
            'events' => ['workspace.invitation.sent', 'workspace.member.joined'],
            'is_active' => true,
            'failure_count' => 0,
        ];
    }
}
