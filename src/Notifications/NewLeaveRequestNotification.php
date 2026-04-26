<?php

namespace Jmal\Hris\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Jmal\Hris\Models\LeaveRequest;

class NewLeaveRequestNotification extends Notification
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
        $emp = $this->leaveRequest->employee;
        $type = $this->leaveRequest->leaveType?->name ?? 'Leave';

        return [
            'title' => 'New Leave Request',
            'body' => "{$emp->first_name} {$emp->last_name} filed a {$type} request.",
            'action_url' => '/leave?status=pending',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body'])
            ->action('Review Requests', url($data['action_url']));
    }
}
