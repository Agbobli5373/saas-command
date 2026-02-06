<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BillingCheckoutRequest;
use App\Http\Requests\Settings\BillingSwapRequest;
use App\Services\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class BillingController extends Controller
{
    /**
     * Show the billing settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $subscription = $user?->subscription('default');
        $plans = $this->plans();

        return Inertia::render('settings/billing', [
            'status' => $request->session()->get('status'),
            'plans' => $plans,
            'currentPriceId' => $subscription?->stripe_price,
            'isSubscribed' => $user?->subscribed('default') ?? false,
            'onGracePeriod' => $subscription?->onGracePeriod() ?? false,
            'endsAt' => $subscription?->ends_at?->toIso8601String(),
        ]);
    }

    /**
     * Start Stripe Checkout for a selected plan.
     */
    public function checkout(BillingCheckoutRequest $request, BillingService $billing): SymfonyResponse
    {
        $planKey = $request->validated('plan');
        $priceId = $this->plans()[$planKey] ?? null;

        if ($priceId === null) {
            return to_route('billing.edit')->with('status', 'Invalid plan selected.');
        }

        if ($request->user()->subscribed('default')) {
            return to_route('billing.edit')->with('status', 'You already have an active subscription.');
        }

        try {
            $checkoutUrl = $billing->checkout(
                $request->user(),
                $priceId,
                route('billing.edit'),
                route('billing.edit')
            );

            return Inertia::location($checkoutUrl);
        } catch (Throwable) {
            return to_route('billing.edit')->with('status', 'Unable to start checkout right now.');
        }
    }

    /**
     * Open Stripe Billing Portal.
     */
    public function portal(Request $request, BillingService $billing): SymfonyResponse
    {
        try {
            $portalUrl = $billing->billingPortal($request->user(), route('billing.edit'));

            return Inertia::location($portalUrl);
        } catch (Throwable) {
            return to_route('billing.edit')->with('status', 'Unable to open billing portal right now.');
        }
    }

    /**
     * Swap the current subscription to a different plan.
     */
    public function swap(BillingSwapRequest $request, BillingService $billing): RedirectResponse
    {
        $planKey = $request->validated('plan');
        $priceId = $this->plans()[$planKey] ?? null;

        if ($priceId === null) {
            return to_route('billing.edit')->with('status', 'Invalid plan selected.');
        }

        try {
            $billing->swap($request->user(), $priceId);

            return to_route('billing.edit')->with('status', 'Your subscription has been updated.');
        } catch (Throwable) {
            return to_route('billing.edit')->with('status', 'Unable to update your subscription right now.');
        }
    }

    /**
     * Cancel the active subscription.
     */
    public function cancel(Request $request, BillingService $billing): RedirectResponse
    {
        try {
            $billing->cancel($request->user());

            return to_route('billing.edit')->with('status', 'Your subscription will end at the current period.');
        } catch (Throwable) {
            return to_route('billing.edit')->with('status', 'Unable to cancel your subscription right now.');
        }
    }

    /**
     * Resume a cancelled subscription during the grace period.
     */
    public function resume(Request $request, BillingService $billing): RedirectResponse
    {
        try {
            $billing->resume($request->user());

            return to_route('billing.edit')->with('status', 'Your subscription has been resumed.');
        } catch (Throwable) {
            return to_route('billing.edit')->with('status', 'Unable to resume your subscription right now.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function plans(): array
    {
        /** @var array<string, string> $plans */
        $plans = array_filter(
            config('services.stripe.prices', []),
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        );

        return $plans;
    }
}
