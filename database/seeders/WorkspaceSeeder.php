<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->first();

        if ($owner === null) {
            return;
        }

        $workspace = Workspace::query()->firstOrCreate(
            [
                'owner_id' => $owner->id,
                'name' => 'Acme Team Workspace',
            ],
            [
                'is_personal' => false,
            ],
        );

        $workspace->members()->syncWithoutDetaching([
            $owner->id => ['role' => WorkspaceRole::Owner->value],
        ]);
    }
}
