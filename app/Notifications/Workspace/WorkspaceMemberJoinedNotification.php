<?php

namespace App\Notifications\Workspace;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkspaceMemberJoinedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $workspaceName,
        public string $memberName
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('New teammate joined'),
            'message' => __(':member joined :workspace.', [
                'member' => $this->memberName,
                'workspace' => $this->workspaceName,
            ]),
            'action_url' => route('workspace'),
        ];
    }
}
