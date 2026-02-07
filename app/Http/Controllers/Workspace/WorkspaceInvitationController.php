<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceInvitationRequest;
use App\Models\WorkspaceInvitation;
use App\Notifications\Workspace\WorkspaceInvitationNotification;
use App\Notifications\Workspace\WorkspaceMemberJoinedNotification;
use App\Services\Billing\PlanService;
use App\Services\Billing\WorkspaceEntitlementService;
use App\Services\Usage\UsageMeteringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class WorkspaceInvitationController extends Controller
{
    /**
     * Store a workspace invitation and send the invite email.
     */
    public function store(
        StoreWorkspaceInvitationRequest $request,
        WorkspaceEntitlementService $entitlements,
        UsageMeteringService $usage
    ): RedirectResponse {
        $user = $request->user();
        $workspace = $user->activeWorkspace();

        if ($workspace === null) {
            return back()->with('status', 'No active workspace selected.');
        }

        $inviteMembers = $entitlements->inviteMembers(
            user: $user,
            workspace: $workspace,
            pendingInvitations: $workspace->pendingInvitationCount(),
        );

        if (! $inviteMembers['allowed']) {
            if (in_array($inviteMembers['reasonCode'], ['insufficient_role', 'feature_unavailable'], true)) {
                abort(403);
            }

            return back()->with('status', $inviteMembers['message'] ?? 'Invitations are currently unavailable.');
        }

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
            ->pending()
            ->update(['expires_at' => now()]);

        $invitation = $workspace->invitations()->create([
            'invited_by_user_id' => $user->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::lower((string) Str::uuid()),
            'expires_at' => now()->addDays(7),
        ]);

        try {
            $usage->trackIfConfigured($workspace, 'team_invitations_sent', context: [
                'invitation_id' => $invitation->id,
                'invited_by_user_id' => $user->id,
                'invitee_email' => $email,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

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
    public function accept(Request $request, string $token, PlanService $plans): RedirectResponse
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

        if ($plans->hasReachedSeatLimit($workspace)) {
            return to_route('dashboard')->with('status', 'Workspace seat limit reached. Ask the owner to upgrade first.');
        }

        $workspace->addMember($request->user(), $invitation->roleEnum());

        if ($workspace->owner !== null && $workspace->owner->isNot($request->user())) {
            $workspace->owner->notify(new WorkspaceMemberJoinedNotification(
                workspaceName: $workspace->name,
                memberName: $request->user()->name,
            ));
        }

        $invitation->forceFill([
            'accepted_at' => now(),
        ])->save();

        $request->user()->switchWorkspace($workspace);

        return to_route('workspace')->with('status', sprintf('You joined %s.', $workspace->name));
    }
}
