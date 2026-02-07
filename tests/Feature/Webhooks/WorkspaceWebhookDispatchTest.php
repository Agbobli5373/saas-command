<?php

use App\Jobs\Webhooks\SendWorkspaceWebhookDelivery;
use App\Models\User;
use App\Models\WorkspaceWebhookDelivery;
use App\Models\WorkspaceWebhookEndpoint;
use App\Services\Webhooks\WorkspaceWebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.stripe.default_plan', 'free');
    config()->set('services.stripe.plans.free.feature_flags', ['outbound_webhooks']);
});

test('dispatch creates delivery jobs for matching active endpoints', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    WorkspaceWebhookEndpoint::factory()->create([
        'workspace_id' => $workspace?->id,
        'events' => ['workspace.member.joined'],
        'is_active' => true,
    ]);

    WorkspaceWebhookEndpoint::factory()->create([
        'workspace_id' => $workspace?->id,
        'events' => ['billing.subscription.updated'],
        'is_active' => true,
    ]);

    WorkspaceWebhookEndpoint::factory()->create([
        'workspace_id' => $workspace?->id,
        'events' => ['workspace.member.joined'],
        'is_active' => false,
    ]);

    $created = app(WorkspaceWebhookService::class)->dispatch(
        $workspace,
        'workspace.member.joined',
        ['member_user_id' => 123]
    );

    expect($created)->toBe(1);

    $this->assertDatabaseCount('workspace_webhook_deliveries', 1);
    Queue::assertPushed(SendWorkspaceWebhookDelivery::class, 1);
});

test('delivery job signs request and marks delivery as delivered on success', function () {
    $endpoint = WorkspaceWebhookEndpoint::factory()->create([
        'events' => ['workspace.member.joined'],
        'signing_secret' => 'whsec_test_secret_123456789',
        'url' => 'https://example.test/hooks/success',
        'is_active' => true,
    ]);

    $delivery = WorkspaceWebhookDelivery::factory()->create([
        'workspace_webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'workspace.member.joined',
        'payload' => ['member_user_id' => 55],
        'status' => 'pending',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.test/hooks/success' => Http::response(['ok' => true], 200),
    ]);

    (new SendWorkspaceWebhookDelivery($delivery->id))->handle();

    $delivery->refresh();
    $endpoint->refresh();

    expect($delivery->status)->toBe('delivered')
        ->and($delivery->response_status_code)->toBe(200)
        ->and($delivery->attempt_count)->toBe(1);

    expect($endpoint->failure_count)->toBe(0)
        ->and($endpoint->last_dispatched_at)->not->toBeNull();
});

test('delivery job marks retrying on failed response and throws for retry', function () {
    $endpoint = WorkspaceWebhookEndpoint::factory()->create([
        'events' => ['workspace.member.joined'],
        'signing_secret' => 'whsec_test_secret_123456789',
        'url' => 'https://example.test/hooks/failure',
        'is_active' => true,
    ]);

    $delivery = WorkspaceWebhookDelivery::factory()->create([
        'workspace_webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'workspace.member.joined',
        'payload' => ['member_user_id' => 77],
        'status' => 'pending',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.test/hooks/failure' => Http::response(['error' => 'bad'], 500),
    ]);

    expect(fn () => (new SendWorkspaceWebhookDelivery($delivery->id))->handle())
        ->toThrow(\RuntimeException::class);

    $delivery->refresh();
    $endpoint->refresh();

    expect($delivery->status)->toBe('retrying')
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->response_status_code)->toBe(500);

    expect($endpoint->failure_count)->toBe(1)
        ->and($endpoint->last_error_message)->toContain('HTTP 500');
});
