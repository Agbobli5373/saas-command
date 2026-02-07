<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BillingCheckoutRequest;
use App\Http\Requests\Settings\BillingSwapRequest;
use App\Models\BillingAuditEvent;
use App\Models\StripeWebhookEvent;
use App\Models\Workspace;
use App\Services\Billing\BillingAuditLogger;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanService;
use App\Services\Usage\UsageMeteringService;
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
    public function edit(
        Request $request,
        BillingService $billing,
        PlanService $plans,
        UsageMeteringService $usage
    ): Response {
        $user = $request->user();
        $workspace = $user?->activeWorkspace();
        abort_if($workspace === null, 403);
        $this->authorize('manageBilling', $workspace);

        $subscription = $workspace->subscription('default');
        $seatCount = $workspace->seatCount();
        $pendingInvitationCount = $workspace->pendingInvitationCount();
        $currentPlan = $plans->resolveWorkspacePlan($workspace);

        return Inertia::render('settings/billing', [
            'status' => $request->session()->get('status'),
            'plans' => array_values($plans->all()),
            'stripeConfigWarnings' => $this->stripeConfigWarnings(),
            'webhookOutcome' => $this->webhookOutcome(),
            'currentPriceId' => $subscription?->stripe_price,
            'currentPlanKey' => $currentPlan['key'] ?? null,
            'currentPlanTitle' => $currentPlan['title'] ?? null,
            'currentPlanBillingMode' => $currentPlan['billingMode'] ?? null,
            'isSubscribed' => $workspace->subscribed('default'),
            'onGracePeriod' => $subscription?->onGracePeriod() ?? false,
            'endsAt' => $subscription?->ends_at?->toIso8601String(),
            'seatCount' => $seatCount,
            'seatLimit' => $plans->seatLimit($workspace),
            'remainingSeatCapacity' => $plans->remainingSeatCapacity($workspace, $pendingInvitationCount),
            'billedSeatCount' => $this->billedSeatCount($seatCount, $subscription?->quantity),
            'usagePeriod' => $usage->currentPeriodMeta(),
            'usageMetrics' => $usage->currentPeriodUsage($workspace),
            'invoices' => $this->safeInvoices($workspace, $billing),
            'auditTimeline' => $this->auditTimeline($workspace),
        ]);
    }

    /**
     * Start Stripe Checkout for a selected plan.
     */
    public function checkout(
        BillingCheckoutRequest $request,
        BillingService $billing,
        PlanService $plans,
        BillingAuditLogger $auditLogger
    ): SymfonyResponse {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('billing.edit')->with('status', 'Create or join a workspace before subscribing.');
        }

        $this->authorize('manageBilling', $workspace);

        $planKey = $request->validated('plan');
        $plan = $plans->checkoutPlans()[$planKey] ?? null;

        if (! is_array($plan)) {
            return to_route('billing.edit')->with('status', 'Invalid plan selected.');
        }

        $priceId = $plan['priceId'] ?? null;

        if (! is_string($priceId) || $priceId === '') {
            return to_route('billing.edit')->with('status', 'Invalid plan selected.');
        }

        if ($workspace->subscribed('default')) {
            return to_route('billing.edit')->with('status', 'You already have an active subscription.');
        }

        try {
            $checkoutUrl = $billing->checkout(
                $workspace,
                $priceId,
                route('billing.edit'),
                route('billing.edit')
            );

            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'checkout_started',
                title: 'Checkout started',
                description: sprintf('Stripe checkout was started for %s.', $plan['title']),
                context: [
                    'plan_key' => $plan['key'],
                    'price_id' => $priceId,
                ],
            );

            return Inertia::location($checkoutUrl);
        } catch (Throwable $exception) {
            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'checkout_start_failed',
                title: 'Checkout failed to start',
                description: 'Stripe checkout could not be started.',
                severity: 'error',
                context: [
                    'plan_key' => $plan['key'],
                    'price_id' => $priceId,
                    'error' => $exception->getMessage(),
                ],
            );

            return to_route('billing.edit')->with('status', 'Unable to start checkout right now.');
        }
    }

    /**
     * Open Stripe Billing Portal.
     */
    public function portal(Request $request, BillingService $billing, BillingAuditLogger $auditLogger): SymfonyResponse
    {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('billing.edit')->with('status', 'Create or join a workspace before managing billing.');
        }

        $this->authorize('manageBilling', $workspace);

        try {
            $portalUrl = $billing->billingPortal($workspace, route('billing.edit'));

            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'billing_portal_opened',
                title: 'Billing portal opened',
                description: 'Stripe customer portal was opened.',
            );

            return Inertia::location($portalUrl);
        } catch (Throwable $exception) {
            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'billing_portal_open_failed',
                title: 'Billing portal failed to open',
                description: 'Stripe customer portal could not be opened.',
                severity: 'error',
                context: [
                    'error' => $exception->getMessage(),
                ],
            );

            return to_route('billing.edit')->with('status', 'Unable to open billing portal right now.');
        }
    }

    /**
     * Swap the current subscription to a different plan.
     */
    public function swap(
        BillingSwapRequest $request,
        BillingService $billing,
        PlanService $plans,
        BillingAuditLogger $auditLogger
    ): RedirectResponse {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('billing.edit')->with('status', 'Create or join a workspace before changing plans.');
        }

        $this->authorize('manageBilling', $workspace);

        $planKey = $request->validated('plan');
        $plan = $plans->checkoutPlans()[$planKey] ?? null;

        if (! is_array($plan)) {
            return to_route('billing.edit')->with('status', 'Invalid plan selected.');
        }

        $priceId = $plan['priceId'] ?? null;

        if (! is_string($priceId) || $priceId === '') {
            return to_route('billing.edit')->with('status', 'Invalid plan selected.');
        }

        try {
            $billing->swap($workspace, $priceId);

            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'subscription_swapped',
                title: 'Subscription plan changed',
                description: sprintf('Subscription was changed to %s.', $plan['title']),
                context: [
                    'plan_key' => $plan['key'],
                    'price_id' => $priceId,
                ],
            );

            return to_route('billing.edit')->with('status', 'Your subscription has been updated.');
        } catch (Throwable $exception) {
            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'subscription_swap_failed',
                title: 'Subscription change failed',
                description: 'Subscription plan could not be updated.',
                severity: 'error',
                context: [
                    'plan_key' => $plan['key'],
                    'price_id' => $priceId,
                    'error' => $exception->getMessage(),
                ],
            );

            return to_route('billing.edit')->with('status', 'Unable to update your subscription right now.');
        }
    }

    /**
     * Cancel the active subscription.
     */
    public function cancel(Request $request, BillingService $billing, BillingAuditLogger $auditLogger): RedirectResponse
    {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('billing.edit')->with('status', 'Create or join a workspace before cancelling billing.');
        }

        $this->authorize('manageBilling', $workspace);

        try {
            $billing->cancel($workspace);

            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'subscription_cancelled',
                title: 'Subscription cancelled',
                description: 'Subscription was cancelled and moved to grace period.',
            );

            return to_route('billing.edit')->with('status', 'Your subscription will end at the current period.');
        } catch (Throwable $exception) {
            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'subscription_cancel_failed',
                title: 'Subscription cancellation failed',
                description: 'Subscription could not be cancelled.',
                severity: 'error',
                context: [
                    'error' => $exception->getMessage(),
                ],
            );

            return to_route('billing.edit')->with('status', 'Unable to cancel your subscription right now.');
        }
    }

    /**
     * Resume a cancelled subscription during the grace period.
     */
    public function resume(Request $request, BillingService $billing, BillingAuditLogger $auditLogger): RedirectResponse
    {
        $workspace = $request->user()->activeWorkspace();

        if ($workspace === null) {
            return to_route('billing.edit')->with('status', 'Create or join a workspace before resuming billing.');
        }

        $this->authorize('manageBilling', $workspace);

        try {
            $billing->resume($workspace);

            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'subscription_resumed',
                title: 'Subscription resumed',
                description: 'Subscription was resumed during grace period.',
            );

            return to_route('billing.edit')->with('status', 'Your subscription has been resumed.');
        } catch (Throwable $exception) {
            $this->logAuditEvent(
                auditLogger: $auditLogger,
                workspace: $workspace,
                actorId: $request->user()->id,
                eventType: 'subscription_resume_failed',
                title: 'Subscription resume failed',
                description: 'Subscription could not be resumed.',
                severity: 'error',
                context: [
                    'error' => $exception->getMessage(),
                ],
            );

            return to_route('billing.edit')->with('status', 'Unable to resume your subscription right now.');
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
    private function safeInvoices(Workspace $workspace, BillingService $billing): array
    {
        try {
            return $billing->invoices($workspace, 12);
        } catch (Throwable) {
            return [];
        }
    }

    private function billedSeatCount(int $seatCount, ?int $subscriptionQuantity): int
    {
        if (is_int($subscriptionQuantity) && $subscriptionQuantity > 0) {
            return $subscriptionQuantity;
        }

        return max(1, $seatCount);
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     eventType: string,
     *     source: string,
     *     severity: string,
     *     title: string,
     *     description: string|null,
     *     context: array<string, mixed>,
     *     occurredAt: string|null,
     *     actor: array{id: int, name: string, email: string}|null
     * }>
     */
    private function auditTimeline(Workspace $workspace): array
    {
        return BillingAuditEvent::query()
            ->with('actor:id,name,email')
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(static function (BillingAuditEvent $event): array {
                $actor = $event->actor;

                return [
                    'id' => $event->id,
                    'eventType' => $event->event_type,
                    'source' => $event->source,
                    'severity' => $event->severity,
                    'title' => $event->title,
                    'description' => $event->description,
                    'context' => is_array($event->context) ? $event->context : [],
                    'occurredAt' => $event->occurred_at?->toIso8601String(),
                    'actor' => $actor === null ? null : [
                        'id' => $actor->id,
                        'name' => $actor->name,
                        'email' => $actor->email,
                    ],
                ];
            })
            ->values()
            ->all();
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function logAuditEvent(
        BillingAuditLogger $auditLogger,
        Workspace $workspace,
        int $actorId,
        string $eventType,
        string $title,
        ?string $description = null,
        string $severity = 'info',
        array $context = []
    ): void {
        $auditLogger->record(
            workspace: $workspace,
            actorId: $actorId,
            eventType: $eventType,
            source: 'billing_action',
            severity: $severity,
            title: $title,
            description: $description,
            context: $context,
        );
    }
}
