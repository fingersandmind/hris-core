<?php

namespace Jmal\Hris\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Jmal\Hris\Models\Loan;

class NewLoanApplicationNotification extends Notification
{
    public function __construct(
        public readonly Loan $loan,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $emp = $this->loan->employee;

        return [
            'title' => 'New Loan Application',
            'body' => "{$emp->first_name} {$emp->last_name} applied for a loan of ₱" . number_format((float) $this->loan->amount, 2) . '.',
            'action_url' => '/loans?status=pending',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body'])
            ->action('Review Applications', url($data['action_url']));
    }
}
