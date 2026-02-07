<?php

use App\Enums\WorkspaceRole;
use App\Models\StripeWebhookEvent;
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

    config()->set('services.stripe.warnings', []);
});

test('billing settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('billing.edit'));

    $response->assertOk();
});

test('billing settings page shares plan metadata and invoice history', function () {
    $user = User::factory()->create();

    $this->mock(BillingService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('invoices')
            ->once()
            ->andReturn([
                [
                    'id' => 'in_test_123',
                    'number' => 'INV-0001',
                    'status' => 'paid',
                    'total' => '$29.00',
                    'amountPaid' => '$29.00',
                    'date' => '2026-02-07',
                    'currency' => 'USD',
                    'hostedInvoiceUrl' => 'https://example.test/invoice',
                    'invoicePdfUrl' => 'https://example.test/invoice.pdf',
                ],
            ]);
    });

    $response = $this
        ->actingAs($user)
        ->get(route('billing.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/billing')
        ->has('plans', 3)
        ->where('plans.0.key', 'free')
        ->where('plans.1.key', 'starter_monthly')
        ->where('currentPlanKey', 'free')
        ->where('currentPlanBillingMode', 'free')
        ->where('seatCount', 1)
        ->where('seatLimit', 3)
        ->where('remainingSeatCapacity', 2)
        ->where('billedSeatCount', 1)
        ->has('invoices', 1)
        ->where('invoices.0.id', 'in_test_123')
        ->where('invoices.0.invoicePdfUrl', 'https://example.test/invoice.pdf')
    );
});

test('billing settings page shares seat metrics from workspace subscription', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);

    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_metrics_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 5,
    ]);

    $this->mock(BillingService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('invoices')->once()->andReturn([]);
    });

    $response = $this
        ->actingAs($owner)
        ->get(route('billing.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seatCount', 2)
        ->where('billedSeatCount', 5)
        ->where('seatLimit', null)
    );
});

test('billing settings page shows payment warning outcome when payment failed recently', function () {
    $user = User::factory()->create();

    StripeWebhookEvent::query()->create([
        'stripe_event_id' => 'evt_failed_123',
        'event_type' => 'invoice.payment_failed',
        'status' => 'action_required',
        'message' => 'Invoice payment failed.',
        'payload' => ['id' => 'evt_failed_123'],
        'handled_by_cashier_at' => now(),
        'processed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('billing.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('webhookOutcome.status', 'warning')
    );
});

test('billing settings page clears payment warning when failure is resolved', function () {
    $user = User::factory()->create();

    StripeWebhookEvent::query()->create([
        'stripe_event_id' => 'evt_failed_124',
        'event_type' => 'invoice.payment_failed',
        'status' => 'resolved',
        'message' => 'Payment issue was resolved.',
        'payload' => ['id' => 'evt_failed_124'],
        'handled_by_cashier_at' => now()->subMinutes(5),
        'processed_at' => now()->subMinutes(5),
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('billing.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('webhookOutcome', null)
    );
});

test('guests are redirected from billing settings page', function () {
    $response = $this->get(route('billing.edit'));

    $response->assertRedirect(route('login'));
});

test('checkout redirects to stripe checkout url for paid plans', function () {
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

test('checkout returns validation error for free plan', function () {
    $user = User::factory()->create();

    $this->mock(BillingService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('checkout');
    });

    $response = $this
        ->actingAs($user)
        ->from(route('billing.edit'))
        ->post(route('billing.checkout'), [
            'plan' => 'free',
        ]);

    $response->assertRedirect(route('billing.edit'));
    $response->assertSessionHasErrors('plan');
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

test('free-tier onboarded users can access workspace', function () {
    $user = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspace'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('workspace')
        ->where('seatCount', 1)
        ->where('seatLimit', 3)
    );
});

test('subscribed users can access workspace', function () {
    $user = User::factory()->create();
    $workspace = $user->activeWorkspace();

    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspace'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('workspace')
        ->where('seatCount', 1)
        ->where('billedSeatCount', 1)
    );
});

test('workspace members cannot access billing settings', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);
    $member->switchWorkspace($workspace);

    $response = $this
        ->actingAs($member)
        ->get(route('billing.edit'));

    $response->assertForbidden();
});

test('workspace members cannot start checkout', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);
    $member->switchWorkspace($workspace);

    $response = $this
        ->actingAs($member)
        ->post(route('billing.checkout'), [
            'plan' => 'starter_monthly',
        ]);

    $response->assertForbidden();
});
