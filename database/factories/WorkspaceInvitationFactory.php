<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    protected $model = WorkspaceInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'invited_by_user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement([
                WorkspaceRole::Admin->value,
                WorkspaceRole::Member->value,
            ]),
            'token' => Str::lower((string) Str::uuid()),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }
}
