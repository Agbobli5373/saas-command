<?php

namespace Database\Factories;

use App\Models\WorkspaceWebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceWebhookDelivery>
 */
class WorkspaceWebhookDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_webhook_endpoint_id' => WorkspaceWebhookEndpoint::factory(),
            'event_type' => fake()->randomElement([
                'workspace.invitation.sent',
                'workspace.member.joined',
                'billing.subscription.updated',
            ]),
            'payload' => [
                'id' => fake()->uuid(),
                'workspace_id' => fake()->numberBetween(1, 1000),
            ],
            'status' => fake()->randomElement(['pending', 'delivered', 'failed']),
            'attempt_count' => fake()->numberBetween(0, 4),
            'response_status_code' => fake()->optional()->numberBetween(200, 503),
            'response_body' => fake()->optional()->sentence(),
            'last_error_message' => fake()->optional()->sentence(),
            'dispatched_at' => fake()->optional()->dateTimeThisMonth(),
            'last_attempted_at' => fake()->optional()->dateTimeThisMonth(),
        ];
    }
}
