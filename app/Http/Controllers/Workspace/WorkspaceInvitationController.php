<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceInvitationRequest;
use App\Models\WorkspaceInvitation;
use App\Notifications\Workspace\WorkspaceInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class WorkspaceInvitationController extends Controller
{
    /**
     * Store a workspace invitation and send the invite email.
     */
    public function store(StoreWorkspaceInvitationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->activeWorkspace();

        if ($workspace === null) {
            return back()->with('status', 'No active workspace selected.');
        }

        $this->authorize('inviteMembers', $workspace);

        $email = Str::lower(trim((string) $request->validated('email')));
        $role = (string) $request->validated('role');

        $alreadyMember = $workspace->members()
            ->whereRaw('LOWER(users.email) = ?', [$email])
            ->exists();

        if ($alreadyMember) {
            return back()->with('status', 'This user is already a member of your workspace.');
        }

        $workspace->invitations()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->update(['expires_at' => now()]);

        $invitation = $workspace->invitations()->create([
            'invited_by_user_id' => $user->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::lower((string) Str::uuid()),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)->notify(
            new WorkspaceInvitationNotification(
                workspaceName: $workspace->name,
                invitedByName: $user->name,
                acceptUrl: route('workspaces.invitations.accept', ['token' => $invitation->token]),
                expiresAt: $invitation->expires_at,
                role: $invitation->role,
            )
        );

        return back()->with('status', sprintf('Invitation sent to %s.', $email));
    }

    /**
     * Accept a workspace invitation for the authenticated user.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token', $token)
            ->first();

        if ($invitation === null) {
            return to_route('dashboard')->with('status', 'Invitation not found.');
        }

        if ($invitation->accepted_at !== null) {
            return to_route('dashboard')->with('status', 'Invitation already accepted.');
        }

        if ($invitation->isExpired()) {
            return to_route('dashboard')->with('status', 'Invitation has expired.');
        }

        if ($request->user()->cannot('accept', $invitation)) {
            return to_route('dashboard')->with('status', 'This invitation is for a different email address.');
        }

        $workspace = $invitation->workspace;
        $workspace->addMember($request->user(), $invitation->roleEnum());

        $invitation->forceFill([
            'accepted_at' => now(),
        ])->save();

        $request->user()->switchWorkspace($workspace);

        return to_route('workspace')->with('status', sprintf('You joined %s.', $workspace->name));
    }
}
