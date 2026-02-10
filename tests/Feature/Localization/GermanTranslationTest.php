<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Notifications\Billing\PaymentFailedNotification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('services.stripe.default_plan', 'free');
    config()->set('services.stripe.usage.metrics', [
        'team_invitations_sent' => [
            'title' => 'Team Invitations',
            'description' => 'Invitations sent to teammates this month.',
            'limit_key' => 'team_invitations_per_month',
        ],
    ]);
    config()->set('services.stripe.plans', [
        'free' => [
            'billing_mode' => 'free',
            'title' => 'Free',
            'price_label' => '$0',
            'interval_label' => '/forever',
            'description' => 'Free plan',
            'features' => ['Feature Free'],
            'feature_flags' => ['team_invitations'],
            'limits' => [
                'seats' => null,
                'team_invitations_per_month' => 10,
            ],
            'highlighted' => false,
        ],
    ]);
});

test('workspace invitation flash status is translated in german locale', function () {
    Notification::fake();

    $owner = User::factory()->create([
        'locale' => 'de',
    ]);

    $response = $this
        ->actingAs($owner)
        ->from(route('home'))
        ->post(route('workspaces.invitations.store'), [
            'email' => 'einladung@example.com',
            'role' => WorkspaceRole::Member->value,
        ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('status', __('Invitation sent to :email.', [
        'email' => 'einladung@example.com',
    ], 'de'));
});

test('payment failed notification payload is translated in german locale', function () {
    App::setLocale('de');

    $notification = new PaymentFailedNotification('evt_123');
    $payload = $notification->toArray(new stdClass);
    $mailMessage = $notification->toMail(new stdClass);

    expect($payload)
        ->toMatchArray([
            'title' => __('Payment failed', locale: 'de'),
            'message' => __('Your latest subscription payment failed. Update your payment method to continue service.', locale: 'de'),
            'stripe_event_id' => 'evt_123',
        ]);

    expect($mailMessage->subject)->toBe(__('Payment failed for your subscription', locale: 'de'))
        ->and($mailMessage->actionText)->toBe(__('Open Billing Settings', locale: 'de'))
        ->and($mailMessage->greeting)->toBe(__('Action required', locale: 'de'));
});
