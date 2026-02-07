<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
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
    $user = User::factory()->create();
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
