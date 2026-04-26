<?php

namespace Jmal\Hris\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Jmal\Hris\Models\LeaveRequest;

class LeaveRejectedNotification extends Notification
{
    public function __construct(
        public readonly LeaveRequest $leaveRequest,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $type = $this->leaveRequest->leaveType?->name ?? 'Leave';

        return [
            'title' => 'Leave Rejected',
            'body' => "Your {$type} request ({$this->leaveRequest->start_date} to {$this->leaveRequest->end_date}) was rejected.",
            'action_url' => '/portal/leaves',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body'])
            ->action('View My Leave', url($data['action_url']));
    }
}
