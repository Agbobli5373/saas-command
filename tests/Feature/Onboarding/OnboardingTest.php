<?php

use App\Models\User;
use App\Services\Billing\BillingService;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

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
            'highlighted' => false,
        ],
        'starter_yearly' => [
            'price_id' => 'price_yearly_123',
            'billing_mode' => 'stripe',
            'title' => 'Starter Yearly',
            'price_label' => '$290',
            'interval_label' => '/year',
            'description' => 'Yearly plan',
            'features' => ['Feature B'],
            'feature_flags' => ['team_invitations'],
            'limits' => ['seats' => null],
            'highlighted' => true,
        ],
    ]);
});

test('incomplete users are redirected from dashboard to onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('onboarding.show'));
});

test('onboarding page is displayed for incomplete users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('onboarding.show'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('onboarding')
        ->where('workspaceName', $user->activeWorkspace()->name)
        ->has('plans', 3)
    );
});

test('completed users are redirected from onboarding to dashboard', function () {
    $user = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('onboarding.show'));

    $response->assertRedirect(route('dashboard'));
});

test('onboarding submission starts stripe checkout and updates workspace name', function () {
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
        ->post(route('onboarding.store'), [
            'workspace_name' => 'Acme SaaS',
            'plan' => 'starter_monthly',
        ]);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/session');

    expect($user->activeWorkspace()->name)->toBe('Acme SaaS');
});

test('onboarding submission on free tier completes without stripe checkout', function () {
    $user = User::factory()->create();

    $this->mock(BillingService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('checkout');
    });

    $response = $this
        ->actingAs($user)
        ->post(route('onboarding.store'), [
            'workspace_name' => 'Acme Free',
            'plan' => 'free',
        ]);

    $response->assertRedirect(route('dashboard'));

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull();
    expect($user->activeWorkspace()->name)->toBe('Acme Free');
});

test('users with active subscription are marked complete and can access dashboard', function () {
    $user = User::factory()->create();
    $workspace = $user->activeWorkspace();

    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_onboarding_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('onboarding.show'));

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->onboarding_completed_at)->not->toBeNull();
});
