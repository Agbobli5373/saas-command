<?php

namespace App\Services\Billing;

use App\Models\Workspace;

class PlanService
{
    /**
     * @return array<string, array{
     *     key: string,
     *     billingMode: 'free'|'stripe',
     *     priceId: string|null,
     *     title: string,
     *     priceLabel: string,
     *     intervalLabel: string,
     *     description: string,
     *     features: array<int, string>,
     *     featureFlags: array<int, string>,
     *     limits: array{seats: int|null},
     *     highlighted: bool
     * }>
     */
    public function all(): array
    {
        /** @var array<string, array<string, mixed>> $configuredPlans */
        $configuredPlans = config('services.stripe.plans', []);

        $plans = [];

        foreach ($configuredPlans as $planKey => $plan) {
            if (! $this->isEnabled($plan)) {
                continue;
            }

            $billingMode = $this->billingMode($plan);
            $priceId = $this->priceId($plan);

            if ($billingMode === 'stripe' && $priceId === null) {
                continue;
            }

            $plans[$planKey] = [
                'key' => $planKey,
                'billingMode' => $billingMode,
                'priceId' => $priceId,
                'title' => $this->stringValue($plan['title'] ?? null) ?? $planKey,
                'priceLabel' => $this->stringValue($plan['price_label'] ?? null) ?? '',
                'intervalLabel' => $this->stringValue($plan['interval_label'] ?? null) ?? '',
                'description' => $this->stringValue($plan['description'] ?? null) ?? '',
                'features' => $this->stringList($plan['features'] ?? []),
                'featureFlags' => $this->stringList($plan['feature_flags'] ?? []),
                'limits' => [
                    'seats' => $this->intOrNull($plan['limits']['seats'] ?? null),
                ],
                'highlighted' => (bool) ($plan['highlighted'] ?? false),
            ];
        }

        return $plans;
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     billingMode: 'free'|'stripe',
     *     priceId: string|null,
     *     title: string,
     *     priceLabel: string,
     *     intervalLabel: string,
     *     description: string,
     *     features: array<int, string>,
     *     featureFlags: array<int, string>,
     *     limits: array{seats: int|null},
     *     highlighted: bool
     * }>
     */
    public function checkoutPlans(): array
    {
        return collect($this->all())
            ->filter(static fn (array $plan): bool => $plan['billingMode'] === 'stripe' && is_string($plan['priceId']))
            ->all();
    }

    /**
     * @return array{
     *     key: string,
     *     billingMode: 'free'|'stripe',
     *     priceId: string|null,
     *     title: string,
     *     priceLabel: string,
     *     intervalLabel: string,
     *     description: string,
     *     features: array<int, string>,
     *     featureFlags: array<int, string>,
     *     limits: array{seats: int|null},
     *     highlighted: bool
     * }|null
     */
    public function find(string $planKey): ?array
    {
        return $this->all()[$planKey] ?? null;
    }

    /**
     * @return array{
     *     key: string,
     *     billingMode: 'free'|'stripe',
     *     priceId: string|null,
     *     title: string,
     *     priceLabel: string,
     *     intervalLabel: string,
     *     description: string,
     *     features: array<int, string>,
     *     featureFlags: array<int, string>,
     *     limits: array{seats: int|null},
     *     highlighted: bool
     * }|null
     */
    public function resolveWorkspacePlan(Workspace $workspace): ?array
    {
        $activePriceId = $workspace->subscription('default')?->stripe_price;

        if (is_string($activePriceId) && $activePriceId !== '') {
            $matchedPlan = collect($this->checkoutPlans())
                ->first(static fn (array $plan): bool => $plan['priceId'] === $activePriceId);

            if (is_array($matchedPlan)) {
                return $matchedPlan;
            }

            return [
                'key' => 'active_stripe_plan',
                'billingMode' => 'stripe',
                'priceId' => $activePriceId,
                'title' => 'Active Stripe Plan',
                'priceLabel' => '',
                'intervalLabel' => '',
                'description' => 'A Stripe plan active on this workspace.',
                'features' => [],
                'featureFlags' => $this->defaultStripeFeatureFlags(),
                'limits' => [
                    'seats' => null,
                ],
                'highlighted' => false,
            ];
        }

        $defaultPlanKey = $this->defaultPlanKey();

        if ($defaultPlanKey === null) {
            return null;
        }

        return $this->all()[$defaultPlanKey] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function defaultStripeFeatureFlags(): array
    {
        return collect($this->checkoutPlans())
            ->flatMap(static fn (array $plan): array => $plan['featureFlags'])
            ->filter(static fn (mixed $flag): bool => is_string($flag) && $flag !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function defaultPlanKey(): ?string
    {
        /** @var mixed $configuredDefault */
        $configuredDefault = config('services.stripe.default_plan');

        if (is_string($configuredDefault) && isset($this->all()[$configuredDefault])) {
            return $configuredDefault;
        }

        foreach ($this->all() as $plan) {
            if ($plan['billingMode'] === 'free') {
                return $plan['key'];
            }
        }

        $firstPlan = array_key_first($this->all());

        return is_string($firstPlan) ? $firstPlan : null;
    }

    public function seatLimit(Workspace $workspace): ?int
    {
        return $this->resolveWorkspacePlan($workspace)['limits']['seats'] ?? null;
    }

    public function hasReachedSeatLimit(Workspace $workspace, int $pendingInvitations = 0): bool
    {
        $seatLimit = $this->seatLimit($workspace);

        if ($seatLimit === null) {
            return false;
        }

        $pendingInvitations = max(0, $pendingInvitations);

        return ($workspace->seatCount() + $pendingInvitations) >= $seatLimit;
    }

    public function remainingSeatCapacity(Workspace $workspace, int $pendingInvitations = 0): ?int
    {
        $seatLimit = $this->seatLimit($workspace);

        if ($seatLimit === null) {
            return null;
        }

        $pendingInvitations = max(0, $pendingInvitations);

        return max(0, $seatLimit - ($workspace->seatCount() + $pendingInvitations));
    }

    public function workspaceHasFeature(Workspace $workspace, string $featureFlag): bool
    {
        if ($featureFlag === '') {
            return false;
        }

        $plan = $this->resolveWorkspacePlan($workspace);

        if ($plan === null) {
            return false;
        }

        return in_array($featureFlag, $plan['featureFlags'], true);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function isEnabled(array $plan): bool
    {
        /** @var mixed $enabled */
        $enabled = $plan['enabled'] ?? true;

        if (is_bool($enabled)) {
            return $enabled;
        }

        if (is_string($enabled)) {
            return ! in_array(strtolower($enabled), ['0', 'false', 'off', 'no'], true);
        }

        return (bool) $enabled;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return 'free'|'stripe'
     */
    private function billingMode(array $plan): string
    {
        $billingMode = strtolower((string) ($plan['billing_mode'] ?? 'stripe'));

        return $billingMode === 'free' ? 'free' : 'stripe';
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function priceId(array $plan): ?string
    {
        return $this->stringValue($plan['price_id'] ?? null);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_iterable($value)) {
            return [];
        }

        return collect($value)
            ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(static fn (string $item): string => trim($item))
            ->values()
            ->all();
    }

    private function intOrNull(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($int) ? $int : null;
    }
}
