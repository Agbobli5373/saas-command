<?php

use App\Models\User;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Workspace\WorkspaceMemberJoinedNotification;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from notification center', function () {
    $response = $this->get(route('notifications.index'));

    $response->assertRedirect(route('login'));
});

test('notification center lists billing and product notifications', function () {
    $user = User::factory()->create();

    $user->notify(new PaymentFailedNotification('evt_center_1'));
    $user->notify(new WorkspaceMemberJoinedNotification('Acme Workspace', 'Jane Member'));

    $paymentNotification = $user->notifications()
        ->where('type', PaymentFailedNotification::class)
        ->first();

    $paymentNotification?->markAsRead();

    $response = $this
        ->actingAs($user)
        ->get(route('notifications.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/notifications')
        ->has('notifications', 2)
        ->where('unreadCount', 1)
        ->where('notifications', fn ($notifications): bool => collect($notifications)
            ->pluck('title')
            ->intersect(['New teammate joined', 'Payment failed'])
            ->count() === 2)
    );
});

test('user can mark one notification as read', function () {
    $user = User::factory()->create();

    $user->notify(new PaymentFailedNotification('evt_center_2'));

    $notification = $user->notifications()->first();

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->from(route('notifications.index'))
        ->post(route('notifications.read', ['notification' => $notification->id]));

    $response->assertRedirect(route('notifications.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user cannot mark another users notification as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUser->notify(new PaymentFailedNotification('evt_center_3'));

    $notification = $otherUser->notifications()->first();

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->post(route('notifications.read', ['notification' => $notification->id]));

    $response->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('user can mark all notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new PaymentFailedNotification('evt_center_4'));
    $user->notify(new WorkspaceMemberJoinedNotification('Acme Workspace', 'John Member'));

    $response = $this
        ->actingAs($user)
        ->from(route('notifications.index'))
        ->post(route('notifications.read-all'));

    $response->assertRedirect(route('notifications.index'));

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
