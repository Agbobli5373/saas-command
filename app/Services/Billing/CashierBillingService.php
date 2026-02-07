<?php

namespace App\Services\Billing;

use App\Models\User;
use Laravel\Cashier\Invoice;
use RuntimeException;

class CashierBillingService implements BillingService
{
    public function checkout(User $user, string $priceId, string $successUrl, string $cancelUrl): string
    {
        $trialDays = (int) config('services.stripe.trial_days', 0);

        $subscriptionBuilder = $user->newSubscription('default', $priceId);

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

    public function billingPortal(User $user, string $returnUrl): string
    {
        return $user->billingPortalUrl($returnUrl);
    }

    public function invoices(User $user, int $limit = 10): array
    {
        return $user
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

    public function swap(User $user, string $priceId): void
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            throw new RuntimeException('You do not have an active subscription to change.');
        }

        $subscription->swap($priceId);
    }

    public function cancel(User $user): void
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            throw new RuntimeException('You do not have an active subscription to cancel.');
        }

        $subscription->cancel();
    }

    public function resume(User $user): void
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            throw new RuntimeException('You do not have a cancelled subscription to resume.');
        }

        $subscription->resume();
    }
}
