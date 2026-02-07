<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BillingCheckoutRequest;
use App\Http\Requests\Settings\BillingSwapRequest;
use App\Models\StripeWebhookEvent;
use App\Models\User;
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
    public function edit(Request $request, BillingService $billing): Response
    {
        $user = $request->user();
        $subscription = $user?->subscription('default');
        $plans = $this->plans();

        return Inertia::render('settings/billing', [
            'status' => $request->session()->get('status'),
            'plans' => array_values($plans),
            'stripeConfigWarnings' => $this->stripeConfigWarnings(),
            'webhookOutcome' => $this->webhookOutcome(),
            'currentPriceId' => $subscription?->stripe_price,
            'isSubscribed' => $user?->subscribed('default') ?? false,
            'onGracePeriod' => $subscription?->onGracePeriod() ?? false,
            'endsAt' => $subscription?->ends_at?->toIso8601String(),
            'invoices' => $user === null ? [] : $this->safeInvoices($user, $billing),
        ]);
    }

    /**
     * Start Stripe Checkout for a selected plan.
     */
    public function checkout(BillingCheckoutRequest $request, BillingService $billing): SymfonyResponse
    {
        $planKey = $request->validated('plan');
        $priceId = $this->plans()[$planKey]['priceId'] ?? null;

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
        $priceId = $this->plans()[$planKey]['priceId'] ?? null;

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

    /**
     * @return array<int, array{
     *     id: string,
     *     number: string|null,
     *     status: string,
     *     total: string,
     *     amountPaid: string,
     *     date: string,
     *     currency: string,
     *     hostedInvoiceUrl: string|null,
     *     invoicePdfUrl: string|null
     * }>
     */
    private function safeInvoices(User $user, BillingService $billing): array
    {
        try {
            return $billing->invoices($user, 12);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{status: 'warning', message: string, occurredAt: string}|null
     */
    private function webhookOutcome(): ?array
    {
        $failedEvent = StripeWebhookEvent::query()
            ->where('event_type', 'invoice.payment_failed')
            ->where('status', 'action_required')
            ->latest('created_at')
            ->first();

        if ($failedEvent === null) {
            return null;
        }

        return [
            'status' => 'warning',
            'message' => 'Recent payment attempt failed. Ask the customer to update their payment method.',
            'occurredAt' => $failedEvent->created_at->toIso8601String(),
        ];
    }
}
