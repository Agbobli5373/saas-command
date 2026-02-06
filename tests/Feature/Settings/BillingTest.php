<?php

use App\Models\User;
use App\Services\Billing\BillingService;
use Mockery\MockInterface;

beforeEach(function () {
    config()->set('services.stripe.prices', [
        'starter_monthly' => 'price_monthly_123',
        'starter_yearly' => 'price_yearly_123',
    ]);
});

test('billing settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('billing.edit'));

    $response->assertOk();
});

test('guests are redirected from billing settings page', function () {
    $response = $this->get(route('billing.edit'));

    $response->assertRedirect(route('login'));
});

test('checkout redirects to stripe checkout url', function () {
    $user = User::factory()->create();

    $this->mock(BillingService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('checkout')
            ->once()
            ->andReturn('https://checkout.stripe.test/session');
    });

    $response = $this
        ->actingAs($user)
        ->post(route('billing.checkout'), [
            'plan' => 'starter_monthly',
        ]);

    $response->assertRedirect('https://checkout.stripe.test/session');
});

test('checkout returns inertia location response for inertia requests', function () {
    $user = User::factory()->create();

    $this->mock(BillingService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('checkout')
            ->once()
            ->andReturn('https://checkout.stripe.test/session');
    });

    $response = $this
        ->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('billing.checkout'), [
            'plan' => 'starter_monthly',
        ]);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/session');
});

test('unsubscribed users are redirected from workspace to billing settings', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('workspace'));

    $response->assertRedirect(route('billing.edit'));
});

test('subscribed users can access workspace', function () {
    $user = User::factory()->create();

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspace'));

    $response->assertOk();
});
