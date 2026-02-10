<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\CompleteOnboardingRequest;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanService;
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
    public function show(Request $request, PlanService $plans): Response|RedirectResponse
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
            'plans' => array_values($plans->all()),
            'stripeConfigWarnings' => $this->stripeConfigWarnings(),
        ]);
    }

    /**
     * Complete onboarding and start Stripe checkout.
     */
    public function store(
        CompleteOnboardingRequest $request,
        BillingService $billing,
        PlanService $plans
    ): SymfonyResponse {
        $user = $request->user();
        $workspace = $user->activeWorkspace();
        abort_if($workspace === null, 403);
        $this->authorize('manageBilling', $workspace);

        $workspace->forceFill([
            'name' => (string) $request->validated('workspace_name'),
        ])->save();

        if ($workspace->subscribed('default')) {
            $user->markOnboardingCompleted();

            return to_route('dashboard')->with('status', __('Onboarding completed.'));
        }

        $planKey = $request->validated('plan');
        $plan = $plans->find($planKey);

        if (! is_array($plan)) {
            return to_route('onboarding.show')->with('status', __('Invalid plan selected.'));
        }

        if ($plan['billingMode'] === 'free') {
            $user->markOnboardingCompleted();

            return to_route('dashboard')->with('status', __('Onboarding completed on :plan plan.', [
                'plan' => $plan['title'],
            ]));
        }

        $priceId = $plan['priceId'];

        if (! is_string($priceId) || $priceId === '') {
            return to_route('onboarding.show')->with('status', __('Invalid plan selected.'));
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
            return to_route('onboarding.show')->with('status', __('Unable to start checkout right now.'));
        }
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
