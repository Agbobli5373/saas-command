<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => sprintf('%s Workspace', fake()->company()),
            'owner_id' => User::factory(),
            'is_personal' => false,
        ];
    }

    /**
     * Configure model factory callbacks.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            $workspace->members()->syncWithoutDetaching([
                $workspace->owner_id => ['role' => WorkspaceRole::Owner->value],
            ]);
        });
    }
}
