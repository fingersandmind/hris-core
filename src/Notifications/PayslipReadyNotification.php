<?php

namespace Jmal\Hris\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Jmal\Hris\Models\Payslip;

class PayslipReadyNotification extends Notification
{
    public function __construct(
        public readonly Payslip $payslip,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $period = $this->payslip->payPeriod?->name ?? 'Current period';

        return [
            'title' => 'Payslip Ready',
            'body' => "Your payslip for {$period} is now available. Net pay: ₱" . number_format((float) $this->payslip->net_pay, 2),
            'action_url' => '/portal/payslips',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body'])
            ->action('View Payslip', url($data['action_url']));
    }
}
