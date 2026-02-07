<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * Show the current workspace page.
     */
    public function show(Request $request): Response
    {
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
            ])
            ->values()
            ->all();

        $pendingInvitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
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
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'canInviteMembers' => $user->can('inviteMembers', $workspace),
        ]);
    }
}
