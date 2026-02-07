<?php

use App\Models\User;
use App\Services\Usage\UsageMeteringService;
use Carbon\CarbonImmutable;

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
            'features' => [],
            'feature_flags' => ['team_invitations'],
            'limits' => [
                'seats' => 3,
                'team_invitations_per_month' => 5,
            ],
            'highlighted' => false,
        ],
        'starter_monthly' => [
            'billing_mode' => 'stripe',
            'price_id' => 'price_monthly_123',
            'title' => 'Starter Monthly',
            'price_label' => '$29',
            'interval_label' => '/month',
            'description' => 'Starter paid plan',
            'features' => [],
            'feature_flags' => ['team_invitations'],
            'limits' => [
                'seats' => null,
                'team_invitations_per_month' => null,
            ],
            'highlighted' => true,
        ],
    ]);
});

test('usage events are tracked and monthly counters are incremented', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $meter = app(UsageMeteringService::class);

    $meter->track($workspace, 'team_invitations_sent', 2, ['source' => 'test']);
    $meter->track($workspace, 'team_invitations_sent', 1, ['source' => 'test']);

    $this->assertDatabaseCount('workspace_usage_events', 2);
    $this->assertDatabaseHas('workspace_usage_counters', [
        'workspace_id' => $workspace->id,
        'metric_key' => 'team_invitations_sent',
        'period_start' => now()->startOfMonth()->toDateString(),
        'used' => 3,
        'quota' => 5,
    ]);

    $usage = $meter->currentPeriodUsage($workspace);

    expect($usage)->toHaveCount(1)
        ->and($usage[0]['used'])->toBe(3)
        ->and($usage[0]['quota'])->toBe(5)
        ->and($usage[0]['remaining'])->toBe(2)
        ->and($usage[0]['isExceeded'])->toBeFalse();
});

test('usage counters reset by creating a new monthly period record', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $meter = app(UsageMeteringService::class);

    $meter->track(
        workspace: $workspace,
        metricKey: 'team_invitations_sent',
        quantity: 2,
        occurredAt: CarbonImmutable::parse('2026-02-05 10:00:00')
    );

    $meter->track(
        workspace: $workspace,
        metricKey: 'team_invitations_sent',
        quantity: 1,
        occurredAt: CarbonImmutable::parse('2026-03-02 10:00:00')
    );

    $this->assertDatabaseHas('workspace_usage_counters', [
        'workspace_id' => $workspace->id,
        'metric_key' => 'team_invitations_sent',
        'period_start' => '2026-02-01',
        'used' => 2,
    ]);

    $this->assertDatabaseHas('workspace_usage_counters', [
        'workspace_id' => $workspace->id,
        'metric_key' => 'team_invitations_sent',
        'period_start' => '2026-03-01',
        'used' => 1,
    ]);

    $februaryUsage = $meter->currentPeriodUsage($workspace, CarbonImmutable::parse('2026-02-27'));
    $marchUsage = $meter->currentPeriodUsage($workspace, CarbonImmutable::parse('2026-03-27'));

    expect($februaryUsage[0]['used'])->toBe(2)
        ->and($marchUsage[0]['used'])->toBe(1);
});

test('quota resolves to unlimited when workspace is on a paid plan without limit', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $workspace->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_usage_quota_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'quantity' => 1,
    ]);

    $meter = app(UsageMeteringService::class);

    expect($meter->quota($workspace, 'team_invitations_sent'))->toBeNull();

    $meter->track($workspace, 'team_invitations_sent', 4);

    $usage = $meter->currentPeriodUsage($workspace);

    expect($usage[0]['isUnlimited'])->toBeTrue()
        ->and($usage[0]['quota'])->toBeNull()
        ->and($usage[0]['remaining'])->toBeNull();
});
