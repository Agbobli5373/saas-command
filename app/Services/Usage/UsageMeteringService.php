<?php

namespace App\Services\Usage;

use App\Models\Workspace;
use App\Models\WorkspaceUsageCounter;
use App\Models\WorkspaceUsageEvent;
use App\Services\Billing\PlanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UsageMeteringService
{
    public function __construct(private PlanService $plans) {}

    /**
     * Track a usage event and update the monthly counter.
     *
     * @param  array<string, mixed>  $context
     */
    public function track(
        Workspace $workspace,
        string $metricKey,
        int $quantity = 1,
        array $context = [],
        ?CarbonImmutable $occurredAt = null
    ): WorkspaceUsageCounter {
        $metric = $this->metric($metricKey);

        if ($metric === null) {
            throw new InvalidArgumentException(sprintf('Metric "%s" is not configured.', $metricKey));
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $timestamp = $occurredAt?->utc() ?? now()->toImmutable();
        $periodStart = $this->periodStart($timestamp);
        $periodStartDate = $periodStart->toDateString();
        $quota = $this->quota($workspace, $metricKey);

        return DB::transaction(function () use ($workspace, $metricKey, $quantity, $context, $timestamp, $periodStartDate, $quota): WorkspaceUsageCounter {
            WorkspaceUsageEvent::query()->create([
                'workspace_id' => $workspace->id,
                'metric_key' => $metricKey,
                'quantity' => $quantity,
                'occurred_at' => $timestamp,
                'period_start' => $periodStartDate,
                'context' => $context,
            ]);

            $counter = WorkspaceUsageCounter::query()
                ->where('workspace_id', $workspace->id)
                ->where('metric_key', $metricKey)
                ->whereDate('period_start', $periodStartDate)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                $counter = WorkspaceUsageCounter::query()->create([
                    'workspace_id' => $workspace->id,
                    'metric_key' => $metricKey,
                    'period_start' => $periodStartDate,
                    'used' => 0,
                    'quota' => $quota,
                ]);
            }

            $counter->forceFill([
                'used' => max(0, $counter->used) + $quantity,
                'quota' => $quota,
            ])->save();

            return $counter->refresh();
        }, 3);
    }

    /**
     * Track usage only if the metric is configured.
     *
     * @param  array<string, mixed>  $context
     */
    public function trackIfConfigured(
        Workspace $workspace,
        string $metricKey,
        int $quantity = 1,
        array $context = [],
        ?CarbonImmutable $occurredAt = null
    ): ?WorkspaceUsageCounter {
        if (! $this->hasMetric($metricKey)) {
            return null;
        }

        return $this->track($workspace, $metricKey, $quantity, $context, $occurredAt);
    }

    /**
     * Resolve metric usage for the current billing month.
     *
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     quota: int|null,
     *     used: int,
     *     remaining: int|null,
     *     percentage: float|null,
     *     isUnlimited: bool,
     *     isExceeded: bool
     * }>
     */
    public function currentPeriodUsage(Workspace $workspace, ?CarbonImmutable $reference = null): array
    {
        $periodStart = $this->periodStart($reference ?? now()->toImmutable())->toDateString();

        $counters = WorkspaceUsageCounter::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('period_start', $periodStart)
            ->get()
            ->keyBy('metric_key');

        return collect($this->metrics())
            ->map(function (array $metric, string $metricKey) use ($workspace, $counters): array {
                $counter = $counters->get($metricKey);
                $used = max(0, (int) ($counter?->used ?? 0));
                $quota = $counter?->quota ?? $this->quota($workspace, $metricKey);
                $remaining = $quota === null ? null : max(0, $quota - $used);
                $percentage = $quota === null || $quota < 1
                    ? null
                    : round(min(100, ($used / $quota) * 100), 2);

                return [
                    'key' => $metricKey,
                    'title' => $metric['title'],
                    'description' => $metric['description'],
                    'quota' => $quota,
                    'used' => $used,
                    'remaining' => $remaining,
                    'percentage' => $percentage,
                    'isUnlimited' => $quota === null,
                    'isExceeded' => $quota !== null && $used >= $quota,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve quota for a usage metric in the workspace's active plan.
     */
    public function quota(Workspace $workspace, string $metricKey): ?int
    {
        $metric = $this->metric($metricKey);

        if ($metric === null || $metric['limitKey'] === null) {
            return null;
        }

        return $this->plans->workspaceLimit($workspace, $metric['limitKey']);
    }

    /**
     * Resolve period metadata for the current usage window.
     *
     * @return array{start: string, end: string, label: string}
     */
    public function currentPeriodMeta(?CarbonImmutable $reference = null): array
    {
        $periodStart = $this->periodStart($reference ?? now()->toImmutable());

        return [
            'start' => $periodStart->toDateString(),
            'end' => $periodStart->endOfMonth()->toDateString(),
            'label' => $periodStart->format('F Y'),
        ];
    }

    public function hasMetric(string $metricKey): bool
    {
        return $this->metric($metricKey) !== null;
    }

    private function periodStart(CarbonImmutable $reference): CarbonImmutable
    {
        return $reference->startOfMonth()->startOfDay();
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     limitKey: string|null
     * }|null
     */
    private function metric(string $metricKey): ?array
    {
        if ($metricKey === '') {
            return null;
        }

        return $this->metrics()[$metricKey] ?? null;
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     limitKey: string|null
     * }>
     */
    private function metrics(): array
    {
        /** @var mixed $configuredMetrics */
        $configuredMetrics = config('services.stripe.usage.metrics', []);

        if (! is_iterable($configuredMetrics)) {
            return [];
        }

        $metrics = [];

        foreach ($configuredMetrics as $metricKey => $metric) {
            if (! is_string($metricKey)) {
                continue;
            }

            $metricKey = trim($metricKey);

            if ($metricKey === '') {
                continue;
            }

            $metric = is_array($metric) ? $metric : [];

            $metrics[$metricKey] = [
                'key' => $metricKey,
                'title' => $this->stringValue($metric['title'] ?? null) ?? $this->fallbackTitle($metricKey),
                'description' => $this->stringValue($metric['description'] ?? null),
                'limitKey' => $this->stringValue($metric['limit_key'] ?? null),
            ];
        }

        return $metrics;
    }

    private function fallbackTitle(string $metricKey): string
    {
        return ucwords(str_replace('_', ' ', $metricKey));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
