<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingAuditEvent>
 */
class BillingAuditEventFactory extends Factory
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
            'actor_id' => User::factory(),
            'event_type' => fake()->randomElement([
                'checkout_started',
                'subscription_swapped',
                'subscription_cancelled',
                'stripe_invoice_payment_failed',
            ]),
            'source' => fake()->randomElement(['billing_action', 'stripe_webhook']),
            'severity' => fake()->randomElement(['info', 'warning', 'error']),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(6),
            'context' => [
                'reference' => fake()->uuid(),
            ],
            'occurred_at' => now(),
        ];
    }
}
