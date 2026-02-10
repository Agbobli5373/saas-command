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
        $roleLabel = __($this->role === 'admin' ? 'Admin' : 'Member');

        return (new MailMessage)
            ->subject(__('Invitation to join :workspace', ['workspace' => $this->workspaceName]))
            ->greeting(__('You are invited'))
            ->line(__(':name invited you to join ":workspace" as :role.', [
                'name' => $this->invitedByName,
                'workspace' => $this->workspaceName,
                'role' => $roleLabel,
            ]))
            ->line(__('This invite expires on :date.', ['date' => $this->expiresAt->toDayDateTimeString()]))
            ->action(__('Accept invitation'), $this->acceptUrl)
            ->line(__('Sign in with this email address to accept the invite.'));
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
