<?php

namespace App\Http\Controllers\Billing;

use App\Jobs\Billing\ProcessStripeWebhookEvent;
use App\Models\StripeWebhookEvent;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * @var array<int, string>
     */
    private const ASYNC_EVENTS = [
        'checkout.session.completed',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.paid',
        'invoice.payment_failed',
    ];

    public function handleWebhook(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        $eventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'unknown';

        if (! is_string($eventId) || $eventId === '') {
            return parent::handleWebhook($request);
        }

        $event = StripeWebhookEvent::query()->firstOrCreate(
            ['stripe_event_id' => $eventId],
            [
                'event_type' => is_string($eventType) ? $eventType : 'unknown',
                'status' => 'received',
                'payload' => $payload,
            ]
        );

        if ($event->handled_by_cashier_at !== null && $event->processed_at !== null) {
            return $this->successMethod();
        }

        if ($event->handled_by_cashier_at === null) {
            $response = parent::handleWebhook($request);

            $event->forceFill([
                'event_type' => is_string($eventType) ? $eventType : $event->event_type,
                'payload' => $payload,
                'handled_by_cashier_at' => now(),
                'status' => $event->status === 'received' ? 'queued' : $event->status,
            ])->save();
        } else {
            $response = $this->successMethod();
        }

        if (in_array($event->event_type, self::ASYNC_EVENTS, true) && $event->processed_at === null) {
            ProcessStripeWebhookEvent::dispatch($event->id);
        } elseif ($event->processed_at === null) {
            $event->forceFill([
                'status' => 'ignored',
                'message' => 'No asynchronous processing required for this event type.',
                'processed_at' => now(),
            ])->save();
        }

        return $response instanceof Response ? $response : $this->successMethod();
    }
}
