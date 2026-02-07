<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\PlanService;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can switch to the workspace.
     */
    public function switchTo(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can invite members.
     */
    public function inviteMembers(User $user, Workspace $workspace): bool
    {
        $role = $user->workspaceRole($workspace);

        if (! in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)) {
            return false;
        }

        return app(PlanService::class)->workspaceHasFeature($workspace, 'team_invitations');
    }

    /**
     * Determine whether the user can manage billing.
     */
    public function manageBilling(User $user, Workspace $workspace): bool
    {
        $role = $user->workspaceRole($workspace);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }
}
