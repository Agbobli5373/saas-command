<?php

namespace App\Services\Billing;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Usage\UsageMeteringService;

class WorkspaceEntitlementService
{
    public function __construct(
        private PlanService $plans,
        private UsageMeteringService $usage
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     hasInviteRole: bool,
     *     reasonCode: 'ok'|'insufficient_role'|'feature_unavailable'|'seat_limit_reached'|'usage_limit_reached',
     *     message: string|null,
     *     seatLimit: int|null,
     *     remainingSeatCapacity: int|null,
     *     hasReachedSeatLimit: bool,
     *     usageQuota: int|null,
     *     usageUsed: int,
     *     usageRemaining: int|null,
     *     usageMetricKey: string
     * }
     */
    public function inviteMembers(User $user, Workspace $workspace, int $pendingInvitations = 0): array
    {
        $hasInviteRole = in_array(
            $user->workspaceRole($workspace),
            [WorkspaceRole::Owner, WorkspaceRole::Admin],
            true
        );

        $seatLimit = $this->plans->seatLimit($workspace);
        $remainingSeatCapacity = $this->plans->remainingSeatCapacity($workspace, $pendingInvitations);
        $hasReachedSeatLimit = $this->plans->hasReachedSeatLimit($workspace, $pendingInvitations);
        $usageMetric = $this->usageMetric($workspace, 'team_invitations_sent');

        if (! $hasInviteRole) {
            return $this->decision(
                allowed: false,
                hasInviteRole: false,
                reasonCode: 'insufficient_role',
                message: 'Only workspace owners or admins can invite teammates.',
                seatLimit: $seatLimit,
                remainingSeatCapacity: $remainingSeatCapacity,
                hasReachedSeatLimit: $hasReachedSeatLimit,
                usageMetric: $usageMetric,
            );
        }

        if (! $this->plans->workspaceHasFeature($workspace, 'team_invitations')) {
            return $this->decision(
                allowed: false,
                hasInviteRole: true,
                reasonCode: 'feature_unavailable',
                message: 'Team invitations are not included in your current plan.',
                seatLimit: $seatLimit,
                remainingSeatCapacity: $remainingSeatCapacity,
                hasReachedSeatLimit: $hasReachedSeatLimit,
                usageMetric: $usageMetric,
            );
        }

        if ($hasReachedSeatLimit) {
            $currentPlan = $this->plans->resolveWorkspacePlan($workspace);
            $planTitle = is_array($currentPlan) ? (string) ($currentPlan['title'] ?? 'Current') : 'Current';

            $message = $seatLimit === null
                ? 'Workspace seat limit reached. Upgrade to invite more teammates.'
                : sprintf('%s plan allows up to %d seats. Upgrade to invite more teammates.', $planTitle, $seatLimit);

            return $this->decision(
                allowed: false,
                hasInviteRole: true,
                reasonCode: 'seat_limit_reached',
                message: $message,
                seatLimit: $seatLimit,
                remainingSeatCapacity: $remainingSeatCapacity,
                hasReachedSeatLimit: true,
                usageMetric: $usageMetric,
            );
        }

        if ($usageMetric['isExceeded']) {
            $message = $usageMetric['quota'] === null
                ? 'Monthly invitation limit reached for this workspace.'
                : sprintf(
                    'Monthly invitation limit reached (%d/%d). Upgrade your plan to invite more teammates.',
                    $usageMetric['used'],
                    $usageMetric['quota']
                );

            return $this->decision(
                allowed: false,
                hasInviteRole: true,
                reasonCode: 'usage_limit_reached',
                message: $message,
                seatLimit: $seatLimit,
                remainingSeatCapacity: $remainingSeatCapacity,
                hasReachedSeatLimit: false,
                usageMetric: $usageMetric,
            );
        }

        return $this->decision(
            allowed: true,
            hasInviteRole: true,
            reasonCode: 'ok',
            message: null,
            seatLimit: $seatLimit,
            remainingSeatCapacity: $remainingSeatCapacity,
            hasReachedSeatLimit: false,
            usageMetric: $usageMetric,
        );
    }

    /**
     * @return array{
     *     key: string,
     *     quota: int|null,
     *     used: int,
     *     remaining: int|null,
     *     isExceeded: bool
     * }
     */
    private function usageMetric(Workspace $workspace, string $metricKey): array
    {
        $metric = collect($this->usage->currentPeriodUsage($workspace))
            ->first(static fn (array $item): bool => $item['key'] === $metricKey);

        if (! is_array($metric)) {
            return [
                'key' => $metricKey,
                'quota' => null,
                'used' => 0,
                'remaining' => null,
                'isExceeded' => false,
            ];
        }

        return [
            'key' => $metricKey,
            'quota' => is_int($metric['quota']) ? $metric['quota'] : null,
            'used' => max(0, (int) ($metric['used'] ?? 0)),
            'remaining' => is_int($metric['remaining']) ? $metric['remaining'] : null,
            'isExceeded' => (bool) ($metric['isExceeded'] ?? false),
        ];
    }

    /**
     * @param  array{
     *     key: string,
     *     quota: int|null,
     *     used: int,
     *     remaining: int|null,
     *     isExceeded: bool
     * }  $usageMetric
     * @return array{
     *     allowed: bool,
     *     hasInviteRole: bool,
     *     reasonCode: 'ok'|'insufficient_role'|'feature_unavailable'|'seat_limit_reached'|'usage_limit_reached',
     *     message: string|null,
     *     seatLimit: int|null,
     *     remainingSeatCapacity: int|null,
     *     hasReachedSeatLimit: bool,
     *     usageQuota: int|null,
     *     usageUsed: int,
     *     usageRemaining: int|null,
     *     usageMetricKey: string
     * }
     */
    private function decision(
        bool $allowed,
        bool $hasInviteRole,
        string $reasonCode,
        ?string $message,
        ?int $seatLimit,
        ?int $remainingSeatCapacity,
        bool $hasReachedSeatLimit,
        array $usageMetric
    ): array {
        return [
            'allowed' => $allowed,
            'hasInviteRole' => $hasInviteRole,
            'reasonCode' => $reasonCode,
            'message' => $message,
            'seatLimit' => $seatLimit,
            'remainingSeatCapacity' => $remainingSeatCapacity,
            'hasReachedSeatLimit' => $hasReachedSeatLimit,
            'usageQuota' => $usageMetric['quota'],
            'usageUsed' => $usageMetric['used'],
            'usageRemaining' => $usageMetric['remaining'],
            'usageMetricKey' => $usageMetric['key'],
        ];
    }
}
