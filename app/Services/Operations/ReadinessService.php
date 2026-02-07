<?php

namespace App\Services\Operations;

use App\Models\StripeWebhookEvent;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadinessService
{
    /**
     * Build a readiness snapshot for a workspace.
     *
     * @return array{
     *     checkedAt: string,
     *     overallStatus: 'pass'|'warning'|'fail',
     *     checks: array<int, array{
     *         key: string,
     *         label: string,
     *         status: 'pass'|'warning'|'fail',
     *         summary: string,
     *         value: int|string|null
     *     }>
     * }
     */
    public function snapshot(Workspace $workspace): array
    {
        $checks = [
            $this->failedJobsCheck(),
            $this->stripeWebhookQueueCheck($workspace),
            $this->stripeWebhookFailuresCheck($workspace),
            $this->backupFreshnessCheck(),
        ];

        return [
            'checkedAt' => now()->toIso8601String(),
            'overallStatus' => $this->overallStatus($checks),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'pass'|'warning'|'fail', summary: string, value: int}
     */
    private function failedJobsCheck(): array
    {
        $count = DB::table('failed_jobs')->count();
        $warningThreshold = max(0, (int) config('operations.failed_jobs.warning_threshold', 1));

        $status = $count >= max(1, $warningThreshold) ? 'warning' : 'pass';

        return [
            'key' => 'failed_jobs',
            'label' => 'Failed jobs',
            'status' => $status,
            'summary' => sprintf('%d failed jobs currently stored.', $count),
            'value' => $count,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'pass'|'warning'|'fail', summary: string, value: int|null}
     */
    private function stripeWebhookQueueCheck(Workspace $workspace): array
    {
        if (! is_string($workspace->stripe_id) || $workspace->stripe_id === '') {
            return [
                'key' => 'stripe_webhook_queue',
                'label' => 'Stripe webhook queue',
                'status' => 'pass',
                'summary' => 'No Stripe customer connected for this workspace.',
                'value' => null,
            ];
        }

        $staleMinutes = max(1, (int) config('operations.stripe_webhooks.stale_minutes', 10));
        $cutoff = now()->subMinutes($staleMinutes);

        $staleQueuedEvents = StripeWebhookEvent::query()
            ->where('status', 'queued')
            ->where('created_at', '<=', $cutoff)
            ->get()
            ->filter(fn (StripeWebhookEvent $event): bool => data_get($event->payload, 'data.object.customer') === $workspace->stripe_id)
            ->count();

        return [
            'key' => 'stripe_webhook_queue',
            'label' => 'Stripe webhook queue',
            'status' => $staleQueuedEvents > 0 ? 'warning' : 'pass',
            'summary' => $staleQueuedEvents > 0
                ? sprintf('%d queued Stripe webhook events are older than %d minutes.', $staleQueuedEvents, $staleMinutes)
                : 'No stale Stripe webhook events are pending.',
            'value' => $staleQueuedEvents,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'pass'|'warning'|'fail', summary: string, value: int|null}
     */
    private function stripeWebhookFailuresCheck(Workspace $workspace): array
    {
        if (! is_string($workspace->stripe_id) || $workspace->stripe_id === '') {
            return [
                'key' => 'stripe_webhook_failures',
                'label' => 'Stripe webhook failures',
                'status' => 'pass',
                'summary' => 'No Stripe customer connected for this workspace.',
                'value' => null,
            ];
        }

        $lookbackHours = max(1, (int) config('operations.stripe_webhooks.failure_lookback_hours', 24));
        $cutoff = now()->subHours($lookbackHours);

        $failedEvents = StripeWebhookEvent::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', $cutoff)
            ->get()
            ->filter(fn (StripeWebhookEvent $event): bool => data_get($event->payload, 'data.object.customer') === $workspace->stripe_id)
            ->count();

        return [
            'key' => 'stripe_webhook_failures',
            'label' => 'Stripe webhook failures',
            'status' => $failedEvents > 0 ? 'warning' : 'pass',
            'summary' => $failedEvents > 0
                ? sprintf('%d Stripe webhook events failed in the last %d hours.', $failedEvents, $lookbackHours)
                : sprintf('No failed Stripe webhooks in the last %d hours.', $lookbackHours),
            'value' => $failedEvents,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'pass'|'warning'|'fail', summary: string, value: string|null}
     */
    private function backupFreshnessCheck(): array
    {
        $healthFile = trim((string) config('operations.backup.health_file', ''));

        if ($healthFile === '') {
            return [
                'key' => 'backup_freshness',
                'label' => 'Backup freshness',
                'status' => 'fail',
                'summary' => 'Backup health file is not configured.',
                'value' => null,
            ];
        }

        if (! is_file($healthFile)) {
            return [
                'key' => 'backup_freshness',
                'label' => 'Backup freshness',
                'status' => 'fail',
                'summary' => sprintf('Backup health file is missing at %s.', $healthFile),
                'value' => null,
            ];
        }

        $modifiedAt = filemtime($healthFile);

        if (! is_int($modifiedAt)) {
            return [
                'key' => 'backup_freshness',
                'label' => 'Backup freshness',
                'status' => 'fail',
                'summary' => 'Unable to read backup health file timestamp.',
                'value' => null,
            ];
        }

        $maxAgeHours = max(1, (int) config('operations.backup.max_age_hours', 26));
        $lastSuccess = Carbon::createFromTimestamp($modifiedAt);

        if ($lastSuccess->lt(now()->subHours($maxAgeHours))) {
            return [
                'key' => 'backup_freshness',
                'label' => 'Backup freshness',
                'status' => 'fail',
                'summary' => sprintf(
                    'Latest backup marker is older than %d hours (%s).',
                    $maxAgeHours,
                    $lastSuccess->toDateTimeString()
                ),
                'value' => $lastSuccess->toIso8601String(),
            ];
        }

        return [
            'key' => 'backup_freshness',
            'label' => 'Backup freshness',
            'status' => 'pass',
            'summary' => sprintf('Latest backup marker updated at %s.', $lastSuccess->toDateTimeString()),
            'value' => $lastSuccess->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{status: 'pass'|'warning'|'fail'}>  $checks
     * @return 'pass'|'warning'|'fail'
     */
    private function overallStatus(array $checks): string
    {
        if (collect($checks)->contains(static fn (array $check): bool => $check['status'] === 'fail')) {
            return 'fail';
        }

        if (collect($checks)->contains(static fn (array $check): bool => $check['status'] === 'warning')) {
            return 'warning';
        }

        return 'pass';
    }
}
