<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkspaceInvitationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspace = Workspace::query()->first();

        if ($workspace === null) {
            return;
        }

        WorkspaceInvitation::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'email' => 'invitee@example.com',
                'accepted_at' => null,
            ],
            [
                'invited_by_user_id' => $workspace->owner_id,
                'role' => WorkspaceRole::Member->value,
                'token' => Str::lower((string) Str::uuid()),
                'expires_at' => now()->addDays(7),
            ],
        );
    }
}
