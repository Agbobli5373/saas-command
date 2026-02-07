<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Notifications\Workspace\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('workspace owners can invite teammates by email', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->post(route('workspaces.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::Admin->value,
        ]);

    $response->assertRedirect(route('workspace'));

    $this->assertDatabaseHas('workspace_invitations', [
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Admin->value,
        'accepted_at' => null,
    ]);

    Notification::assertSentOnDemand(
        WorkspaceInvitationNotification::class,
        function (WorkspaceInvitationNotification $notification, array $channels, object $notifiable): bool {
            return in_array('mail', $channels, true)
                && ($notifiable->routes['mail'] ?? null) === 'invitee@example.com'
                && str_contains($notification->acceptUrl, '/workspaces/invitations/');
        },
    );
});

test('workspace members cannot invite teammates', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);
    $member->switchWorkspace($workspace);

    $response = $this
        ->actingAs($member)
        ->post(route('workspaces.invitations.store'), [
            'email' => 'blocked@example.com',
            'role' => WorkspaceRole::Member->value,
        ]);

    $response->assertForbidden();
});

test('invited user can accept invitation with matching email', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $invitedUser = User::factory()->create([
        'email' => 'joiner@example.com',
    ]);

    $invitation = $workspace->invitations()->create([
        'invited_by_user_id' => $owner->id,
        'email' => 'joiner@example.com',
        'role' => WorkspaceRole::Member->value,
        'token' => Str::lower((string) Str::uuid()),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('workspaces.invitations.accept', ['token' => $invitation->token]));

    $response->assertRedirect(route('workspace'));

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $invitedUser->id,
        'role' => WorkspaceRole::Member->value,
    ]);

    $this->assertDatabaseHas('workspace_invitations', [
        'id' => $invitation->id,
    ]);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    expect($invitedUser->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('invitation cannot be accepted by a different email address', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $wrongUser = User::factory()->create([
        'email' => 'wrong@example.com',
    ]);

    $invitation = $workspace->invitations()->create([
        'invited_by_user_id' => $owner->id,
        'email' => 'intended@example.com',
        'role' => WorkspaceRole::Member->value,
        'token' => Str::lower((string) Str::uuid()),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this
        ->actingAs($wrongUser)
        ->get(route('workspaces.invitations.accept', ['token' => $invitation->token]));

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('workspace_invitations', [
        'id' => $invitation->id,
        'accepted_at' => null,
    ]);
});

test('expired invitation cannot be accepted', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $invitedUser = User::factory()->create([
        'email' => 'expired@example.com',
    ]);

    $invitation = $workspace->invitations()->create([
        'invited_by_user_id' => $owner->id,
        'email' => 'expired@example.com',
        'role' => WorkspaceRole::Admin->value,
        'token' => Str::lower((string) Str::uuid()),
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('workspaces.invitations.accept', ['token' => $invitation->token]));

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $invitedUser->id,
    ]);
});
