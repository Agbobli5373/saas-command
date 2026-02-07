<?php

use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Notifications\Billing\PaymentFailedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('cashier.webhook.secret', null);
});

function stripeWebhookPayload(string $id, string $type): array
{
    return [
        'id' => $id,
        'type' => $type,
        'data' => [
            'object' => [
                'id' => 'obj_test_123',
                'customer' => 'cus_test_123',
            ],
        ],
    ];
}

test('webhook event is stored and processed', function () {
    $payload = stripeWebhookPayload('evt_test_checkout_1', 'checkout.session.completed');

    $this->postJson(route('cashier.webhook'), $payload)->assertOk();

    $this->assertDatabaseHas('stripe_webhook_events', [
        'stripe_event_id' => 'evt_test_checkout_1',
        'event_type' => 'checkout.session.completed',
        'status' => 'processed',
    ]);

    expect(
        StripeWebhookEvent::query()
            ->where('stripe_event_id', 'evt_test_checkout_1')
            ->value('processed_at')
    )->not->toBeNull();
});

test('duplicate webhook event is idempotent', function () {
    $payload = stripeWebhookPayload('evt_test_checkout_2', 'checkout.session.completed');

    $this->postJson(route('cashier.webhook'), $payload)->assertOk();
    $this->postJson(route('cashier.webhook'), $payload)->assertOk();

    expect(
        StripeWebhookEvent::query()
            ->where('stripe_event_id', 'evt_test_checkout_2')
            ->count()
    )->toBe(1);
});

test('invoice payment failed event is flagged as action required', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
    ]);

    Notification::fake();

    $payload = stripeWebhookPayload('evt_test_invoice_1', 'invoice.payment_failed');

    $this->postJson(route('cashier.webhook'), $payload)->assertOk();

    $this->assertDatabaseHas('stripe_webhook_events', [
        'stripe_event_id' => 'evt_test_invoice_1',
        'event_type' => 'invoice.payment_failed',
        'status' => 'action_required',
    ]);

    Notification::assertSentTo($user, PaymentFailedNotification::class);
});

test('invoice payment failed event stores an in-app database notification', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
    ]);

    $payload = stripeWebhookPayload('evt_test_invoice_2', 'invoice.payment_failed');

    $this->postJson(route('cashier.webhook'), $payload)->assertOk();

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'type' => PaymentFailedNotification::class,
    ]);
});

test('invoice payment failed event is resolved by a later paid event', function () {
    User::factory()->create([
        'stripe_id' => 'cus_test_123',
    ]);

    $this->postJson(route('cashier.webhook'), stripeWebhookPayload('evt_failed_1', 'invoice.payment_failed'))->assertOk();
    $this->postJson(route('cashier.webhook'), stripeWebhookPayload('evt_paid_1', 'invoice.paid'))->assertOk();

    $this->assertDatabaseHas('stripe_webhook_events', [
        'stripe_event_id' => 'evt_failed_1',
        'event_type' => 'invoice.payment_failed',
        'status' => 'resolved',
    ]);
});
