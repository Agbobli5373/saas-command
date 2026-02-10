<?php

namespace App\Notifications\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $stripeEventId) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Payment failed for your subscription'))
            ->greeting(__('Action required'))
            ->line(__('We could not process your latest subscription payment.'))
            ->line(__('Update your payment method to keep your subscription active.'))
            ->action(__('Open Billing Settings'), route('billing.edit'))
            ->line(__('Event reference: :event', ['event' => $this->stripeEventId]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Payment failed'),
            'message' => __('Your latest subscription payment failed. Update your payment method to continue service.'),
            'stripe_event_id' => $this->stripeEventId,
            'action_url' => route('billing.edit'),
        ];
    }
}
