<?php

namespace App\Services\Webhooks;

use App\Jobs\Webhooks\SendWorkspaceWebhookDelivery;
use App\Models\Workspace;
use App\Models\WorkspaceWebhookDelivery;
use App\Models\WorkspaceWebhookEndpoint;

class WorkspaceWebhookService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Workspace $workspace, string $eventType, array $payload): int
    {
        if ($eventType === '') {
            return 0;
        }

        $matchedEndpoints = WorkspaceWebhookEndpoint::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->get()
            ->filter(static fn (WorkspaceWebhookEndpoint $endpoint): bool => self::endpointMatchesEvent($endpoint, $eventType))
            ->values();

        $created = 0;

        foreach ($matchedEndpoints as $endpoint) {
            $delivery = WorkspaceWebhookDelivery::query()->create([
                'workspace_webhook_endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'pending',
                'attempt_count' => 0,
            ]);

            SendWorkspaceWebhookDelivery::dispatch($delivery->id);
            $created++;
        }

        return $created;
    }

    /**
     * @return array<string, string>
     */
    public static function supportedEvents(): array
    {
        return [
            'workspace.invitation.sent' => 'Workspace invitation sent',
            'workspace.member.joined' => 'Workspace member joined',
            'workspace.member.removed' => 'Workspace member removed',
            'workspace.member.role_updated' => 'Workspace member role updated',
            'workspace.ownership.transferred' => 'Workspace ownership transferred',
            'billing.checkout.started' => 'Billing checkout started',
            'billing.subscription.updated' => 'Billing subscription updated',
            'billing.subscription.cancelled' => 'Billing subscription cancelled',
            'billing.subscription.resumed' => 'Billing subscription resumed',
        ];
    }

    public static function endpointMatchesEvent(WorkspaceWebhookEndpoint $endpoint, string $eventType): bool
    {
        $events = is_array($endpoint->events) ? $endpoint->events : [];

        if ($events === []) {
            return false;
        }

        return in_array('*', $events, true) || in_array($eventType, $events, true);
    }
}
