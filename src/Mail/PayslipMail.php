<?php

namespace Jmal\Hris\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Jmal\Hris\Models\Payslip;

class PayslipMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payslip $payslip,
        protected string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        $period = $this->payslip->payPeriod?->name ?? 'Current Period';

        return new Envelope(
            subject: "Your Payslip for {$period}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'hris::emails.payslip',
            with: [
                'employeeName' => $this->payslip->employee->first_name,
                'periodName' => $this->payslip->payPeriod?->name ?? 'Current Period',
                'grossPay' => number_format((float) $this->payslip->gross_pay, 2),
                'totalDeductions' => number_format((float) $this->payslip->total_deductions, 2),
                'netPay' => number_format((float) $this->payslip->net_pay, 2),
            ],
        );
    }

    /** @return Attachment[] */
    public function attachments(): array
    {
        $emp = $this->payslip->employee;
        $pp = $this->payslip->payPeriod;
        $filename = "payslip-{$emp->employee_number}-{$pp?->start_date?->format('Y-m-d')}.pdf";

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
