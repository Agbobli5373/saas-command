<?php

use App\Models\StripeWebhookEvent;

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
    $payload = stripeWebhookPayload('evt_test_invoice_1', 'invoice.payment_failed');

    $this->postJson(route('cashier.webhook'), $payload)->assertOk();

    $this->assertDatabaseHas('stripe_webhook_events', [
        'stripe_event_id' => 'evt_test_invoice_1',
        'event_type' => 'invoice.payment_failed',
        'status' => 'action_required',
    ]);
});
