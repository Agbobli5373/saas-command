<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Notifications\Workspace\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('services.stripe.default_plan', 'free');

    config()->set('services.stripe.plans', [
        'free' => [
            'billing_mode' => 'free',
            'title' => 'Free',
            'price_label' => '$0',
            'interval_label' => '/forever',
            'description' => 'Free plan',
            'features' => ['Feature Free'],
            'feature_flags' => ['team_invitations'],
            'limits' => ['seats' => 3],
            'highlighted' => false,
        ],
        'starter_monthly' => [
            'price_id' => 'price_monthly_123',
            'billing_mode' => 'stripe',
            'title' => 'Starter Monthly',
            'price_label' => '$29',
            'interval_label' => '/month',
            'description' => 'Monthly plan',
            'features' => ['Feature A'],
            'feature_flags' => ['team_invitations'],
            'limits' => ['seats' => null],
            'highlighted' => true,
        ],
    ]);
});

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

test('workspace invitation creation is blocked when seat limit is reached', function () {
    config()->set('services.stripe.plans.free.limits.seats', 2);

    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $existingMember = User::factory()->create();
    $workspace->addMember($existingMember, WorkspaceRole::Member);

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->post(route('workspaces.invitations.store'), [
            'email' => 'blocked@example.com',
            'role' => WorkspaceRole::Member->value,
        ]);

    $response->assertRedirect(route('workspace'));
    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'up to 2 seats'));

    $this->assertDatabaseMissing('workspace_invitations', [
        'workspace_id' => $workspace->id,
        'email' => 'blocked@example.com',
    ]);
});

test('workspace invitation creation requires plan feature flag', function () {
    config()->set('services.stripe.plans.free.feature_flags', []);

    $owner = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->post(route('workspaces.invitations.store'), [
            'email' => 'blocked@example.com',
            'role' => WorkspaceRole::Member->value,
        ]);

    $response->assertForbidden();
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
    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_invite_accept_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 1,
    ]);

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

    $this->assertDatabaseHas('subscriptions', [
        'stripe_id' => 'sub_invite_accept_123',
        'quantity' => 2,
    ]);
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
