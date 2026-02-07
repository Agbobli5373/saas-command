<?php

namespace App\Services\Billing;

use App\Models\BillingAuditEvent;
use App\Models\Workspace;
use Throwable;

class BillingAuditLogger
{
    /**
     * Record a billing audit event.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(
        Workspace $workspace,
        string $eventType,
        string $title,
        ?string $description = null,
        ?int $actorId = null,
        string $source = 'system',
        string $severity = 'info',
        array $context = []
    ): ?BillingAuditEvent {
        try {
            return BillingAuditEvent::query()->create([
                'workspace_id' => $workspace->id,
                'actor_id' => $actorId,
                'event_type' => $eventType,
                'source' => $source,
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'context' => $context,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
