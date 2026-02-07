<?php

namespace App\Notifications\Workspace;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $workspaceName,
        public string $invitedByName,
        public string $acceptUrl,
        public CarbonInterface $expiresAt,
        public string $role,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = ucfirst($this->role);

        return (new MailMessage)
            ->subject(sprintf('Invitation to join %s', $this->workspaceName))
            ->greeting('You are invited')
            ->line(sprintf('%s invited you to join "%s" as %s.', $this->invitedByName, $this->workspaceName, $roleLabel))
            ->line(sprintf('This invite expires on %s.', $this->expiresAt->toDayDateTimeString()))
            ->action('Accept invitation', $this->acceptUrl)
            ->line('Sign in with this email address to accept the invite.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'workspace' => $this->workspaceName,
            'invited_by' => $this->invitedByName,
            'accept_url' => $this->acceptUrl,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'role' => $this->role,
        ];
    }
}
