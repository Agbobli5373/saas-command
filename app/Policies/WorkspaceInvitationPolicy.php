<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Str;

class WorkspaceInvitationPolicy
{
    /**
     * Determine whether the user can accept an invitation.
     */
    public function accept(User $user, WorkspaceInvitation $workspaceInvitation): bool
    {
        return Str::lower($user->email) === Str::lower($workspaceInvitation->email);
    }
}
