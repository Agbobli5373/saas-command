<?php

namespace App\Jobs\Billing;

use App\Models\StripeWebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $status = 'processed';
        $message = match ($event->event_type) {
            'checkout.session.completed' => 'Checkout session completed.',
            'customer.subscription.updated' => 'Subscription updated.',
            'customer.subscription.deleted' => 'Subscription deleted.',
            'invoice.payment_failed' => 'Invoice payment failed. Customer action may be required.',
            default => 'Webhook received.',
        };

        if ($event->event_type === 'invoice.payment_failed') {
            $status = 'action_required';
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
}
