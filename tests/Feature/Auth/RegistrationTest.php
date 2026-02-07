<?php

use App\Enums\WorkspaceRole;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->current_workspace_id)->not->toBeNull();

    $this->assertDatabaseHas('workspaces', [
        'id' => $user->current_workspace_id,
        'owner_id' => $user->id,
        'is_personal' => true,
    ]);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $user->current_workspace_id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner->value,
    ]);
});
