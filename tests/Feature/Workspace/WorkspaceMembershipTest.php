<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUsageCounter;
use Inertia\Testing\AssertableInertia as Assert;

test('creating a user provisions a personal workspace', function () {
    $user = User::factory()->create([
        'name' => 'Test Owner',
    ]);

    $user->refresh();

    expect($user->current_workspace_id)->not->toBeNull();

    $workspace = $user->currentWorkspace()->first();

    expect($workspace)->not->toBeNull()
        ->and($workspace->name)->toBe('Test Owner Workspace')
        ->and($workspace->owner_id)->toBe($user->id)
        ->and($workspace->is_personal)->toBeTrue();

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner->value,
    ]);
});

test('users can switch to a workspace they belong to', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->addMember($user, WorkspaceRole::Admin);

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('workspaces.current.update'), [
            'workspace_id' => $workspace->id,
        ]);

    $response->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('users cannot switch to a workspace they do not belong to', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('workspaces.current.update'), [
            'workspace_id' => $workspace->id,
        ]);

    $response->assertForbidden();

    expect($user->fresh()->current_workspace_id)->not->toBe($workspace->id);
});

test('active workspace ignores invalid current workspace assignment', function () {
    $user = User::factory()->create();
    $foreignWorkspace = Workspace::factory()->create();

    $user->forceFill([
        'current_workspace_id' => $foreignWorkspace->id,
    ])->save();

    $activeWorkspace = $user->activeWorkspace();

    expect($activeWorkspace)->not->toBeNull()
        ->and($activeWorkspace->id)->not->toBe($foreignWorkspace->id)
        ->and($user->fresh()->current_workspace_id)->toBe($activeWorkspace->id);
});

test('dashboard shares active workspace context for authenticated users', function () {
    $user = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $workspace = Workspace::factory()->create();

    $workspace->addMember($user, WorkspaceRole::Admin);
    $user->switchWorkspace($workspace);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.current_workspace.id', $workspace->id)
        ->has('auth.workspaces', 2)
    );
});

test('workspace page shares metered usage summary for the current month', function () {
    config()->set('services.stripe.usage.metrics', [
        'team_invitations_sent' => [
            'title' => 'Team Invitations',
            'description' => 'Invitations sent to teammates this month.',
            'limit_key' => 'team_invitations_per_month',
        ],
    ]);
    config()->set('services.stripe.plans.free.limits.team_invitations_per_month', 5);

    $owner = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $workspace = $owner->activeWorkspace();

    WorkspaceUsageCounter::query()->create([
        'workspace_id' => $workspace->id,
        'metric_key' => 'team_invitations_sent',
        'period_start' => now()->startOfMonth()->toDateString(),
        'used' => 2,
        'quota' => 5,
    ]);

    WorkspaceUsageCounter::query()->create([
        'workspace_id' => $workspace->id,
        'metric_key' => 'team_invitations_sent',
        'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        'used' => 9,
        'quota' => 5,
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('workspace'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('usagePeriod.start', now()->startOfMonth()->toDateString())
        ->where('usageMetrics.0.key', 'team_invitations_sent')
        ->where('usageMetrics.0.used', 2)
        ->where('usageMetrics.0.quota', 5)
        ->where('usageMetrics.0.remaining', 3)
    );
});

test('adding a member updates subscription seat quantity', function () {
    config()->set('services.stripe.seat_quantity.sync_with_stripe', false);

    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_seat_add_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 1,
    ]);

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);

    $this->assertDatabaseHas('subscriptions', [
        'stripe_id' => 'sub_seat_add_123',
        'quantity' => 2,
    ]);
});

test('removing a member decreases seat quantity but keeps minimum of one', function () {
    config()->set('services.stripe.seat_quantity.sync_with_stripe', false);

    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);

    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_seat_remove_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 2,
    ]);

    $workspace->removeMember($member);

    $this->assertDatabaseHas('subscriptions', [
        'stripe_id' => 'sub_seat_remove_123',
        'quantity' => 1,
    ]);

    $workspace->removeMember($owner);

    $this->assertDatabaseHas('subscriptions', [
        'stripe_id' => 'sub_seat_remove_123',
        'quantity' => 1,
    ]);
});

test('workspace owner can update a member role', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->patch(route('workspaces.members.update', ['member' => $member->id]), [
            'role' => WorkspaceRole::Admin->value,
        ]);

    $response->assertRedirect(route('workspace'));

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Admin->value,
    ]);
});

test('workspace members cannot update other member roles', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);
    $member->switchWorkspace($workspace);

    $targetMember = User::factory()->create();
    $workspace->addMember($targetMember, WorkspaceRole::Member);

    $response = $this
        ->actingAs($member)
        ->patch(route('workspaces.members.update', ['member' => $targetMember->id]), [
            'role' => WorkspaceRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('workspace owner can remove a member through endpoint', function () {
    config()->set('services.stripe.seat_quantity.sync_with_stripe', false);

    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();
    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_member_remove_endpoint_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 2,
    ]);

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->delete(route('workspaces.members.destroy', ['member' => $member->id]));

    $response->assertRedirect(route('workspace'));

    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'stripe_id' => 'sub_member_remove_endpoint_123',
        'quantity' => 1,
    ]);
});

test('workspace owner can transfer ownership to another member', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $admin = User::factory()->create();
    $workspace->addMember($admin, WorkspaceRole::Admin);

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->post(route('workspaces.ownership.transfer'), [
            'owner_id' => $admin->id,
        ]);

    $response->assertRedirect(route('workspace'));

    expect($workspace->fresh()->owner_id)->toBe($admin->id);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
        'role' => WorkspaceRole::Owner->value,
    ]);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::Admin->value,
    ]);
});

test('workspace admins cannot transfer ownership', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $admin = User::factory()->create();
    $workspace->addMember($admin, WorkspaceRole::Admin);
    $admin->switchWorkspace($workspace);

    $target = User::factory()->create();
    $workspace->addMember($target, WorkspaceRole::Member);

    $response = $this
        ->actingAs($admin)
        ->post(route('workspaces.ownership.transfer'), [
            'owner_id' => $target->id,
        ]);

    $response->assertForbidden();
});
