<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\CompleteOnboardingRequest;
use App\Services\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->activeWorkspace();
        abort_if($workspace === null, 403);

        if ($workspace->subscribed('default')) {
            $user->markOnboardingCompleted();
        }

        if ($user->hasCompletedOnboarding()) {
            return to_route('dashboard');
        }

        return Inertia::render('onboarding', [
            'status' => $request->session()->get('status'),
            'workspaceName' => $workspace->name,
            'plans' => array_values($this->plans()),
            'stripeConfigWarnings' => $this->stripeConfigWarnings(),
        ]);
    }

    /**
     * Complete onboarding and start Stripe checkout.
     */
    public function store(CompleteOnboardingRequest $request, BillingService $billing): SymfonyResponse
    {
        $user = $request->user();
        $workspace = $user->activeWorkspace();
        abort_if($workspace === null, 403);
        $this->authorize('manageBilling', $workspace);

        $workspace->forceFill([
            'name' => (string) $request->validated('workspace_name'),
        ])->save();

        if ($workspace->subscribed('default')) {
            $user->markOnboardingCompleted();

            return to_route('dashboard')->with('status', 'Onboarding completed.');
        }

        $planKey = $request->validated('plan');
        $priceId = $this->plans()[$planKey]['priceId'] ?? null;

        if (! is_string($priceId) || $priceId === '') {
            return to_route('onboarding.show')->with('status', 'Invalid plan selected.');
        }

        try {
            $checkoutUrl = $billing->checkout(
                $workspace,
                $priceId,
                route('onboarding.show'),
                route('onboarding.show')
            );

            return Inertia::location($checkoutUrl);
        } catch (Throwable) {
            return to_route('onboarding.show')->with('status', 'Unable to start checkout right now.');
        }
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     priceId: string,
     *     title: string,
     *     priceLabel: string,
     *     intervalLabel: string,
     *     description: string,
     *     features: array<int, string>,
     *     highlighted: bool
     * }>
     */
    private function plans(): array
    {
        /** @var array<string, array<string, mixed>> $configuredPlans */
        $configuredPlans = config('services.stripe.plans', []);
        $plans = [];

        foreach ($configuredPlans as $planKey => $plan) {
            $priceId = $plan['price_id'] ?? null;

            if (! is_string($priceId) || $priceId === '') {
                continue;
            }

            $features = collect($plan['features'] ?? [])
                ->filter(static fn (mixed $feature): bool => is_string($feature) && $feature !== '')
                ->values()
                ->all();

            $plans[$planKey] = [
                'key' => $planKey,
                'priceId' => $priceId,
                'title' => is_string($plan['title'] ?? null) && $plan['title'] !== '' ? $plan['title'] : $planKey,
                'priceLabel' => is_string($plan['price_label'] ?? null) ? $plan['price_label'] : '',
                'intervalLabel' => is_string($plan['interval_label'] ?? null) ? $plan['interval_label'] : '',
                'description' => is_string($plan['description'] ?? null) ? $plan['description'] : '',
                'features' => $features,
                'highlighted' => (bool) ($plan['highlighted'] ?? false),
            ];
        }

        return $plans;
    }

    /**
     * @return array<int, string>
     */
    private function stripeConfigWarnings(): array
    {
        /** @var array<int, string> $warnings */
        $warnings = config('services.stripe.warnings', []);

        return $warnings;
    }
}
