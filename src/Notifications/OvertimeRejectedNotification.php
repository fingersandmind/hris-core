<?php

namespace Jmal\Hris\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Jmal\Hris\Models\OvertimeRequest;

class OvertimeRejectedNotification extends Notification
{
    public function __construct(
        public readonly OvertimeRequest $overtimeRequest,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Overtime Rejected',
            'body' => "Your overtime request for {$this->overtimeRequest->date} was rejected.",
            'action_url' => '/portal/overtime',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body'])
            ->action('View My Overtime', url($data['action_url']));
    }
}
