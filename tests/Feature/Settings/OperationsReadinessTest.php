<?php

use App\Enums\WorkspaceRole;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function backupMarkerPath(): string
{
    $path = storage_path(sprintf('framework/testing/ops-backup-%s.marker', Str::uuid()));

    File::ensureDirectoryExists(dirname($path));

    return $path;
}

test('guests are redirected from operations readiness page', function () {
    $response = $this->get(route('operations.show'));

    $response->assertRedirect(route('login'));
});

test('workspace owners can view production readiness checks', function () {
    $user = User::factory()->create();

    $backupFile = backupMarkerPath();
    File::put($backupFile, 'ok');

    config()->set('operations.backup.health_file', $backupFile);

    $response = $this
        ->actingAs($user)
        ->get(route('operations.show'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/operations')
        ->where('workspaceName', $user->activeWorkspace()->name)
        ->where('overallStatus', 'pass')
        ->has('checks', 4)
        ->where('checks.0.key', 'failed_jobs')
        ->where('checks.3.key', 'backup_freshness')
        ->where('checks.3.status', 'pass')
    );
});

test('operations readiness warns when queue and webhook issues are present', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();
    $workspace->forceFill([
        'stripe_id' => 'cus_ops_123',
    ])->save();

    $backupFile = backupMarkerPath();
    File::put($backupFile, 'ok');

    config()->set('operations.backup.health_file', $backupFile);
    config()->set('operations.failed_jobs.warning_threshold', 1);
    config()->set('operations.stripe_webhooks.stale_minutes', 10);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException: test failure',
        'failed_at' => now(),
    ]);

    $queuedEvent = StripeWebhookEvent::query()->create([
        'stripe_event_id' => 'evt_ops_queued_1',
        'event_type' => 'invoice.paid',
        'status' => 'queued',
        'payload' => [
            'data' => [
                'object' => [
                    'customer' => 'cus_ops_123',
                ],
            ],
        ],
    ]);

    $queuedEvent->forceFill([
        'created_at' => now()->subMinutes(20),
        'updated_at' => now()->subMinutes(20),
    ])->save();

    StripeWebhookEvent::query()->create([
        'stripe_event_id' => 'evt_ops_failed_1',
        'event_type' => 'invoice.payment_failed',
        'status' => 'failed',
        'payload' => [
            'data' => [
                'object' => [
                    'customer' => 'cus_ops_123',
                ],
            ],
        ],
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('operations.show'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('overallStatus', 'warning')
        ->where('checks.0.status', 'warning')
        ->where('checks.1.status', 'warning')
        ->where('checks.2.status', 'warning')
        ->where('checks.3.status', 'pass')
    );
});

test('operations readiness fails when backup marker is missing', function () {
    $owner = User::factory()->create();

    config()->set('operations.backup.health_file', storage_path('framework/testing/missing-backup.marker'));

    $response = $this
        ->actingAs($owner)
        ->get(route('operations.show'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('overallStatus', 'fail')
        ->where('checks.3.key', 'backup_freshness')
        ->where('checks.3.status', 'fail')
    );
});

test('workspace members cannot access operations readiness', function () {
    $owner = User::factory()->create();
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create();
    $workspace->addMember($member, WorkspaceRole::Member);
    $member->switchWorkspace($workspace);

    $response = $this
        ->actingAs($member)
        ->get(route('operations.show'));

    $response->assertForbidden();
});
