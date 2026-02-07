<?php

namespace App\Jobs\Billing;

use App\Models\StripeWebhookEvent;
use App\Models\Workspace;
use App\Notifications\Billing\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Cashier;
use Throwable;

class ProcessStripeWebhookEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $stripeWebhookEventId) {}

    public function uniqueId(): string
    {
        return (string) $this->stripeWebhookEventId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $event = StripeWebhookEvent::query()->find($this->stripeWebhookEventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        $payload = $event->payload;
        $customerId = data_get($payload, 'data.object.customer');

        $status = 'processed';
        $message = match ($event->event_type) {
            'checkout.session.completed' => 'Checkout session completed.',
            'customer.subscription.updated' => 'Subscription updated.',
            'customer.subscription.deleted' => 'Subscription deleted.',
            'invoice.paid' => 'Invoice paid.',
            'invoice.payment_failed' => 'Invoice payment failed. Customer action may be required.',
            default => 'Webhook received.',
        };

        if ($event->event_type === 'invoice.payment_failed') {
            $status = 'action_required';
            $this->sendPaymentFailedNotification($customerId, $event);
        }

        if (in_array($event->event_type, ['invoice.paid', 'checkout.session.completed', 'customer.subscription.updated'], true)) {
            $this->resolvePaymentFailedEvents($customerId, $event->id);
        }

        $event->forceFill([
            'status' => $status,
            'message' => $message,
            'error' => null,
            'processed_at' => now(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $event = StripeWebhookEvent::query()->find($this->stripeWebhookEventId);

        if ($event === null) {
            return;
        }

        $event->forceFill([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ])->save();
    }

    private function sendPaymentFailedNotification(mixed $customerId, StripeWebhookEvent $event): void
    {
        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $billable = Cashier::findBillable($customerId);

        if ($billable instanceof Workspace) {
            $owner = $billable->owner()->first();

            if ($owner !== null) {
                $owner->notify(new PaymentFailedNotification($event->stripe_event_id));
            }

            return;
        }

        if (! is_object($billable) || ! method_exists($billable, 'notify')) {
            return;
        }

        $billable->notify(new PaymentFailedNotification($event->stripe_event_id));
    }

    private function resolvePaymentFailedEvents(mixed $customerId, int $currentEventId): void
    {
        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        StripeWebhookEvent::query()
            ->where('event_type', 'invoice.payment_failed')
            ->where('status', 'action_required')
            ->where('id', '<', $currentEventId)
            ->where('payload->data->object->customer', $customerId)
            ->update([
                'status' => 'resolved',
                'message' => 'Payment issue was resolved by a later successful billing event.',
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
