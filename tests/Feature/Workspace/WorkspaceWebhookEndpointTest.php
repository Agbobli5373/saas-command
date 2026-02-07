<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceWebhookEndpoint;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('services.stripe.default_plan', 'free');
    config()->set('services.stripe.plans', [
        'free' => [
            'billing_mode' => 'free',
            'title' => 'Free',
            'price_label' => '$0',
            'interval_label' => '/forever',
            'description' => 'Free plan',
            'features' => [],
            'feature_flags' => ['team_invitations', 'outbound_webhooks'],
            'limits' => ['seats' => 3],
            'highlighted' => false,
        ],
    ]);
});

test('workspace owner can create outbound webhook endpoint', function () {
    $owner = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->post(route('workspaces.webhooks.store'), [
            'name' => 'CRM',
            'url' => 'https://example.test/hooks/workspace',
            'signing_secret' => 'whsec_abcdefghijklmnopqrstuvwxyz123456',
            'events' => ['workspace.member.joined', 'billing.subscription.updated'],
        ]);

    $response->assertRedirect(route('workspace'));

    $this->assertDatabaseHas('workspace_webhook_endpoints', [
        'workspace_id' => $owner->activeWorkspace()?->id,
        'name' => 'CRM',
        'url' => 'https://example.test/hooks/workspace',
        'is_active' => 1,
    ]);
});

test('workspace member cannot create outbound webhook endpoint', function () {
    $owner = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $workspace = $owner->activeWorkspace();

    $member = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $workspace?->addMember($member, WorkspaceRole::Member);
    $member->switchWorkspace($workspace);

    $response = $this
        ->actingAs($member)
        ->post(route('workspaces.webhooks.store'), [
            'name' => 'Blocked',
            'url' => 'https://example.test/hooks/blocked',
            'signing_secret' => 'whsec_abcdefghijklmnopqrstuvwxyz123456',
            'events' => ['workspace.member.joined'],
        ]);

    $response->assertForbidden();
});

test('workspace owner can disable an outbound webhook endpoint', function () {
    $owner = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $workspace = $owner->activeWorkspace();

    $endpoint = WorkspaceWebhookEndpoint::factory()->create([
        'workspace_id' => $workspace?->id,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->from(route('workspace'))
        ->delete(route('workspaces.webhooks.destroy', ['endpoint' => $endpoint->id]));

    $response->assertRedirect(route('workspace'));

    $this->assertDatabaseHas('workspace_webhook_endpoints', [
        'id' => $endpoint->id,
        'is_active' => 0,
    ]);
});

test('workspace page shares webhook endpoint settings and capabilities', function () {
    $owner = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $workspace = $owner->activeWorkspace();

    WorkspaceWebhookEndpoint::factory()->create([
        'workspace_id' => $workspace?->id,
        'name' => 'Analytics',
        'events' => ['workspace.member.joined'],
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('workspace'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('canManageWebhooks', true)
        ->has('webhookEndpoints', 1)
        ->where('webhookEndpoints.0.name', 'Analytics')
        ->where('webhookEndpoints.0.isActive', true)
        ->where('supportedWebhookEvents', function (mixed $events): bool {
            if ($events instanceof Collection) {
                $events = $events->all();
            }

            if (! is_array($events)) {
                return false;
            }

            return ($events['workspace.member.joined'] ?? null) === 'Workspace member joined';
        })
    );
});
