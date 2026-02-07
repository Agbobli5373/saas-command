<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\DestroyWorkspaceMemberRequest;
use App\Http\Requests\Workspace\TransferWorkspaceOwnershipRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceMemberRoleRequest;
use App\Models\User;
use App\Services\Billing\PlanService;
use App\Services\Billing\WorkspaceEntitlementService;
use App\Services\Usage\UsageMeteringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * Show the current workspace page.
     */
    public function show(
        Request $request,
        PlanService $plans,
        UsageMeteringService $usage,
        WorkspaceEntitlementService $entitlements
    ): Response {
        $user = $request->user();
        $workspace = $user->activeWorkspace();
        abort_if($workspace === null, 403);

        $this->authorize('view', $workspace);

        $members = $workspace->members()
            ->select(['users.id', 'users.name', 'users.email'])
            ->orderBy('users.name')
            ->get()
            ->map(fn ($member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => (string) ($member->pivot->role ?? 'member'),
                'isOwner' => $member->id === $workspace->owner_id,
            ])
            ->values()
            ->all();

        $seatCount = count($members);
        $billedSeatCount = max(1, (int) ($workspace->subscription('default')?->quantity ?? $seatCount));
        $pendingInvitationCount = $workspace->pendingInvitationCount();
        $currentPlan = $plans->resolveWorkspacePlan($workspace);
        $inviteMembers = $entitlements->inviteMembers($user, $workspace, $pendingInvitationCount);

        $pendingInvitations = $workspace->invitations()
            ->pending()
            ->latest('id')
            ->get()
            ->map(fn ($invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('workspace', [
            'status' => $request->session()->get('status'),
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'plan' => [
                'key' => $currentPlan['key'] ?? null,
                'title' => $currentPlan['title'] ?? null,
                'billingMode' => $currentPlan['billingMode'] ?? null,
            ],
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'canInviteMembers' => $inviteMembers['allowed'],
            'canManageInvitations' => $inviteMembers['hasInviteRole'],
            'inviteEntitlement' => [
                'reasonCode' => $inviteMembers['reasonCode'],
                'message' => $inviteMembers['message'],
                'usageQuota' => $inviteMembers['usageQuota'],
                'usageUsed' => $inviteMembers['usageUsed'],
                'usageRemaining' => $inviteMembers['usageRemaining'],
            ],
            'canManageMembers' => $user->can('manageMembers', $workspace),
            'canTransferOwnership' => $user->can('transferOwnership', $workspace),
            'currentUserId' => $user->id,
            'seatCount' => $seatCount,
            'seatLimit' => $inviteMembers['seatLimit'],
            'remainingSeatCapacity' => $inviteMembers['remainingSeatCapacity'],
            'hasReachedSeatLimit' => $inviteMembers['hasReachedSeatLimit'],
            'billedSeatCount' => $billedSeatCount,
            'usagePeriod' => $usage->currentPeriodMeta(),
            'usageMetrics' => $usage->currentPeriodUsage($workspace),
        ]);
    }

    /**
     * Update the role of an existing workspace member.
     */
    public function updateMemberRole(
        UpdateWorkspaceMemberRoleRequest $request,
        User $member
    ): RedirectResponse {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('workspace')->with('status', 'No active workspace selected.');
        }

        $this->authorize('manageMembers', $workspace);

        if (! $workspace->members()->where('users.id', $member->id)->exists()) {
            abort(404);
        }

        if ($member->id === $workspace->owner_id) {
            return back()->with('status', 'Transfer ownership instead of changing the owner role.');
        }

        $role = WorkspaceRole::from((string) $request->validated('role'));

        $workspace->updateMemberRole($member, $role);

        return back()->with('status', sprintf('%s role updated to %s.', $member->name, $role->value));
    }

    /**
     * Remove a member from the active workspace.
     */
    public function destroyMember(
        DestroyWorkspaceMemberRequest $request,
        User $member
    ): RedirectResponse {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('workspace')->with('status', 'No active workspace selected.');
        }

        $this->authorize('manageMembers', $workspace);

        if (! $workspace->members()->where('users.id', $member->id)->exists()) {
            abort(404);
        }

        if ($member->id === $workspace->owner_id) {
            return back()->with('status', 'Transfer ownership before removing the owner.');
        }

        if ($member->id === $request->user()->id) {
            return back()->with('status', 'You cannot remove your own membership from this screen.');
        }

        $workspace->removeMember($member);

        return back()->with('status', sprintf('%s removed from workspace.', $member->name));
    }

    /**
     * Transfer workspace ownership to another member.
     */
    public function transferOwnership(TransferWorkspaceOwnershipRequest $request): RedirectResponse
    {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('workspace')->with('status', 'No active workspace selected.');
        }

        $this->authorize('transferOwnership', $workspace);

        $newOwner = User::query()->findOrFail((int) $request->validated('owner_id'));

        if (! $workspace->members()->where('users.id', $newOwner->id)->exists()) {
            return back()->with('status', 'Select a current member to transfer ownership.');
        }

        if (! $workspace->transferOwnershipTo($newOwner)) {
            return back()->with('status', 'Ownership transfer could not be completed.');
        }

        return back()->with('status', sprintf('Workspace ownership transferred to %s.', $newOwner->name));
    }
}
