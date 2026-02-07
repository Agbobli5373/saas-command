<?php

namespace App\Services\Billing;

use App\Models\Workspace;
use Laravel\Cashier\Invoice;
use RuntimeException;

class CashierBillingService implements BillingService
{
    public function checkout(Workspace $workspace, string $priceId, string $successUrl, string $cancelUrl): string
    {
        $trialDays = (int) config('services.stripe.trial_days', 0);

        $subscriptionBuilder = $workspace->newSubscription('default', $priceId);

        if ($trialDays > 0) {
            $subscriptionBuilder = $subscriptionBuilder->trialDays($trialDays);
        }

        $checkout = $subscriptionBuilder->checkout([
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        if (! is_string($checkout->url) || $checkout->url === '') {
            throw new RuntimeException('Unable to create Stripe checkout session.');
        }

        return $checkout->url;
    }

    public function billingPortal(Workspace $workspace, string $returnUrl): string
    {
        return $workspace->billingPortalUrl($returnUrl);
    }

    public function invoices(Workspace $workspace, int $limit = 10): array
    {
        return $workspace
            ->invoicesIncludingPending(['limit' => $limit])
            ->map(function (Invoice $invoice): array {
                $stripeInvoice = $invoice->asStripeInvoice();

                return [
                    'id' => $stripeInvoice->id,
                    'number' => $stripeInvoice->number,
                    'status' => (string) $stripeInvoice->status,
                    'total' => $invoice->total(),
                    'amountPaid' => $invoice->amountPaid(),
                    'date' => $invoice->date()->toDateString(),
                    'currency' => strtoupper((string) ($stripeInvoice->currency ?? 'usd')),
                    'hostedInvoiceUrl' => $stripeInvoice->hosted_invoice_url,
                    'invoicePdfUrl' => $stripeInvoice->invoice_pdf,
                ];
            })
            ->values()
            ->all();
    }

    public function swap(Workspace $workspace, string $priceId): void
    {
        $subscription = $workspace->subscription('default');

        if ($subscription === null) {
            throw new RuntimeException('You do not have an active subscription to change.');
        }

        $subscription->swap($priceId);
    }

    public function cancel(Workspace $workspace): void
    {
        $subscription = $workspace->subscription('default');

        if ($subscription === null) {
            throw new RuntimeException('You do not have an active subscription to cancel.');
        }

        $subscription->cancel();
    }

    public function resume(Workspace $workspace): void
    {
        $subscription = $workspace->subscription('default');

        if ($subscription === null) {
            throw new RuntimeException('You do not have a cancelled subscription to resume.');
        }

        $subscription->resume();
    }
}
